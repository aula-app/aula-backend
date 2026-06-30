<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LegacyUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Tests\Concerns\CreatesTestTenant;
use Tests\Support\SignsIdTokens;
use Tests\TestCase;

/**
 * Covers the IdP-initiated (OIDC third-party initiated login) entry point
 * that Eduplaces' marketplace launcher hits. The callback resolves the
 * aula tenant from the upstream id_token's `school` claim and maps it to
 * `tenants.eduplaces_school_id`.
 */
class SsoIdpInitiatedTest extends TestCase
{
    use CreatesTestTenant;
    use SignsIdTokens;

    private const INSTANCE_CODE = 'TEST001';

    private const KEYCLOAK_BASE = 'https://sso.test.local';

    private const KEYCLOAK_REALM = 'aula-test';

    private const KEYCLOAK_CLIENT_ID = 'aula-backend-test';

    private const EDUPLACES_AUTH = 'https://auth.sandbox.eduplaces.dev';

    private const EDUPLACES_IDP_ALIAS = 'eduplaces';

    private const EDUPLACES_SCHOOL = 'school-uuid-aaa';

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        self::$testTenant->update([
            'sso_enabled'                => true,
            'sso_provider'               => self::EDUPLACES_IDP_ALIAS,
            'sso_force_logout'           => false,
            'sso_require_email_verified' => false,
            'eduplaces_school_id'        => self::EDUPLACES_SCHOOL,
        ]);

        config([
            'services.keycloak.base_url'         => self::KEYCLOAK_BASE,
            'services.keycloak.realms'           => self::KEYCLOAK_REALM,
            'services.keycloak.client_id'        => self::KEYCLOAK_CLIENT_ID,
            'services.eduplaces.idp_alias'       => self::EDUPLACES_IDP_ALIAS,
            'services.eduplaces.allowed_issuers' => [self::EDUPLACES_AUTH, 'https://auth.eduplaces.io'],
        ]);

