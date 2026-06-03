<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\IdTokenVerification\IdTokenVerificationException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Verifies a Keycloak-issued id_token end-to-end:
 *   1. JWT structure
 *   2. RS256 signature against a JWKS key with matching kid
 *   3. exp / nbf / iat (handled by Firebase JWT with leeway)
 *   4. iss matches our Keycloak realm
 *   5. aud contains our client_id
 *   6. azp, if present, equals our client_id
 *
 * The JWKS document is fetched on first use and cached. On a kid miss the
 * cache is bypassed once to handle Keycloak key rotation without forcing a
 * restart.
 */
class IdTokenVerifier
{
    private const JWKS_TTL_SECONDS = 3600;
    /**
     * Default clock-skew tolerance in seconds. 5 minutes matches the conservative
     * end of common OIDC defaults (Auth0/Okta/Cognito range from 60 to 300 seconds)
     * and absorbs typical NTP drift without exposing meaningful replay surface
     * for short-lived id_tokens. Override per-environment via SSO_CLOCK_SKEW_SECONDS.
     */
    private const DEFAULT_CLOCK_SKEW_SECONDS = 300;

    /**
     * @return array<string, mixed> verified claims
     */
    public function verify(string $idToken): array
    {
        $header = $this->parseHeader($idToken);
        $keys   = $this->keysForKid($header['kid']);

        JWT::$leeway = (int) config('services.keycloak.clock_skew_seconds', self::DEFAULT_CLOCK_SKEW_SECONDS);

        try {
            $decoded = (array) JWT::decode($idToken, $keys);
        } catch (ExpiredException $e) {
            throw new IdTokenVerificationException('expired', $e);
        } catch (BeforeValidException $e) {
            throw new IdTokenVerificationException('not_yet_valid', $e);
        } catch (SignatureInvalidException $e) {
            throw new IdTokenVerificationException('signature_invalid', $e);
        } catch (Throwable $e) {
            throw new IdTokenVerificationException('malformed', $e);
        }

        $this->verifyIssuer($decoded);
        $this->verifyAudience($decoded);
        $this->verifyAzp($decoded);

        return $decoded;
    }

    /**
     * @return array{kid: string}
     */
    private function parseHeader(string $idToken): array
    {
        if (substr_count($idToken, '.') !== 2) {
            throw new IdTokenVerificationException('malformed');
        }

        $parts   = explode('.', $idToken);
        $decoded = $this->base64UrlDecode($parts[0]);
        $header  = $decoded === '' ? null : json_decode($decoded, true);

        if (! is_array($header) || empty($header['kid']) || ! is_string($header['kid'])) {
            throw new IdTokenVerificationException('malformed');
        }

        return ['kid' => $header['kid']];
    }

    /**
     * @return array<string, \Firebase\JWT\Key>
     */
    private function keysForKid(string $kid): array
    {
        $keys = $this->parseKeySet($this->fetchJwks(useCache: true));

        if (isset($keys[$kid])) {
            return $keys;
        }

        // Possible key rotation — refetch once, then give up.
        $keys = $this->parseKeySet($this->fetchJwks(useCache: false));

        if (! isset($keys[$kid])) {
            throw new IdTokenVerificationException('kid_unknown');
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $jwks
     * @return array<string, \Firebase\JWT\Key>
     */
    private function parseKeySet(array $jwks): array
    {
        try {
            return JWK::parseKeySet($jwks);
        } catch (Throwable $e) {
            throw new IdTokenVerificationException('jwks_invalid', $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchJwks(bool $useCache): array
    {
        if (! $useCache) {
            Cache::forget($this->jwksCacheKey());
        }

        return Cache::remember($this->jwksCacheKey(), self::JWKS_TTL_SECONDS, function (): array {
            $response = Http::get($this->jwksUrl());

            if (! $response->ok()) {
                throw new IdTokenVerificationException('jwks_unavailable');
            }

            $json = $response->json();

            if (! is_array($json) || ! isset($json['keys'])) {
                throw new IdTokenVerificationException('jwks_invalid');
            }

            return $json;
        });
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function verifyIssuer(array $claims): void
    {
        if (($claims['iss'] ?? null) !== $this->expectedIssuer()) {
            throw new IdTokenVerificationException('issuer_mismatch');
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function verifyAudience(array $claims): void
    {
        $clientId = $this->clientId();
        $aud      = $claims['aud'] ?? null;

        $ok = is_string($aud)
            ? $aud === $clientId
            : (is_array($aud) && in_array($clientId, $aud, true));

        if (! $ok) {
            throw new IdTokenVerificationException('audience_mismatch');
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function verifyAzp(array $claims): void
    {
        if (! array_key_exists('azp', $claims)) {
            return;
        }

        if ($claims['azp'] !== $this->clientId()) {
            throw new IdTokenVerificationException('azp_mismatch');
        }
    }

    private function expectedIssuer(): string
    {
        return rtrim((string) config('services.keycloak.base_url'), '/') . '/realms/' . $this->realm();
    }

    private function jwksUrl(): string
    {
        return rtrim((string) config('services.keycloak.base_url'), '/') . '/realms/' . $this->realm() . '/protocol/openid-connect/certs';
    }

    private function jwksCacheKey(): string
    {
        return 'oidc_jwks:' . $this->realm();
    }

    private function realm(): string
    {
        return (string) config('services.keycloak.realms', 'master');
    }

    private function clientId(): string
    {
        return (string) config('services.keycloak.client_id');
    }

    private function base64UrlDecode(string $data): string
    {
        $padded = str_pad($data, strlen($data) + (4 - strlen($data) % 4) % 4, '=');
        $result = base64_decode(strtr($padded, '-_', '+/'), true);

        return $result === false ? '' : $result;
    }
}
