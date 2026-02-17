<?php

namespace App\Services;

use App\Models\LegacyUser;

class LegacyJwtService
{
    /**
     * Generate a JWT token for the given user.
     * Matches the legacy JWT::gen_jwt() implementation exactly.
     */
    public function generateToken(LegacyUser $user): string
    {
        $jwtKey = $this->getJwtKey();

        $header = [
            'alg' => 'HS512',
            'typ' => 'JWT',
        ];
        $headerEncoded = $this->base64UrlEncode(json_encode($header));

        $payload = [
            'exp' => 0,
            'user_id' => $user->id,
            'user_hash' => $user->hash_id,
            'user_level' => $user->userlevel,
            'roles' => json_decode($user->roles ?? '[]'),
            'temp_pw' => !empty($user->temp_pw),
        ];

        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        $signature = $this->base64UrlEncode(
            hash_hmac('sha512', "{$headerEncoded}.{$payloadEncoded}", $jwtKey, true)
        );

        return "{$headerEncoded}.{$payloadEncoded}.{$signature}";
    }

    /**
     * Validate a JWT token.
     * Matches the legacy JWT::check_jwt() implementation.
     *
     * @return array{success: bool, error?: string, payload?: object}
     */
    public function validateToken(string $token): array
    {
        $tokenParts = explode('.', $token);

        if (count($tokenParts) !== 3) {
            return ['success' => false, 'error' => 'invalid_token_format'];
        }

        [$headerEncoded, $payloadEncoded, $signatureProvided] = $tokenParts;

        // Decode header and payload
        $header = base64_decode($headerEncoded);
        $payload = base64_decode($payloadEncoded);

        if ($header === false || $payload === false) {
            return ['success' => false, 'error' => 'invalid_token_encoding'];
        }

        // Verify signature
        $jwtKey = $this->getJwtKey();

        // Re-encode to match legacy format (handles padding differences)
        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode($payload);

        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha512', "{$base64UrlHeader}.{$base64UrlPayload}", $jwtKey, true)
        );

        if ($signatureProvided !== $expectedSignature) {
            return ['success' => false, 'error' => 'invalid_signature'];
        }

        // Parse payload
        $payloadData = json_decode($payload);

        if ($payloadData === null) {
            return ['success' => false, 'error' => 'invalid_payload'];
        }

        // Check expiration (if set and > 0)
        if (isset($payloadData->exp) && $payloadData->exp > 0 && time() > $payloadData->exp) {
            return ['success' => false, 'error' => 'token_expired'];
        }

        return [
            'success' => true,
            'payload' => $payloadData,
        ];
    }

    /**
     * Get the payload from a token without full validation.
     * Useful for extracting user info before database checks.
     */
    public function getPayload(string $token): ?object
    {
        $tokenParts = explode('.', $token);

        if (count($tokenParts) !== 3) {
            return null;
        }

        $payload = base64_decode($tokenParts[1]);

        if ($payload === false) {
            return null;
        }

        return json_decode($payload);
    }

    /**
     * Extract the Bearer token from an Authorization header value.
     */
    public function extractBearerToken(?string $authHeader): ?string
    {
        if ($authHeader === null) {
            return null;
        }

        if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get the JWT key for the current tenant.
     */
    protected function getJwtKey(): string
    {
        // Try to get JWT key from current tenant
        $tenant = tenant();

        if ($tenant && !empty($tenant->jwt_key)) {
            return $tenant->jwt_key;
        }

        // Fallback to environment variable
        $envKey = env('JWT_KEY');

        if (!empty($envKey)) {
            return $envKey;
        }

        // Final fallback (should be changed in production)
        return 'default_jwt_key_change_me';
    }

    /**
     * Base64 URL encode a string.
     * Matches the legacy base64_url_encode() function exactly.
     */
    protected function base64UrlEncode(string $text): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));
    }
}
