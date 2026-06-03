<?php

namespace Tests\Support;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;

/**
 * Generates an RSA keypair once per test, exposes helpers to sign id_tokens
 * with the private key, build a matching JWKS document, and fake the JWKS
 * endpoint so the production code's signature verification has a real key
 * to validate against.
 */
trait SignsIdTokens
{
    private ?string $rsaPrivatePem = null;

    private ?array $rsaPublicJwk = null;

    protected function bootIdTokenSigner(string $kid = 'test-key-1'): void
    {
        if ($this->rsaPrivatePem !== null) {
            return;
        }

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($resource, $privatePem);
        $details = openssl_pkey_get_details($resource);

        $this->rsaPrivatePem = $privatePem;
        $this->rsaPublicJwk  = [
            'kid' => $kid,
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'n'   => $this->base64UrlEncode($details['rsa']['n']),
            'e'   => $this->base64UrlEncode($details['rsa']['e']),
        ];
    }

    /**
     * Sign and return a JWT with the test key. Pass kid='unknown' to simulate
     * a token signed by a key the JWKS endpoint doesn't expose.
     */
    protected function signIdToken(array $claims, string $kid = 'test-key-1'): string
    {
        $this->bootIdTokenSigner();

        return JWT::encode($claims, $this->rsaPrivatePem, 'RS256', $kid);
    }

    protected function jwksDocument(): array
    {
        $this->bootIdTokenSigner();

        return ['keys' => [$this->rsaPublicJwk]];
    }

    /**
     * Register an Http::fake() route for Keycloak's JWKS endpoint so
     * IdTokenVerifier can fetch it and parse our public key.
     */
    protected function fakeJwksEndpoint(): void
    {
        Http::fake([
            '*/protocol/openid-connect/certs' => Http::response($this->jwksDocument(), 200),
        ]);
    }

    private function base64UrlEncode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
