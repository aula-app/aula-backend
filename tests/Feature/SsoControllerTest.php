<?php

namespace Tests\Feature;

use App\Enums\UserLevel;
use App\Enums\UserStatus;
use App\Models\LegacyUser;
use App\Services\LegacyJwtService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Manager\OAuth2\User;
use Tests\Concerns\CreatesTestTenant;
use Tests\Support\SignsIdTokens;
use Tests\TestCase;

class SsoControllerTest extends TestCase
{
    use CreatesTestTenant;
    use SignsIdTokens;

    private const INSTANCE_CODE = 'TEST001';

    private const KEYCLOAK_BASE = 'https://sso.test.local';

    private const KEYCLOAK_REALM = 'aula-test';

    private const KEYCLOAK_CLIENT_ID = 'aula-backend-test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        self::$testTenant->update([
            'sso_enabled' => true,
            'sso_provider' => 'mock-iserv',
            'sso_force_logout' => false,
            'sso_require_email_verified' => true,
            // Explicit: TEST001 is shared with Eduplaces test classes, and this
            // class asserts the behaviour of a tenant that is not on Eduplaces.
            'idp_school_id' => null,
        ]);

        config([
            'services.keycloak.base_url' => self::KEYCLOAK_BASE,
            'services.keycloak.realms' => self::KEYCLOAK_REALM,
            'services.keycloak.client_id' => self::KEYCLOAK_CLIENT_ID,
        ]);

        // The callback's id_token verification fetches JWKS through the tenant-scoped
        // cache, so the fake and the flush must run inside tenant context.
        self::$testTenant->run(function () {
            Cache::flush();
        });
        $this->fakeJwksEndpoint();
        // Pre-register the broker stub so callbacks that reach the post-link save
        // path do not make real HTTP calls; tests that care about this URL override.
        Http::fake([
            '*/broker/*/token' => Http::response(['id_token' => 'idp.token.test'], 200),
        ]);
    }

    protected function tearDown(): void
    {
        self::$testTenant->run(fn () => LegacyUser::where('email', 'like', 'sso_%@test.example')->delete());
        parent::tearDown();
    }

    // =========================================================
    // initiate
    // =========================================================

    public function test_initiate_returns_keycloak_url(): void
    {
        $targetUrl = 'https://sso.aula.de/auth/realms/aula/protocol/openid-connect/auth?kc_idp_hint=mock-iserv';

        $provider = \Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('with')->andReturnSelf();
        $provider->shouldReceive('redirect')->andReturn(new RedirectResponse($targetUrl));

        Socialite::shouldReceive('driver')->with('keycloak')->andReturn($provider);

        $response = $this->getJson('/api/v2/auth/sso/initiate', ['aula-instance-code' => self::INSTANCE_CODE]);

        $response->assertOk()->assertJsonStructure(['url']);
        $this->assertEquals($targetUrl, $response->json('url'));
    }

    public function test_initiate_adds_prompt_login_when_force_login_requested(): void
    {
        $capturedParams = [];

        $provider = \Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('with')->andReturnUsing(function (array $params) use (&$capturedParams, $provider) {
            $this->assertIsArray($params);
            $this->assertArrayHasKey('prompt', $params);
            $capturedParams = $params;

            return $provider;
        });
        $provider->shouldReceive('redirect')->andReturn(new RedirectResponse('https://sso.example'));

        Socialite::shouldReceive('driver')->with('keycloak')->andReturn($provider);

        $this->getJson('/api/v2/auth/sso/initiate?force_login=true', ['aula-instance-code' => self::INSTANCE_CODE]);

        $this->assertEquals('login', $capturedParams['prompt']);
    }

    // =========================================================
    // sso_enabled
    // =========================================================

    public function test_initiate_refuses_a_tenant_with_sso_disabled(): void
    {
        self::$testTenant->update(['sso_enabled' => false]);

        $this->getJson('/api/v2/auth/sso/initiate', ['aula-instance-code' => self::INSTANCE_CODE])
            ->assertForbidden()
            ->assertJsonPath('error', 'sso_disabled');
    }

    /**
     * A state signed while SSO was still on outlives the flag, and nothing
     * forces a caller through initiate() first, so the callback has to refuse
     * on its own rather than provisioning an account.
     */
    public function test_callback_refuses_a_tenant_with_sso_disabled(): void
    {
        self::$testTenant->update(['sso_enabled' => false]);

        $this->mockSocialiteCallback('sub-ssooff-001', 'sso_ssooff@test.example', 'Disabled Tenant', 'ssooff');

        $state = $this->buildState(self::INSTANCE_CODE);
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('sso_error=sso_disabled', (string) $response->headers->get('Location'));

        self::$testTenant->run(function () {
            $this->assertNull(
                LegacyUser::where('sso_sub', 'sub-ssooff-001')->first(),
                'a tenant with SSO disabled must not gain an account from an SSO login',
            );
        });
    }

    // =========================================================
    // native app clients
    // =========================================================

    /**
     * The state is the only thing that survives the round trip through
     * Keycloak, so it is where the app has to say it is the app.
     */
    public function test_initiate_records_a_native_client_in_the_state(): void
    {
        $state = $this->captureInitiateState('/api/v2/auth/sso/initiate?client=app');

        $this->assertSame('app', $this->decodeState($state)['client'] ?? null);
    }

    public function test_initiate_leaves_the_state_unmarked_for_the_website(): void
    {
        $state = $this->captureInitiateState('/api/v2/auth/sso/initiate');

        $this->assertArrayNotHasKey('client', $this->decodeState($state));
    }

    public function test_callback_from_the_app_redirects_to_the_deep_link_scheme(): void
    {
        Http::fake(['*/broker/*/token' => Http::response(['id_token' => 'idp.token.test'], 200)]);

        $this->mockSocialiteCallback('sub-app-001', 'sso_app@test.example', 'App User', 'appuser');

        $state = $this->buildState(self::INSTANCE_CODE, 'app');
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringStartsWith(
            config('app.mobile_url_scheme').'://oauth-login/',
            (string) $response->headers->get('Location'),
        );
    }

    /**
     * A failed login has to come home too. Leaving errors on the website would
     * strand the user in the browser tab the app opened for the login.
     */
    public function test_callback_error_from_the_app_redirects_to_the_deep_link_scheme(): void
    {
        $state = $this->buildState(self::INSTANCE_CODE, 'app');

        $response = $this->get("/api/v2/auth/sso/callback?error=access_denied&state={$state}");

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');

        $this->assertStringStartsWith(config('app.mobile_url_scheme').'://login?', $location);
        $this->assertStringContainsString('sso_error=login_cancelled', $location);
    }

    /**
     * A state we cannot verify buys no redirect target. Honouring an unsigned
     * `client` claim would let anyone aim the callback at a scheme of their
     * choosing, so an unverifiable state falls back to the website.
     */
    public function test_callback_ignores_a_native_client_claim_in_an_unsigned_state(): void
    {
        $payload = base64_encode(json_encode([
            'instance_code' => self::INSTANCE_CODE,
            'client' => 'app',
            'nonce' => 'testnonce',
        ]));

        $response = $this->get("/api/v2/auth/sso/callback?state={$payload}.badsignature");

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');

        $this->assertStringStartsWith(rtrim((string) config('app.frontend_url'), '/'), $location);
        $this->assertStringContainsString('sso_error=invalid_state', $location);
    }

    /**
     * Run initiate against a mocked Socialite driver and give back the state it
     * put into the authorize request.
     */
    private function captureInitiateState(string $uri): string
    {
        $captured = [];

        $provider = \Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('with')->andReturnUsing(function (array $params) use (&$captured, $provider) {
            $captured = $params;

            return $provider;
        });
        $provider->shouldReceive('redirect')->andReturn(new RedirectResponse('https://sso.example'));

        Socialite::shouldReceive('driver')->with('keycloak')->andReturn($provider);

        $this->getJson($uri, ['aula-instance-code' => self::INSTANCE_CODE])->assertOk();

        $this->assertArrayHasKey('state', $captured);

        return (string) $captured['state'];
    }

    // =========================================================
    // callback — state validation
    // =========================================================

    public function test_callback_with_tampered_state_redirects_to_invalid_state_error(): void
    {
        $response = $this->get('/api/v2/auth/sso/callback?state=payload.badsignature');

        $response->assertRedirect();
        $this->assertStringContainsString('sso_error=invalid_state', $response->headers->get('Location'));
    }

    public function test_callback_with_malformed_state_redirects_to_invalid_state_error(): void
    {
        $response = $this->get('/api/v2/auth/sso/callback?state=noseparator');

        $response->assertRedirect();
        $this->assertStringContainsString('sso_error=invalid_state', $response->headers->get('Location'));
    }

    public function test_callback_with_unknown_tenant_redirects_to_unknown_tenant_error(): void
    {
        $state = $this->buildState('ZZZZZ');

        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('sso_error=unknown_tenant', $response->headers->get('Location'));
    }

    // =========================================================
    // callback — IdP / Keycloak OAuth error responses
    // =========================================================

    public function test_callback_with_access_denied_error_redirects_to_login_cancelled(): void
    {
        // User cancelled at the IdP: Keycloak redirects back with error=access_denied
        // and no authorization code — the callback must not attempt a token exchange.
        $state = $this->buildState(self::INSTANCE_CODE);

        $response = $this->get("/api/v2/auth/sso/callback?error=access_denied&error_description=&state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('sso_error=login_cancelled', $response->headers->get('Location'));
    }

    public function test_callback_with_generic_idp_error_redirects_to_provider_error(): void
    {
        $state = $this->buildState(self::INSTANCE_CODE);

        $response = $this->get("/api/v2/auth/sso/callback?error=server_error&state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('sso_error=sso_provider_error', $response->headers->get('Location'));
    }

    // =========================================================
    // callback — user provisioning and lookup
    // =========================================================

    public function test_callback_provisions_new_user_and_redirects_to_frontend(): void
    {
        Http::fake(['*/broker/*/token' => Http::response(['id_token' => 'idp.token.test'], 200)]);

        $this->mockSocialiteCallback('sub-new-001', 'sso_new@test.example', 'New User', 'newuser');

        $state = $this->buildState(self::INSTANCE_CODE);
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('/oauth-login/', $response->headers->get('Location'));

        self::$testTenant->run(function () {
            $user = LegacyUser::where('sso_sub', 'sub-new-001')->first();
            $this->assertNotNull($user);
            $this->assertEquals('sso_new@test.example', $user->email);
            $this->assertEquals(UserLevel::User, $user->userlevel);
        });
    }

    public function test_callback_with_email_match_no_sub_redirects_to_link_flow(): void
    {
        $existing = self::$testTenant->run(function () {
            return $this->createUser('sso_link@test.example', null);
        });

        $this->mockSocialiteCallback('sub-link-001', 'sso_link@test.example', 'Linker', 'linker');

        $state = $this->buildState(self::INSTANCE_CODE);
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('sso_error=account_link_required', $location);
        $this->assertMatchesRegularExpression('/sso_link=[a-f0-9]{32,}/', $location);

        // The legacy row must NOT be stamped yet — linking is gated on password proof.
        self::$testTenant->run(function () use ($existing) {
            $fresh = LegacyUser::find($existing->id);
            $this->assertNull($fresh->sso_sub);
            $this->assertNull($fresh->sso_id_token);
        });
    }

    public function test_callback_with_email_match_to_inactive_user_rejects_account_inactive_not_link(): void
    {
        self::$testTenant->run(function () {
            $this->createUser('sso_inactive_email@test.example', null, UserStatus::Suspended);
        });

        $this->mockSocialiteCallback('sub-inactive-email-001', 'sso_inactive_email@test.example', 'Inactive', 'inactive');

        $state = $this->buildState(self::INSTANCE_CODE);
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('sso_error=account_inactive', $location);
        $this->assertStringNotContainsString('sso_link=', $location);
    }

    public function test_callback_with_email_match_to_user_having_different_sso_sub_rejects_sub_collision(): void
    {
        self::$testTenant->run(function () {
            $this->createUser('sso_owned@test.example', 'existing-sub-aaa');
        });

        $this->mockSocialiteCallback('intruder-sub-bbb', 'sso_owned@test.example', 'Intruder', 'intruder');

        $state = $this->buildState(self::INSTANCE_CODE);
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('sso_error=sub_collision', $location);
        $this->assertStringNotContainsString('sso_link=', $location);

        // No mutation on the original row.
        self::$testTenant->run(function () {
            $fresh = LegacyUser::where('email', 'sso_owned@test.example')->first();
            $this->assertEquals('existing-sub-aaa', $fresh->sso_sub);
        });
    }

    public function test_callback_finds_existing_user_by_sso_sub(): void
    {
        $existing = self::$testTenant->run(function () {
            return $this->createUser('sso_bysub@test.example', 'sub-by-sub-001');
        });

        Http::fake(['*/broker/*/token' => Http::response(['id_token' => 'idp.token.test'], 200)]);
        // Different email, same sub — should match on sub
        $this->mockSocialiteCallback('sub-by-sub-001', 'sso_changed_email@test.example', 'Same Sub', 'samesub');

        $state = $this->buildState(self::INSTANCE_CODE);
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        self::$testTenant->run(function () {
            // No new user created
            $this->assertEquals(0, LegacyUser::where('email', 'sso_changed_email@test.example')->count());
        });

        $this->assertRedirectAuthenticatesUser($response, $existing);
    }

    public function test_callback_inactive_user_redirects_to_account_inactive_error(): void
    {
        self::$testTenant->run(function () {
            $this->createUser('sso_inactive@test.example', 'sub-inactive-001', UserStatus::Inactive);
        });

        Http::fake(['*/broker/*/token' => Http::response([], 200)]);
        $this->mockSocialiteCallback('sub-inactive-001', 'sso_inactive@test.example', 'Inactive', 'inactive');

        $state = $this->buildState(self::INSTANCE_CODE);
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('sso_error=account_inactive', $response->headers->get('Location'));
    }

    // =========================================================
    // callback — email_verified claim enforcement
    // =========================================================

    public function test_callback_rejects_when_email_verified_is_false(): void
    {
        $idToken = $this->makeIdToken([
            'sub' => 'sub-unverified-001',
            'email' => 'sso_unverified@test.example',
            'email_verified' => false,
        ]);

        $this->mockSocialiteCallback('sub-unverified-001', 'sso_unverified@test.example', 'Unverified', 'unverified', $idToken);

        $state = $this->buildState(self::INSTANCE_CODE);
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('sso_error=email_not_verified', $response->headers->get('Location'));

        self::$testTenant->run(function () {
            $this->assertEquals(0, LegacyUser::where('email', 'sso_unverified@test.example')->count());
            $this->assertEquals(0, LegacyUser::where('sso_sub', 'sub-unverified-001')->count());
        });
    }

    public function test_callback_rejects_when_email_verified_claim_is_missing(): void
    {
        $idToken = $this->makeIdToken([
            'sub' => 'sub-missing-claim-001',
            'email' => 'sso_missingclaim@test.example',
        ]);

        $this->mockSocialiteCallback('sub-missing-claim-001', 'sso_missingclaim@test.example', 'Missing Claim', 'missingclaim', $idToken);

        $state = $this->buildState(self::INSTANCE_CODE);
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('sso_error=email_not_verified', $response->headers->get('Location'));

        self::$testTenant->run(function () {
            $this->assertEquals(0, LegacyUser::where('sso_sub', 'sub-missing-claim-001')->count());
        });
    }

    public function test_callback_allows_unverified_email_when_tenant_disables_requirement(): void
    {
        self::$testTenant->update(['sso_require_email_verified' => false]);

        $idToken = $this->makeIdToken([
            'sub' => 'sub-unverified-allowed',
            'email' => 'sso_unverified_allowed@test.example',
            'email_verified' => false,
        ]);

        $this->mockSocialiteCallback('sub-unverified-allowed', 'sso_unverified_allowed@test.example', 'Allowed', 'allowed', $idToken);

        $state = $this->buildState(self::INSTANCE_CODE);
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('/oauth-login/', $response->headers->get('Location'));

        self::$testTenant->run(function () {
            $user = LegacyUser::where('sso_sub', 'sub-unverified-allowed')->first();
            $this->assertNotNull($user);
            $this->assertEquals('sso_unverified_allowed@test.example', $user->email);
        });
    }

    public function test_callback_allows_missing_email_verified_claim_when_tenant_disables_requirement(): void
    {
        self::$testTenant->update(['sso_require_email_verified' => false]);

        $idToken = $this->makeIdToken([
            'sub' => 'sub-missing-allowed',
            'email' => 'sso_missing_allowed@test.example',
        ]);

        $this->mockSocialiteCallback('sub-missing-allowed', 'sso_missing_allowed@test.example', 'Missing Allowed', 'missingallowed', $idToken);

        $state = $this->buildState(self::INSTANCE_CODE);
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('/oauth-login/', $response->headers->get('Location'));
    }

    public function test_callback_still_rejects_id_token_invalid_when_email_verified_requirement_is_disabled(): void
    {
        // Defense-in-depth check: relaxing the email_verified requirement must NOT
        // also relax the cryptographic verification (sig + iss + aud + exp + azp).
        self::$testTenant->update(['sso_require_email_verified' => false]);

        $idToken = $this->makeIdToken([
            'sub' => 'sub-still-strict',
            'email' => 'sso_strict@test.example',
            'iss' => 'https://impostor.example/realms/aula-test',
        ]);

        $this->mockSocialiteCallback('sub-still-strict', 'sso_strict@test.example', 'Strict', 'strict', $idToken);

        $state = $this->buildState(self::INSTANCE_CODE);
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('sso_error=id_token_invalid', $response->headers->get('Location'));
    }

    public function test_callback_rejects_when_id_token_is_missing(): void
    {
        $socialiteUser = \Mockery::mock(User::class);
        $socialiteUser->token = 'access-token-mock';
        $socialiteUser->refreshToken = 'refresh-token-mock';
        $socialiteUser->accessTokenResponseBody = [];
        $socialiteUser->shouldReceive('getId')->andReturn('sub-no-idtoken');
        $socialiteUser->shouldReceive('getEmail')->andReturn('sso_noidtoken@test.example');
        $socialiteUser->shouldReceive('getName')->andReturn('No IdToken');
        $socialiteUser->shouldReceive('getNickname')->andReturn('noidtoken');

        $provider = \Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('keycloak')->andReturn($provider);

        $state = $this->buildState(self::INSTANCE_CODE);
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('sso_error=id_token_invalid', $response->headers->get('Location'));
    }

    public function test_callback_rejects_when_id_token_is_malformed(): void
    {
        $this->mockSocialiteCallback('sub-malformed-001', 'sso_malformed@test.example', 'Malformed', 'malformed', 'not.a.valid.jwt');

        $state = $this->buildState(self::INSTANCE_CODE);
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('sso_error=id_token_invalid', $response->headers->get('Location'));
    }

    public function test_callback_rejects_when_id_token_signature_is_tampered(): void
    {
        $valid = $this->makeIdToken(['sub' => 'sub-tampered', 'email' => 'sso_tampered@test.example']);
        [$h, $p] = explode('.', $valid);
        $tampered = "{$h}.{$p}.".strtr(base64_encode('not-the-real-signature'), '+/', '-_');

        $this->mockSocialiteCallback('sub-tampered', 'sso_tampered@test.example', 'Tampered', 'tampered', $tampered);

        $state = $this->buildState(self::INSTANCE_CODE);
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('sso_error=id_token_invalid', $response->headers->get('Location'));
    }

    public function test_callback_rejects_when_id_token_issuer_is_unexpected(): void
    {
        $idToken = $this->makeIdToken([
            'sub' => 'sub-bad-iss',
            'email' => 'sso_badiss@test.example',
            'iss' => 'https://impostor.example/realms/aula-test',
        ]);

        $this->mockSocialiteCallback('sub-bad-iss', 'sso_badiss@test.example', 'Bad Iss', 'badiss', $idToken);

        $state = $this->buildState(self::INSTANCE_CODE);
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('sso_error=id_token_invalid', $response->headers->get('Location'));
    }

    // =========================================================
    // POST /sso/link — password-proof account linking
    // =========================================================

    public function test_link_endpoint_stamps_sso_sub_and_tokens_when_bearer_matches_intent(): void
    {
        $user = self::$testTenant->run(fn () => $this->createUser('sso_linkme@test.example', null));

        $linkToken = $this->primeLinkIntent([
            'user_id' => $user->id,
            'email' => $user->email,
            'sso_sub' => 'sub-fresh-001',
            'sso_id_token' => 'aula-id-token-linktest',
            'sso_refresh_token' => 'refresh-token-linktest',
            'instance_code' => self::INSTANCE_CODE,
        ]);

        $jwt = $this->jwtForUser($user);

        $response = $this->postJson('/api/v2/auth/sso/link', ['sso_link_token' => $linkToken], [
            'aula-instance-code' => self::INSTANCE_CODE,
            'Authorization' => "Bearer {$jwt}",
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        self::$testTenant->run(function () use ($user) {
            $fresh = LegacyUser::find($user->id);
            $this->assertEquals('sub-fresh-001', $fresh->sso_sub);
            $this->assertEquals('aula-id-token-linktest', $fresh->sso_id_token);
            $this->assertEquals('refresh-token-linktest', $fresh->sso_refresh_token);
        });
    }

    public function test_link_endpoint_rejects_when_bearer_jwt_user_does_not_match_intent(): void
    {
        [$victim, $attacker] = self::$testTenant->run(function () {
            return [
                $this->createUser('sso_victim@test.example', null),
                $this->createUser('sso_attacker@test.example', null),
            ];
        });

        $linkToken = $this->primeLinkIntent([
            'user_id' => $victim->id,
            'email' => $victim->email,
            'sso_sub' => 'sub-take-over',
            'sso_id_token' => 'tok',
            'instance_code' => self::INSTANCE_CODE,
        ]);

        $jwt = $this->jwtForUser($attacker);

        $response = $this->postJson('/api/v2/auth/sso/link', ['sso_link_token' => $linkToken], [
            'aula-instance-code' => self::INSTANCE_CODE,
            'Authorization' => "Bearer {$jwt}",
        ]);

        $response->assertForbidden();

        self::$testTenant->run(function () use ($victim) {
            $fresh = LegacyUser::find($victim->id);
            $this->assertNull($fresh->sso_sub);
        });
    }

    public function test_link_endpoint_rejects_invalid_or_expired_token(): void
    {
        $user = self::$testTenant->run(fn () => $this->createUser('sso_bad@test.example', null));
        $jwt = $this->jwtForUser($user);

        $response = $this->postJson('/api/v2/auth/sso/link', ['sso_link_token' => 'does-not-exist-12345'], [
            'aula-instance-code' => self::INSTANCE_CODE,
            'Authorization' => "Bearer {$jwt}",
        ]);

        $response->assertStatus(404);
    }

    public function test_link_endpoint_requires_bearer_jwt(): void
    {
        $response = $this->postJson('/api/v2/auth/sso/link', ['sso_link_token' => 'whatever'], [
            'aula-instance-code' => self::INSTANCE_CODE,
        ]);

        $response->assertUnauthorized();
    }

    public function test_link_endpoint_is_one_shot_consumes_intent_after_success(): void
    {
        $user = self::$testTenant->run(fn () => $this->createUser('sso_oneshot@test.example', null));

        $linkToken = $this->primeLinkIntent([
            'user_id' => $user->id,
            'email' => $user->email,
            'sso_sub' => 'sub-oneshot',
            'sso_id_token' => 'tok',
            'instance_code' => self::INSTANCE_CODE,
        ]);

        $jwt = $this->jwtForUser($user);

        $first = $this->postJson('/api/v2/auth/sso/link', ['sso_link_token' => $linkToken], [
            'aula-instance-code' => self::INSTANCE_CODE,
            'Authorization' => "Bearer {$jwt}",
        ]);
        $first->assertOk();

        $second = $this->postJson('/api/v2/auth/sso/link', ['sso_link_token' => $linkToken], [
            'aula-instance-code' => self::INSTANCE_CODE,
            'Authorization' => "Bearer {$jwt}",
        ]);
        $second->assertStatus(404);
    }

    public function test_link_endpoint_rejects_when_target_user_already_has_sso_sub(): void
    {
        $user = self::$testTenant->run(fn () => $this->createUser('sso_alreadylinked@test.example', 'sub-already-set'));

        $linkToken = $this->primeLinkIntent([
            'user_id' => $user->id,
            'email' => $user->email,
            'sso_sub' => 'sub-different-new',
            'sso_id_token' => 'tok',
            'instance_code' => self::INSTANCE_CODE,
        ]);

        $jwt = $this->jwtForUser($user);

        $response = $this->postJson('/api/v2/auth/sso/link', ['sso_link_token' => $linkToken], [
            'aula-instance-code' => self::INSTANCE_CODE,
            'Authorization' => "Bearer {$jwt}",
        ]);

        $response->assertStatus(409);

        self::$testTenant->run(function () use ($user) {
            $fresh = LegacyUser::find($user->id);
            $this->assertEquals('sub-already-set', $fresh->sso_sub);
        });
    }

    // =========================================================
    // resolveUser — collision handling
    // =========================================================

    public function test_resolve_user_prioritises_sso_sub_match_when_collision_exists(): void
    {
        [$emailUser, $subUser] = self::$testTenant->run(function () {
            return [
                $this->createUser('sso_collision@test.example', null),
                $this->createUser('sso_other@test.example', 'sub-collision-001'),
            ];
        });

        Log::shouldReceive('warning')
            ->once()
            ->with(\Mockery::pattern('/SSO: email and sso_sub match different users/'), \Mockery::any());

        Http::fake(['*/broker/*/token' => Http::response(['id_token' => 'idp.token.test'], 200)]);
        $this->mockSocialiteCallback('sub-collision-001', 'sso_collision@test.example', 'Collision', 'collision');

        $state = $this->buildState(self::INSTANCE_CODE);
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        // Should authenticate as the sso_sub user, not the email user
        $this->assertRedirectAuthenticatesUser($response, $subUser);
        $payload = $this->decodeRedirectToken($response);
        $this->assertNotEquals($emailUser->hash_id, $payload->user_hash);
    }

    // =========================================================
    // logout
    // =========================================================

    public function test_logout_returns_null_when_force_logout_disabled(): void
    {
        self::$testTenant->update(['sso_force_logout' => false]);

        $user = self::$testTenant->run(fn () => $this->createUser('sso_logout@test.example', 'sub-logout-001', UserStatus::Active, ['sso_id_token' => 'idtoken']));

        $jwt = $this->jwtForUser($user);

        $response = $this->postJson('/api/v2/auth/sso/logout', [], [
            'aula-instance-code' => self::INSTANCE_CODE,
            'Authorization' => "Bearer {$jwt}",
        ]);

        $response->assertOk()->assertJson(['logout_url' => null]);
    }

    public function test_logout_returns_keycloak_url_when_force_logout_enabled(): void
    {
        self::$testTenant->update(['sso_force_logout' => true]);

        $user = self::$testTenant->run(fn () => $this->createUser('sso_forcelogout@test.example', 'sub-forcelogout-001', UserStatus::Active, [
            'sso_id_token' => 'aula-id-token',
            'sso_refresh_token' => 'refresh-token',
        ]));

        Http::fake([
            '*/openid-connect/logout' => Http::response([], 204),
        ]);

        $jwt = $this->jwtForUser($user);

        $response = $this->postJson('/api/v2/auth/sso/logout', [], [
            'aula-instance-code' => self::INSTANCE_CODE,
            'Authorization' => "Bearer {$jwt}",
        ]);

        $response->assertOk();
        $logoutUrl = $response->json('logout_url');
        $this->assertNotNull($logoutUrl);
        $this->assertStringContainsString('openid-connect/logout', $logoutUrl);
        $this->assertStringContainsString('id_token_hint=aula-id-token', $logoutUrl);
    }

    /**
     * The logout URL must point at the aula realm and carry the frontend as
     * post_logout_redirect_uri. Wrapping it in an upstream IdP logout, as an
     * earlier version did, produced a redirect URI containing a per-logout
     * id_token_hint that no IdP can whitelist. Propagating the logout to the
     * upstream IdP is Keycloak's job, configured on the identity provider.
     */
    public function test_logout_url_targets_keycloak_and_redirects_to_the_frontend(): void
    {
        self::$testTenant->update(['sso_force_logout' => true]);

        $user = self::$testTenant->run(fn () => $this->createUser('sso_nochain@test.example', 'sub-nochain-001', UserStatus::Active, [
            'sso_id_token' => 'aula-id-token',
            'sso_refresh_token' => 'refresh-token',
        ]));

        Http::fake();

        $jwt = $this->jwtForUser($user);

        $response = $this->postJson('/api/v2/auth/sso/logout', [], [
            'aula-instance-code' => self::INSTANCE_CODE,
            'Authorization' => "Bearer {$jwt}",
        ]);

        $logoutUrl = $response->assertOk()->json('logout_url');

        $keycloakBase = rtrim((string) config('services.keycloak.base_url'), '/');
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        $this->assertStringStartsWith($keycloakBase, $logoutUrl);

        parse_str((string) parse_url($logoutUrl, PHP_URL_QUERY), $query);
        $this->assertSame($frontendUrl, $query['post_logout_redirect_uri'] ?? null);
    }

    /**
     * Revoking the Keycloak session server-side kills the very session the
     * front-channel redirect needs in order to trigger Keycloak's IdP logout
     * propagation, so it must not run when we hand a logout URL to the browser.
     */
    public function test_logout_does_not_revoke_the_session_when_a_logout_url_is_returned(): void
    {
        self::$testTenant->update(['sso_force_logout' => true]);

        $user = self::$testTenant->run(fn () => $this->createUser('sso_norevoke@test.example', 'sub-norevoke-001', UserStatus::Active, [
            'sso_id_token' => 'aula-id-token',
            'sso_refresh_token' => 'refresh-token',
        ]));

        Http::fake();

        $jwt = $this->jwtForUser($user);

        $response = $this->postJson('/api/v2/auth/sso/logout', [], [
            'aula-instance-code' => self::INSTANCE_CODE,
            'Authorization' => "Bearer {$jwt}",
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('logout_url'));

        Http::assertNothingSent();
    }

    /**
     * Without an id_token there is no front-channel logout to redirect to, so
     * back-channel revocation is the only way to end the Keycloak session.
     */
    public function test_logout_falls_back_to_session_revocation_without_an_id_token(): void
    {
        self::$testTenant->update(['sso_force_logout' => true]);

        $user = self::$testTenant->run(fn () => $this->createUser('sso_revoke@test.example', 'sub-revoke-001', UserStatus::Active, [
            'sso_id_token' => null,
            'sso_refresh_token' => 'refresh-token',
        ]));

        Http::fake([
            '*/openid-connect/logout' => Http::response([], 204),
        ]);

        $jwt = $this->jwtForUser($user);

        $response = $this->postJson('/api/v2/auth/sso/logout', [], [
            'aula-instance-code' => self::INSTANCE_CODE,
            'Authorization' => "Bearer {$jwt}",
        ]);

        $response->assertOk()->assertJson(['logout_url' => null]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/protocol/openid-connect/logout')
            && $request['refresh_token'] === 'refresh-token');
    }

    // =========================================================
    // idp_user_id
    // =========================================================

    public function test_callback_does_not_record_a_person_id_for_a_non_eduplaces_tenant(): void
    {
        // This tenant is on mock-iserv with no idp_school_id, so the
        // Eduplaces person lookup must not run at all.
        $this->mockSocialiteCallback('sso-sub-iserv-only', 'sso_iservonly@test.example', 'IServ Only', 'iservonly');

        $state = $this->buildState(self::INSTANCE_CODE);
        $this->get("/api/v2/auth/sso/callback?state={$state}")->assertRedirect();

        self::$testTenant->run(function () {
            $user = LegacyUser::where('sso_sub', 'sso-sub-iserv-only')->first();

            $this->assertNotNull($user);
            $this->assertNull($user->idp_user_id);
        });
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function createUser(string $email, ?string $sub, UserStatus $status = UserStatus::Active, array $extra = []): LegacyUser
    {
        $user = new LegacyUser;
        $user->email = $email;
        $user->sso_sub = $sub;
        $user->status = $status;
        $user->username = $email;
        $user->hash_id = md5($email.microtime(true));
        $user->userlevel = 20;
        $user->roles = json_encode([]);
        $user->refresh_token = false;

        foreach ($extra as $col => $val) {
            $user->$col = $val;
        }

        $user->save();

        return $user;
    }

    private function buildState(string $instanceCode, ?string $client = null): string
    {
        $payload = base64_encode(json_encode(array_filter([
            'instance_code' => $instanceCode,
            'client' => $client,
            'nonce' => 'testnonce',
        ], fn ($value): bool => $value !== null)));
        $signature = hash_hmac('sha256', $payload, config('app.key'));

        return $payload.'.'.$signature;
    }

    /**
     * The payload half of a state, without checking its signature.
     *
     * @return array<string, mixed>
     */
    private function decodeState(string $state): array
    {
        [$payload] = explode('.', $state, 2);

        return (array) json_decode((string) base64_decode($payload, true), true);
    }

    private function mockSocialiteCallback(string $sub, string $email, string $name, string $nickname, ?string $idToken = null): void
    {
        $socialiteUser = \Mockery::mock(User::class);
        $socialiteUser->token = 'access-token-mock';
        $socialiteUser->refreshToken = 'refresh-token-mock';
        $socialiteUser->accessTokenResponseBody = [
            'id_token' => $idToken ?? $this->makeIdToken(['sub' => $sub, 'email' => $email, 'email_verified' => true]),
        ];
        $socialiteUser->shouldReceive('getId')->andReturn($sub);
        $socialiteUser->shouldReceive('getEmail')->andReturn($email);
        $socialiteUser->shouldReceive('getName')->andReturn($name);
        $socialiteUser->shouldReceive('getNickname')->andReturn($nickname);

        $provider = \Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('keycloak')->andReturn($provider);
    }

    /**
     * Seed a link intent directly into the cache and return the opaque token.
     * Must run inside tenant context — CacheTenancyBootstrapper applies a
     * per-tenant prefix, so a central write would not be visible to the
     * tenant-scoped controller read.
     */
    private function primeLinkIntent(array $intent): string
    {
        $token = bin2hex(random_bytes(16));
        self::$testTenant->run(function () use ($token, $intent) {
            Cache::put("sso_link:{$token}", $intent, now()->addMinutes(10));
        });

        return $token;
    }

    /**
     * Build a properly signed id_token for the test JWKS. Defaults cover the
     * crypto envelope (iss/aud/exp/azp) so the verifier accepts the token; the
     * email_verified claim is deliberately NOT defaulted so tests that omit it
     * exercise the controller's missing-claim rejection.
     */
    private function makeIdToken(array $claims): string
    {
        return $this->signIdToken(array_merge([
            'iss' => self::KEYCLOAK_BASE.'/realms/'.self::KEYCLOAK_REALM,
            'aud' => self::KEYCLOAK_CLIENT_ID,
            'azp' => self::KEYCLOAK_CLIENT_ID,
            'iat' => time() - 30,
            'exp' => time() + 600,
        ], $claims));
    }

    private function jwtForUser(LegacyUser $user): string
    {
        return self::$testTenant->run(
            fn () => app(LegacyJwtService::class)->generateToken($user)
        );
    }

    /**
     * Extract the JWT token from an /oauth-login/{token} redirect and
     * validate it against LegacyJwtService.
     */
    private function decodeRedirectToken(TestResponse $response): object
    {
        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('/oauth-login/', $location);

        // The redirect carries the resolved tenant as `?code=`, which is not
        // part of the token: signing it in would fail every validation here.
        $parts = explode('/oauth-login/', (string) parse_url($location, PHP_URL_PATH), 2);
        $token = $parts[1] ?? '';
        $this->assertNotEmpty($token, 'redirect did not contain a JWT token');

        $result = self::$testTenant->run(
            fn () => app(LegacyJwtService::class)->validateToken($token)
        );

        $this->assertTrue($result['success'], 'JWT in redirect failed validation: '.($result['error'] ?? ''));

        return $result['payload'];
    }

    private function assertRedirectAuthenticatesUser(TestResponse $response, LegacyUser $user): void
    {
        $payload = $this->decodeRedirectToken($response);
        // $this->assertEquals($user->id, $payload->user_id);
        $this->assertEquals($user->hash_id, $payload->user_hash);
    }
}
