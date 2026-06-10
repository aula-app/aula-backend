<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\LegacyUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class SsoControllerTest extends TestCase
{
    use CreatesTestTenant;

    private const INSTANCE_CODE = 'TEST001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        self::$testTenant->update([
            'sso_enabled'      => true,
            'sso_provider'     => 'mock-iserv',
            'sso_force_logout' => false,
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
            $this->assertEquals('mock-iserv', $user->sso_provider);
            $this->assertEquals(20, $user->userlevel);
        });
    }

    public function test_callback_finds_existing_user_by_email(): void
    {
        $existing = self::$testTenant->run(function () {
            return $this->createUser('sso_existing@test.example', null);
        });

        Http::fake(['*/broker/*/token' => Http::response(['id_token' => 'idp.token.test'], 200)]);
        $this->mockSocialiteCallback('sub-existing-email', 'sso_existing@test.example', 'Existing', 'existing');

        $state = $this->buildState(self::INSTANCE_CODE);
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        self::$testTenant->run(function () {
            $this->assertEquals(1, LegacyUser::where('email', 'sso_existing@test.example')->count());
        });

        $this->assertRedirectAuthenticatesUser($response, $existing);
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
            $this->createUser('sso_inactive@test.example', 'sub-inactive-001', UserStatus::Suspended->value);
        });

        Http::fake(['*/broker/*/token' => Http::response([], 200)]);
        $this->mockSocialiteCallback('sub-inactive-001', 'sso_inactive@test.example', 'Inactive', 'inactive');

        $state = $this->buildState(self::INSTANCE_CODE);
        $response = $this->get("/api/v2/auth/sso/callback?state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('sso_error=account_inactive', $response->headers->get('Location'));
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
        $this->assertNotEquals($emailUser->id, $payload->user_id);
    }

    // =========================================================
    // logout
    // =========================================================

    public function test_logout_returns_null_when_force_logout_disabled(): void
    {
        self::$testTenant->update(['sso_force_logout' => false]);

        $user = self::$testTenant->run(fn () => $this->createUser('sso_logout@test.example', 'sub-logout-001', UserStatus::Active->value, ['sso_id_token' => 'idtoken']));

        $jwt = $this->jwtForUser($user);

        $response = $this->postJson('/api/v2/auth/sso/logout', [], [
            'aula-instance-code' => self::INSTANCE_CODE,
            'Authorization'      => "Bearer {$jwt}",
        ]);

        $response->assertOk()->assertJson(['logout_url' => null]);
    }

    public function test_logout_returns_keycloak_url_when_force_logout_enabled(): void
    {
        self::$testTenant->update(['sso_force_logout' => true]);

        $user = self::$testTenant->run(fn () => $this->createUser('sso_forcelogout@test.example', 'sub-forcelogout-001', UserStatus::Active->value, [
            'sso_id_token'      => 'aula-id-token',
            'sso_refresh_token' => 'refresh-token',
            'sso_idp_id_token'  => null,
        ]));

        Http::fake([
            '*/openid-connect/logout' => Http::response([], 204),
        ]);

        $jwt = $this->jwtForUser($user);

        $response = $this->postJson('/api/v2/auth/sso/logout', [], [
            'aula-instance-code' => self::INSTANCE_CODE,
            'Authorization'      => "Bearer {$jwt}",
        ]);

        $response->assertOk();
        $logoutUrl = $response->json('logout_url');
        $this->assertNotNull($logoutUrl);
        $this->assertStringContainsString('openid-connect/logout', $logoutUrl);
        $this->assertStringContainsString('id_token_hint=aula-id-token', $logoutUrl);
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function createUser(string $email, ?string $sub, int $status = UserStatus::Active->value, array $extra = []): LegacyUser
    {
        $user = new LegacyUser();
        $user->email      = $email;
        $user->sso_sub    = $sub;
        $user->status     = $status;
        $user->username   = $email;
        $user->hash_id    = md5($email . microtime(true));
        $user->userlevel  = 20;
        $user->roles      = json_encode([]);
        $user->refresh_token = false;

        foreach ($extra as $col => $val) {
            $user->$col = $val;
        }

        $user->save();
        return $user;
    }

    private function buildState(string $instanceCode): string
    {
        $payload = base64_encode(json_encode([
            'instance_code' => $instanceCode,
            'nonce'         => 'testnonce',
        ]));
        $signature = hash_hmac('sha256', $payload, config('app.key'));
        return $payload . '.' . $signature;
    }

    private function mockSocialiteCallback(string $sub, string $email, string $name, string $nickname): void
    {
        $socialiteUser = \Mockery::mock(\Laravel\Socialite\Two\User::class);
        $socialiteUser->token = 'access-token-mock';
        $socialiteUser->refreshToken = 'refresh-token-mock';
        $socialiteUser->accessTokenResponseBody = ['id_token' => 'aula-id-token-mock'];
        $socialiteUser->shouldReceive('getId')->andReturn($sub);
        $socialiteUser->shouldReceive('getEmail')->andReturn($email);
        $socialiteUser->shouldReceive('getName')->andReturn($name);
        $socialiteUser->shouldReceive('getNickname')->andReturn($nickname);

        $provider = \Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('keycloak')->andReturn($provider);
    }

    private function jwtForUser(LegacyUser $user): string
    {
        return self::$testTenant->run(
            fn () => app(\App\Services\LegacyJwtService::class)->generateToken($user)
        );
    }

    /**
     * Extract the JWT token from an /oauth-login/{token} redirect and
     * validate it against LegacyJwtService.
     */
    private function decodeRedirectToken(\Illuminate\Testing\TestResponse $response): object
    {
        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('/oauth-login/', $location);

        $parts = explode('/oauth-login/', $location, 2);
        $token = $parts[1] ?? '';
        $this->assertNotEmpty($token, 'redirect did not contain a JWT token');

        $result = self::$testTenant->run(
            fn () => app(\App\Services\LegacyJwtService::class)->validateToken($token)
        );

        $this->assertTrue($result['success'], 'JWT in redirect failed validation: ' . ($result['error'] ?? ''));
        return $result['payload'];
    }

    private function assertRedirectAuthenticatesUser(\Illuminate\Testing\TestResponse $response, LegacyUser $user): void
    {
        $payload = $this->decodeRedirectToken($response);
        $this->assertEquals($user->id, $payload->user_id);
        $this->assertEquals($user->hash_id, $payload->user_hash);
    }
}
