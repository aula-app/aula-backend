<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Covers the IdP-initiated (third-party initiated login) entry point that
 * Eduplaces' marketplace launcher hits. Tenant resolution happens client-
 * side: this endpoint just bounces to the frontend with `via=eduplaces`,
 * the frontend collects the instance code, and the user is sent through
 * the regular tenant-scoped /sso/initiate flow.
 */
class SsoIdpInitiatedTest extends TestCase
{
    private const ALLOWED_ISS = 'https://auth.sandbox.eduplaces.dev';

    private const FRONTEND_URL = 'http://localhost:3000';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.frontend_url'                   => self::FRONTEND_URL,
            'services.eduplaces.allowed_issuers' => [self::ALLOWED_ISS, 'https://auth.eduplaces.io'],
        ]);
    }

    public function test_rejects_when_iss_is_missing(): void
    {
        $response = $this->getJson('/api/v2/auth/sso/idp-initiated');

        $response->assertStatus(400)->assertJson(['error' => 'invalid_issuer']);
    }

    public function test_rejects_when_iss_is_not_allowlisted(): void
    {
        $response = $this->getJson('/api/v2/auth/sso/idp-initiated?iss=https://attacker.example');

        $response->assertStatus(400)->assertJson(['error' => 'invalid_issuer']);
    }

    public function test_bounces_to_frontend_with_via_and_preserves_login_hint(): void
    {
        $hint = 'opaque-eduplaces-hint';

        $response = $this->get('/api/v2/auth/sso/idp-initiated?iss='.urlencode(self::ALLOWED_ISS).'&login_hint='.urlencode($hint));

        $response->assertRedirect();
        $location = $response->headers->get('Location');

        $this->assertStringStartsWith(self::FRONTEND_URL.'/login?', $location);
        $this->assertStringContainsString('via=eduplaces', $location);
        $this->assertStringContainsString('login_hint='.urlencode($hint), $location);
    }

    public function test_omits_login_hint_when_absent(): void
    {
        $response = $this->get('/api/v2/auth/sso/idp-initiated?iss='.urlencode(self::ALLOWED_ISS));

        $response->assertRedirect();
        $location = $response->headers->get('Location');

        $this->assertStringContainsString('via=eduplaces', $location);
        $this->assertStringNotContainsString('login_hint=', $location);
    }
}
