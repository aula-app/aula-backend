<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\LegacyUser;
use App\Models\Tenant;

/**
 * Verifies the HS512 JWT that BE.v1 issues, so a frontend still logging in at
 * /api/controllers/login.php reaches the v2 SSO and IDP routes.
 *
 * Transitional. Delete once the frontend obtains its tokens from
 * /api/v2/oauth/token; see LegacyOrPassportGuard.
 *
 * Verification only. v2 issues Passport tokens and must not mint these.
 */
class LegacyJwtVerifier
{
    private const ALGORITHM = 'HS512';

    /**
     * The user a legacy bearer token authenticates, or null.
     *
     * Takes the token rather than the request: Passport's TokenGuard blanks
     * the Authorization header once it has run (TokenGuard.php:178), so a
     * caller trying Passport first has to capture it beforehand.
     */
    public function resolve(?string $token): ?LegacyUser
    {
        if ($token === null || $token === '') {
            return null;
        }

        $payload = $this->verify($token);

        if ($payload === null) {
            return null;
        }

        // The sole identifying claim. Eloquent turns `= null` into `IS NULL`
        // and hash_id is nullable, so a blank claim would authenticate as an
        // arbitrary hash-less user.
        $userHash = $payload['user_hash'] ?? null;

        if (! is_string($userHash) || $userHash === '') {
            return null;
        }

        $user = LegacyUser::where('hash_id', $userHash)->first();

        if ($user === null || ! $user->isActive()) {
            return null;
        }

        // The v1 flag meaning "this session must re-issue its token first".
        if ((bool) $user->refresh_token) {
            return null;
        }

        return $user;
    }

    /**
     * The decoded payload of a token signed with this tenant's key, or null.
     *
     * @return array<string, mixed>|null
     */
    private function verify(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signature] = $parts;

        $key = $this->tenantKey();

        if ($key === null) {
            return null;
        }

        $header = json_decode((string) base64_decode($headerEncoded, true), true);

        // Pinned rather than read from the token, so the algorithm is never
        // chosen by the caller.
        if (! is_array($header) || ($header['alg'] ?? null) !== self::ALGORITHM) {
            return null;
        }

        $expected = $this->base64UrlEncode(
            hash_hmac('sha512', "{$headerEncoded}.{$payloadEncoded}", $key, true)
        );

        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode((string) base64_decode($payloadEncoded, true), true);

        if (! is_array($payload)) {
            return null;
        }

        // v1 issues exp = 0 for a token that does not expire.
        $exp = $payload['exp'] ?? 0;

        if (is_numeric($exp) && $exp > 0 && time() > $exp) {
            return null;
        }

        return $payload;
    }

    private function tenantKey(): ?string
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        if ($tenant === null || empty($tenant->jwt_key)) {
            return null;
        }

        return (string) $tenant->jwt_key;
    }

    private function base64UrlEncode(string $value): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($value));
    }
}