        Cache::flush();
        self::$testTenant->run(fn () => Cache::flush());
        $this->fakeJwksEndpoint();
    }

    protected function tearDown(): void
    {
        self::$testTenant->run(fn () => LegacyUser::where('email', 'like', 'idp_%@test.example')->delete());
        parent::tearDown();
    }

    // =========================================================
    // /sso/idp-initiated
    // =========================================================

    public function test_idp_initiated_rejects_missing_iss(): void
    {
        $response = $this->getJson('/api/v2/auth/sso/idp-initiated');

        $response->assertStatus(400)->assertJson(['error' => 'invalid_issuer']);
    }

    public function test_idp_initiated_rejects_disallowed_iss(): void
    {
        $response = $this->getJson('/api/v2/auth/sso/idp-initiated?iss=https://attacker.example');

        $response->assertStatus(400)->assertJson(['error' => 'invalid_issuer']);
    }

    public function test_idp_initiated_redirects_to_keycloak_with_idp_hint_and_login_hint(): void
    {
        $capturedParams = [];

        $provider = \Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('with')->andReturnUsing(function (array $params) use (&$capturedParams, $provider) {
            $capturedParams = $params;
            return $provider;
        });
        $provider->shouldReceive('redirect')->andReturn(new RedirectResponse('https://sso.test.local/realms/aula-test/protocol/openid-connect/auth'));

        Socialite::shouldReceive('driver')->with('keycloak')->andReturn($provider);

        $hint = 'opaque-eduplaces-hint';

        $response = $this->get('/api/v2/auth/sso/idp-initiated?iss='.urlencode(self::EDUPLACES_AUTH).'&login_hint='.urlencode($hint));

        $response->assertRedirect();
        $this->assertEquals(self::EDUPLACES_IDP_ALIAS, $capturedParams['kc_idp_hint']);
        $this->assertEquals($hint, $capturedParams['login_hint']);
        $this->assertNotEmpty($capturedParams['state']);
    }

    public function test_idp_initiated_omits_login_hint_when_absent(): void
    {
        $capturedParams = [];

        $provider = \Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('with')->andReturnUsing(function (array $params) use (&$capturedParams, $provider) {
            $capturedParams = $params;
            return $provider;
        });
        $provider->shouldReceive('redirect')->andReturn(new RedirectResponse('https://sso.test.local/x'));

        Socialite::shouldReceive('driver')->with('keycloak')->andReturn($provider);

        $this->get('/api/v2/auth/sso/idp-initiated?iss='.urlencode(self::EDUPLACES_AUTH));

        $this->assertArrayNotHasKey('login_hint', $capturedParams);
    }

    // =========================================================
    // callback — idp-initiated branch
    // =========================================================

    public function test_callback_resolves_tenant_via_school_claim_and_authenticates(): void
    {
        $this->fakeBrokerUpstreamIdToken(['sub' => 'eduplaces-sub-aaa', 'school' => self::EDUPLACES_SCHOOL, 'iss' => self::EDUPLACES_AUTH]);
        $this->mockSocialiteCallback('keycloak-sub-001', 'idp_resolved@test.example', 'IdP Resolved', 'idpresolved');

        $state = $this->buildIdpInitiatedState();
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('/oauth-login/', $response->headers->get('Location'));

        self::$testTenant->run(function () {
            $user = LegacyUser::where('sso_sub', 'keycloak-sub-001')->first();
            $this->assertNotNull($user, 'user should have been provisioned in the resolved tenant');
        });
    }

    public function test_callback_rejects_when_school_claim_is_missing(): void
    {
        $this->fakeBrokerUpstreamIdToken(['sub' => 'eduplaces-sub-nos', 'iss' => self::EDUPLACES_AUTH]);
        $this->mockSocialiteCallback('keycloak-sub-noschool', 'idp_noschool@test.example', 'N', 'n');

        $state = $this->buildIdpInitiatedState();
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('sso_error=eduplaces_school_missing', $response->headers->get('Location'));
    }

    public function test_callback_rejects_when_no_aula_tenant_matches_school(): void
    {
        $this->fakeBrokerUpstreamIdToken(['sub' => 'eduplaces-sub-stray', 'school' => 'school-uuid-unknown', 'iss' => self::EDUPLACES_AUTH]);
        $this->mockSocialiteCallback('keycloak-sub-stray', 'idp_stray@test.example', 'S', 's');

        $state = $this->buildIdpInitiatedState();
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('sso_error=school_not_provisioned', $response->headers->get('Location'));
    }

    public function test_callback_rejects_when_resolved_tenant_has_sso_disabled(): void
    {
        self::$testTenant->update(['sso_enabled' => false]);

        $this->fakeBrokerUpstreamIdToken(['sub' => 'eduplaces-sub-off', 'school' => self::EDUPLACES_SCHOOL, 'iss' => self::EDUPLACES_AUTH]);
        $this->mockSocialiteCallback('keycloak-sub-disabled', 'idp_disabled@test.example', 'D', 'd');

        $state = $this->buildIdpInitiatedState();
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('sso_error=sso_disabled', $response->headers->get('Location'));
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function fakeBrokerUpstreamIdToken(array $claims): void
    {
        Http::fake([
            self::KEYCLOAK_BASE.'/realms/'.self::KEYCLOAK_REALM.'/broker/'.self::EDUPLACES_IDP_ALIAS.'/token' => Http::response([
                'id_token' => $this->makeUnverifiedJwt($claims),
            ], 200),
        ]);
    }

    private function buildIdpInitiatedState(): string
    {
        $payload = base64_encode((string) json_encode([
            'instance_code' => '__IDP_INITIATED_EDUPLACES__',
            'nonce'         => 'testnonce',
        ]));
        $signature = hash_hmac('sha256', $payload, (string) config('app.key'));
        return $payload.'.'.$signature;
    }

    /**
     * Produce a JWT-shaped string whose payload section is the supplied claims.
     * The controller's decodeIdTokenPayload() only base64-decodes; it does not
     * verify a signature on the upstream broker token (Keycloak is the trust
     * boundary), so a stub header/signature is fine.
     */
    private function makeUnverifiedJwt(array $claims): string
    {
        $header = rtrim(strtr(base64_encode((string) json_encode(['alg' => 'RS256'])), '+/', '-_'), '=');
        $body   = rtrim(strtr(base64_encode((string) json_encode($claims)), '+/', '-_'), '=');
        return "{$header}.{$body}.sig";
    }

    private function mockSocialiteCallback(string $keycloakSub, string $email, string $name, string $nickname): void
    {
        $idToken = $this->signIdToken([
            'iss'            => self::KEYCLOAK_BASE.'/realms/'.self::KEYCLOAK_REALM,
            'aud'            => self::KEYCLOAK_CLIENT_ID,
            'azp'            => self::KEYCLOAK_CLIENT_ID,
            'iat'            => time() - 30,
            'exp'            => time() + 600,
            'sub'            => $keycloakSub,
            'email'          => $email,
            'email_verified' => true,
        ]);

        $socialiteUser = \Mockery::mock(\Laravel\Socialite\Two\User::class);
        $socialiteUser->token = 'kc-access-token';
        $socialiteUser->refreshToken = 'kc-refresh-token';
        $socialiteUser->accessTokenResponseBody = ['id_token' => $idToken];
        $socialiteUser->shouldReceive('getId')->andReturn($keycloakSub);
        $socialiteUser->shouldReceive('getEmail')->andReturn($email);
        $socialiteUser->shouldReceive('getName')->andReturn($name);
        $socialiteUser->shouldReceive('getNickname')->andReturn($nickname);

        $provider = \Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('keycloak')->andReturn($provider);
    }
}
