<?php

declare(strict_types=1);

namespace App\Services\Eduplaces;

use App\Services\Eduplaces\Exceptions\EduplacesAuthFailed;
use App\Services\Eduplaces\Exceptions\EduplacesConfigMissing;
use App\Services\Eduplaces\Exceptions\EduplacesUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class EduplacesClient
{
    private const TOKEN_CACHE_KEY = 'eduplaces:client_credentials_token';

    private const SCHOOLS_READ_SCOPE = 'urn:eduplaces:idm:v1:schools:read';

    private const USERS_READ_SCOPE = 'urn:eduplaces:idm:v1:users:read';

    /**
     * Resolve the Eduplaces school UUID that the given user (sub) belongs to.
     *
     * Strategy: iterate the schools that have sync enabled for this app and
     * check each school's user list for the sub. Eduplaces' IDM model does
     * not expose a school id directly on the user object, so this is the
     * documented path. Result is cached per-sub to keep the callback cheap.
     *
     * Returns null when no synced school claims the user — caller decides
     * whether that's a hard error (no aula tenant for this school) or a
     * soft one (school admin hasn't enabled sync yet).
     */
    public function resolveSchoolIdForSub(string $sub): ?string
    {
        $cacheKey = "eduplaces:sub_to_school:{$sub}";

        $cached = Cache::get($cacheKey);
        if (is_string($cached)) {
            return $cached;
        }

        $token = $this->getAccessToken();

        foreach ($this->listSchoolIds($token) as $schoolId) {
            if ($this->schoolContainsSub($token, $schoolId, $sub)) {
                Cache::put($cacheKey, $schoolId, now()->addMinutes(15));

                return $schoolId;
            }
        }

        return null;
    }

    /**
     * Fetch (and cache) a client-credentials access token covering the
     * scopes this client needs.
     */
    protected function getAccessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $clientId = (string) config('services.eduplaces.client_id');
        $clientSecret = (string) config('services.eduplaces.client_secret');

        if ($clientId === '' || $clientSecret === '') {
            throw new EduplacesConfigMissing('Eduplaces client_id/client_secret not configured.');
        }

        $authUrl = rtrim((string) config('services.eduplaces.auth_url'), '/');

        try {
            $response = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->post("{$authUrl}/oauth2/token", [
                    'grant_type' => 'client_credentials',
                    'scope' => implode(' ', [self::SCHOOLS_READ_SCOPE, self::USERS_READ_SCOPE]),
                ]);
        } catch (ConnectionException $e) {
            throw new EduplacesUnavailable('Cannot reach Eduplaces auth server: '.$e->getMessage(), 0, $e);
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new EduplacesAuthFailed('Eduplaces rejected client credentials.');
        }

        if (! $response->ok()) {
            throw new EduplacesUnavailable('Eduplaces token endpoint returned HTTP '.$response->status());
        }

        $token = (string) $response->json('access_token', '');
        $expiresIn = (int) $response->json('expires_in', 0);

        if ($token === '' || $expiresIn <= 0) {
            throw new EduplacesUnavailable('Eduplaces token response missing access_token or expires_in.');
        }

        // Refresh 60s before expiry to avoid mid-flight 401s.
        Cache::put(self::TOKEN_CACHE_KEY, $token, max(60, $expiresIn - 60));

        return $token;
    }

    /**
     * @return iterable<string>
     */
    protected function listSchoolIds(string $token): iterable
    {
        $response = $this->idmGet($token, '/schools');

        $payload = $response->json();
        $entries = is_array($payload) ? ($payload['data'] ?? $payload) : [];

        if (! is_array($entries)) {
            return [];
        }

        foreach ($entries as $entry) {
            if (is_array($entry) && isset($entry['id']) && is_string($entry['id'])) {
                yield $entry['id'];
            }
        }
    }

    protected function schoolContainsSub(string $token, string $schoolId, string $sub): bool
    {
        $response = $this->idmGet($token, "/schools/{$schoolId}/users");

        if ($response->status() === 404) {
            return false;
        }

        $payload = $response->json();
        $entries = is_array($payload) ? ($payload['data'] ?? $payload) : [];

        if (! is_array($entries)) {
            return false;
        }

        foreach ($entries as $entry) {
            if (is_array($entry) && ($entry['id'] ?? null) === $sub) {
                return true;
            }
        }

        return false;
    }

    protected function idmGet(string $token, string $path): Response
    {
        $apiUrl = rtrim((string) config('services.eduplaces.api_url'), '/');

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->get($apiUrl.$path);
        } catch (ConnectionException $e) {
            throw new EduplacesUnavailable('Cannot reach Eduplaces API: '.$e->getMessage(), 0, $e);
        }

        if ($response->status() === 401) {
            // Token rotation — clear the cache so the next call re-auths.
            Cache::forget(self::TOKEN_CACHE_KEY);
            throw new EduplacesAuthFailed('Eduplaces returned 401 on '.$path);
        }

        if ($response->status() >= 500) {
            throw new EduplacesUnavailable('Eduplaces returned HTTP '.$response->status().' on '.$path);
        }

        return $response;
    }
}
