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
 * Webhook payloads carry an entity id and the names of the properties that
 * changed, never the values, so every event is followed by a read-back through
 * this client. Access is granted by an OAuth2 client-credentials token
 * (see https://developer.eduplaces.de/authentication/client-credentials).
 *
 * A 404 is not an error here: entities disappear between the webhook firing and
 * us handling it, so lookups return null and let the caller decide.
 */
final class EduplacesDirectory implements IdentityDirectory
{
    private const string API_PREFIX = '/idm/ep/v1';

    /**
     * Margin subtracted from the token lifetime so a token cannot expire in
     * flight between our expiry check and the API receiving the request.
     */
    private const int TOKEN_EXPIRY_MARGIN_SECONDS = 60;

    private const int REQUEST_TIMEOUT_SECONDS = 15;

    /**
     * Process-local copy of the cached token, so a worker running many jobs
     * does not hit the cache once per job.
     *
     * The persistent cache below is deliberately *not* read through
     * `tenancy()->central()`. Doing so ends tenancy mid-call, which purges the
     * dynamically-created `tenant` database connection while a database-backed
     * cache store is still holding it — "Database connection [tenant] not
     * configured". One token per tenant prefix is a cheap price for not
     * unwinding tenancy underneath an active store.
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
     * People who can actually sign in. Overlaps with schoolPeople() but is not
     * a subset of it: a user may exist without being configured as a person.
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
     * Every group, read in full.
     *
     * The school listing gives only id and name; the per-group call adds the
     * member list, which is the only place Eduplaces exposes real names when an
     * app holds pseudonymous entitlements. Worth one call per group.
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
     * Everyone at the school.
     *
     * Eduplaces splits this across two overlapping endpoints: `/people` (adds
     * sourceSystemIdentifier, needs a scope an app may not hold) and `/users`
     * (adds status and a pseudonym). Both are read and merged by id so callers
     * see one list; a refusal on `/people` is logged and stepped over.
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
     * A single person, merging the same two views as users().
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
            // Token rejected: drop it and give the request one more chance with
            // a freshly minted one.
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
     * Keyed by client id so rotating credentials cannot serve a token minted
     * for the previous client.
     */
    private function tokenCacheKey(): string
    {
        return 'idp_idm_token:'.md5((string) $this->setting('client_id'));
    }
}
