<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\LegacyUser;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class SsoAwareAccessTokenControllerTest extends TestCase
{
    use CreatesTestTenant;

    private const INSTANCE_CODE = 'TEST001';
    private const PASSWORD      = 'test-password-1234';

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        self::$testTenant->update([
            'sso_enabled'  => true,
            'sso_required' => false,
        ]);
    }

    protected function tearDown(): void
    {
        self::$testTenant->run(fn () => LegacyUser::where('email', 'like', 'login_%@test.example')->delete());
        parent::tearDown();
    }

    // Also acts as the regression guard for #495 link flow — the SSO callback redirects
    // email-matched users (sso_sub still NULL) to the basic sso-aware login so they can prove
    // possession of their password before linking. That login MUST still succeed.
    public function test_login_succeeds_for_user_without_sso_sub_on_non_required_tenant(): void
    {
        self::$testTenant->run(fn () => $this->createUser('login_plain@test.example', 'login_plain', null));

        $response = $this->postJson('/api/v2/oauth/token', [
            'grant_type' => 'password',
            'client_id' => self::$client->id,
            'username' => 'login_plain',
            'password' => self::PASSWORD,
        ], ['aula-instance-code' => self::INSTANCE_CODE]);

        $response->assertStatus(200)
            ->assertJson(['token_type' => 'Bearer'])
            ->assertJsonStructure(['access_token', 'refresh_token']);
    }

    public function test_login_refused_for_user_with_sso_sub_set(): void
    {
        self::$testTenant->run(fn () => $this->createUser('login_ssoUser@test.example', 'login_ssouser', 'sub-already-linked'));

        $response = $this->postJson('/api/v2/oauth/token', [
            'grant_type' => 'password',
            'client_id' => self::$client->id,
            'username' => 'login_ssouser',
            'password' => self::PASSWORD,
        ], ['aula-instance-code' => self::INSTANCE_CODE]);

        $response->assertStatus(400)
            ->assertJsonMissingPath('token_type')
            ->assertJsonMissingPath('access_token')
            ->assertJsonMissingPath('refresh_token')
            ->assertJsonMissingPath('JWT');
    }

    public function test_login_refused_when_tenant_has_sso_required_even_without_user_sso_sub(): void
    {
        self::$testTenant->update(['sso_required' => true]);
        self::$testTenant->run(fn () => $this->createUser('login_required@test.example', 'login_required', null));

        $response = $this->postJson('/api/v2/oauth/token', [
            'grant_type' => 'password',
            'client_id' => self::$client->id,
            'username' => 'login_required',
            'password' => self::PASSWORD,
        ], ['aula-instance-code' => self::INSTANCE_CODE]);

        $response->assertStatus(400)
            ->assertJsonMissingPath('token_type')
            ->assertJsonMissingPath('access_token')
            ->assertJsonMissingPath('refresh_token')
            ->assertJsonMissingPath('JWT');
    }

    public function test_login_refused_for_wrong_password_returns_generic_error(): void
    {
        self::$testTenant->run(fn () => $this->createUser('login_wrong@test.example', 'login_wrong', null));

        $response = $this->postJson('/api/v2/oauth/token', [
            'grant_type' => 'password',
            'client_id' => self::$client->id,
            'username' => 'login_wrong',
            'password' => 'wrong-password',
        ], ['aula-instance-code' => self::INSTANCE_CODE]);

        $response->assertStatus(400)
            ->assertJsonMissingPath('token_type')
            ->assertJsonMissingPath('access_token')
            ->assertJsonMissingPath('refresh_token')
            ->assertJsonMissingPath('JWT');
    }

    private function createUser(string $email, string $username, ?string $sub): LegacyUser
    {
        $user                = new LegacyUser();
        $user->email         = $email;
        $user->username      = $username;
        $user->sso_sub       = $sub;
        $user->status        = UserStatus::Active;
        $user->hash_id       = md5($email . microtime(true));
        $user->userlevel     = 20;
        $user->roles         = json_encode([]);
        $user->refresh_token = false;
        $user->pw            = password_hash(self::PASSWORD, PASSWORD_BCRYPT);
        $user->save();

        return $user;
    }
}
