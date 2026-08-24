<?php

declare(strict_types=1);

namespace App\Services\Idp\Providers\Eduplaces;

use App\Services\Idp\Contracts\IdentityDirectory;
use App\Services\Idp\DirectoryException;
use App\Services\Idp\Dto\IdpGroup;
use App\Services\Idp\Dto\IdpGroupRef;
use App\Services\Idp\Dto\IdpSchool;
use App\Services\Idp\Dto\IdpUser;
use App\Services\Idp\IdpProviders;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Read-only client for the Eduplaces IDM API.
 *
 * Webhook payloads carry an entity id and the names of the changed properties,
 * never the values, so ProcessIdpWebhookEvent reads every entity back through
 * this client. Authenticated with an OAuth2 client-credentials token:
 * https://developer.eduplaces.de/authentication/client-credentials
 *
 * A 404 returns null instead of throwing: an entity can be deleted between the
 * webhook firing and the read-back.
 */
final class EduplacesDirectory implements IdentityDirectory
{
    private const string API_PREFIX = '/idm/ep/v1';

    /**
     * Subtracted from expires_in so a cached token cannot expire between the
     * cache read and the API receiving the request.
     */
    private const int TOKEN_EXPIRY_MARGIN_SECONDS = 60;

    private const int REQUEST_TIMEOUT_SECONDS = 15;

    /**
     * Process-local copy of the cached token, so a worker running many jobs
     * reads the cache once.
     *
     * accessToken() deliberately does not read the cache through
     * `tenancy()->central()`: that ends tenancy mid-call and purges the
     * dynamically created `tenant` database connection while a database-backed
     * cache store still holds it, raising "Database connection [tenant] not
     * configured". The cost is one token per tenant cache prefix.
     */
    private ?string $accessToken = null;

    public function __construct(
        private readonly IdpProviders $providers,
    ) {}

    private function person(string $personId): ?IdpUser
    {
        $data = $this->get(self::API_PREFIX.'/people/'.urlencode($personId));

        return is_array($data) ? IdpUser::fromArray($data) : null;
    }

    public function user(string $userId): ?IdpUser
    {
        $data = $this->get(self::API_PREFIX.'/users/'.urlencode($userId));

        return is_array($data) ? IdpUser::fromArray($data) : null;
    }

    public function group(string $groupId): ?IdpGroup
    {
        $data = $this->get(self::API_PREFIX.'/groups/'.urlencode($groupId));

        return is_array($data) ? IdpGroup::fromArray($data) : null;
    }

    public function school(string $schoolId): ?IdpSchool
    {
        $data = $this->get(self::API_PREFIX.'/schools/'.urlencode($schoolId));

        return is_array($data) ? IdpSchool::fromArray($data) : null;
    }

    /**
     * @return list<IdpUser>
     */
    private function schoolPeople(string $schoolId): array
    {
        $data = $this->get(self::API_PREFIX.'/schools/'.urlencode($schoolId).'/people');

        return $this->mapList($data, fn (array $row): IdpUser => IdpUser::fromArray($row));
    }

    /**
     * Accounts that can sign in. Overlaps schoolPeople() without being a subset
     * of it: a user can exist with no person record.
     *
     * @return list<IdpUser>
     */
    private function schoolUsers(string $schoolId): array
    {
        $data = $this->get(self::API_PREFIX.'/schools/'.urlencode($schoolId).'/users');

        return $this->mapList($data, fn (array $row): IdpUser => IdpUser::fromArray($row));
    }

    /**
     * @return list<IdpGroupRef>
     */
    private function schoolGroupRefs(string $schoolId): array
    {
        $data = $this->get(self::API_PREFIX.'/schools/'.urlencode($schoolId).'/groups');

        return $this->mapList($data, fn (array $row): IdpGroupRef => IdpGroupRef::fromArray($row));
    }

    /**
     * Every group of the school, read in full.
     *
     * schoolGroupRefs() returns id and name only. group() adds members, the one
     * place Eduplaces exposes real names to an app holding pseudonymous
     * entitlements, at one call per group.
     *
     * @return list<IdpGroup>
     */
    public function groups(string $schoolId): array
    {
        $groups = [];

        foreach ($this->schoolGroupRefs($schoolId) as $ref) {
            $groups[] = $this->group($ref->id) ?? new IdpGroup($ref->id, $ref->name, $ref->status);
        }

        return $groups;
    }

    /**
     * Everyone at the school, merged by id across the two endpoints Eduplaces
     * splits this over: `/people` adds sourceSystemIdentifier and needs a scope
     * the app may not hold, `/users` adds status and a pseudonym. A refusal on
     * `/people` is logged and stepped over.
     *
     * @return list<IdpUser>
     */
    public function users(string $schoolId): array
    {
        $merged = [];

        foreach ($this->optionalPeople($schoolId) as $person) {
            $merged[$person->id] = $person;
        }

        foreach ($this->schoolUsers($schoolId) as $user) {
            $merged[$user->id] = isset($merged[$user->id])
                ? $merged[$user->id]->mergedWith($user)
                : $user;
        }

        return array_values($merged);
    }

    /**
     * @return list<IdpUser>
     */
    private function optionalPeople(string $schoolId): array
    {
        try {
            return $this->schoolPeople($schoolId);
        } catch (DirectoryException $e) {
            Log::warning('Eduplaces: people listing unavailable, using users alone', [
                'school' => $schoolId,
                'reason' => $e->reason,
            ]);

            return [];
        }
    }

    /**
     * One user, merging the same two views as users().
     */
    public function personOrUser(string $userId): ?IdpUser
    {
        try {
            $person = $this->person($userId);
        } catch (DirectoryException $e) {
            Log::warning('Eduplaces: person lookup unavailable, using the user record alone', [
                'user' => $userId,
                'reason' => $e->reason,
            ]);
            $person = null;
        }

        $user = $this->user($userId);

        if ($person === null) {
            return $user;
        }

        return $user === null ? $person : $person->mergedWith($user);
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        return $this->providers->config('eduplaces', $key, $default);
    }

    public function forgetToken(): void
    {
        $this->accessToken = null;

        Cache::forget($this->tokenCacheKey());
    }

    /**
     * @return array<array-key, mixed>|null null when the entity does not exist
     */
    private function get(string $path): ?array
    {
        $response = $this->send($path);

        if ($response->status() === 401) {
            // Token rejected: forget it and retry once with a newly minted one.
            $this->forgetToken();
            $response = $this->send($path);
        }

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new DirectoryException('http_'.$response->status(), $response->status());
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new DirectoryException('malformed_response', $response->status());
        }

        return $json;
    }

    private function send(string $path): Response
    {
        try {
            return Http::withToken($this->accessToken())
                ->acceptJson()
                ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->get($this->apiBaseUrl().$path);
        } catch (ConnectionException $e) {
            throw new DirectoryException('connection_failed', null, $e);
        }
    }

    private function accessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        /** @var string|null $cached */
        $cached = Cache::get($this->tokenCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $this->accessToken = $cached;
        }

        return $this->accessToken = $this->requestToken();
    }

    private function requestToken(): string
    {
        $clientId = (string) $this->setting('client_id');
        $clientSecret = (string) $this->setting('client_secret');

        if ($clientId === '' || $clientSecret === '') {
            throw new DirectoryException('credentials_missing');
        }

        /** @var list<string> $scopes */
        $scopes = (array) $this->setting('scopes', []);

        try {
            $response = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->acceptJson()
                ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->post($this->authBaseUrl().'/oauth2/token', [
                    'grant_type' => 'client_credentials',
                    'scope' => implode(' ', $scopes),
                ]);
        } catch (ConnectionException $e) {
            throw new DirectoryException('token_connection_failed', null, $e);
        }

        if (! $response->successful()) {
            throw new DirectoryException('token_request_failed', $response->status());
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new DirectoryException('token_missing');
        }

        $ttl = (int) $response->json('expires_in', 0) - self::TOKEN_EXPIRY_MARGIN_SECONDS;

        if ($ttl > 0) {
            Cache::put($this->tokenCacheKey(), $token, $ttl);
        }

        return $token;
    }

    /**
     * @param  array<array-key, mixed>|null  $data
     * @param  callable(array<string, mixed>): T  $map
     * @return list<T>
     *
     * @template T
     */
    private function mapList(?array $data, callable $map): array
    {
        if ($data === null) {
            return [];
        }

        $items = [];

        foreach ($data as $row) {
            if (is_array($row) && ! empty($row['id'])) {
                $items[] = $map($row);
            }
        }

        return $items;
    }

    private function apiBaseUrl(): string
    {
        return rtrim((string) $this->setting('api_url'), '/');
    }

    private function authBaseUrl(): string
    {
        return rtrim((string) $this->setting('auth_url'), '/');
    }

    /**
     * Keyed by client_id, so rotated credentials cannot serve a token minted
     * for the previous client.
     */
    private function tokenCacheKey(): string
    {
        return 'idp_idm_token:'.md5((string) $this->setting('client_id'));
    }
}
