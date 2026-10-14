<?php

namespace Tests\Feature\OAuth;

use App\Enums\UserLevel;
use App\Enums\UserStatus;
use App\Models\LegacyUser;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class TokenCreateTest extends TestCase
{
    use CreatesTestTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
    }

    // -------------------------------------------------------------------------
    // Integration tests (require TEST001 tenant)
    // -------------------------------------------------------------------------

    public function test_successful_login(): void
    {
        $tenant = self::$testTenant;
        $this->assertNotNull($tenant);

        $password = 'testpass123';
        $tenant->run(function () use ($password) {
            LegacyUser::where('username', 'phpunit_testuser')->delete();

            $user = new LegacyUser();
            $user->username = 'phpunit_testuser';
            $user->pw = password_hash($password, PASSWORD_DEFAULT);
            $user->status = UserStatus::Active;
            $user->hash_id = 'phpunit_hash_'.Str::random(16);
            $user->userlevel = UserLevel::User;
            $user->roles = json_encode([]);
            $user->refresh_token = false;
            $user->save();
        });

        $response = $this->post('/api/v2/oauth/token', [
            'grant_type' => 'password',
            'client_id' => self::$client->id,
            'username' => 'phpunit_testuser',
            'password' => $password,
        ], [
            'aula-instance-code' => 'TEST001',
        ]);

        $response->assertStatus(200)
            ->assertJson(['token_type' => 'Bearer'])
            ->assertJsonStructure(['access_token', 'refresh_token']);

        $jwt = $response->json('access_token');
        $parts = explode('.', $jwt);
        $this->assertCount(3, $parts);

        $payload = json_decode(base64_decode($parts[1]), true);
        $this->assertEquals(UserLevel::User->value, $payload['user_level']);
        $this->assertFalse($payload['temp_pw']);

        $tenant->run(function () {
            LegacyUser::where('username', 'phpunit_testuser')->delete();
        });
    }

    public function test_login_wrong_password(): void
    {
        $tenant = self::$testTenant;
        $this->assertNotNull($tenant);

        $tenant->run(function () {
            LegacyUser::where('username', 'phpunit_testuser')->delete();

            $user = new LegacyUser();
            $user->username = 'phpunit_testuser';
            $user->pw = password_hash('correctpass', PASSWORD_DEFAULT);
            $user->status = UserStatus::Active;
            $user->hash_id = 'phpunit_hash_'.Str::random(16);
            $user->userlevel = UserLevel::User;
            $user->roles = json_encode([]);
            $user->refresh_token = false;
            $user->save();
        });

        $response = $this->post('/api/v2/oauth/token', [
            'grant_type' => 'password',
            'client_id' => self::$client->id,
            'username' => 'phpunit_testuser',
            'password' => 'wrongpassword',
        ], [
            'aula-instance-code' => 'TEST001',
        ]);

        $response->assertStatus(400)
            ->assertJsonMissingPath('token_type')
            ->assertJsonMissingPath('access_token')
            ->assertJsonMissingPath('refresh_token')
            ->assertJsonMissingPath('JWT');

        $tenant->run(function () {
            LegacyUser::where('username', 'phpunit_testuser')->delete();
        });
    }

    public function test_login_nonexistent_user(): void
    {
        $this->assertNotNull(self::$testTenant);

        $response = $this->post('/api/v2/oauth/token', [
            'grant_type' => 'password',
            'client_id' => self::$client->id,
            'username' => 'nonexistent_user_xyz',
            'password' => 'anypassword',
        ], [
            'aula-instance-code' => 'TEST001',
        ]);

        $response->assertStatus(400)
            ->assertJsonMissingPath('token_type')
            ->assertJsonMissingPath('access_token')
            ->assertJsonMissingPath('refresh_token')
            ->assertJsonMissingPath('JWT');
    }

    public function test_login_inactive_user(): void
    {
        $tenant = self::$testTenant;
        $this->assertNotNull($tenant);

        $tenant->run(function () {
            LegacyUser::where('username', 'phpunit_inactive')->delete();

            $user = new LegacyUser();
            $user->username = 'phpunit_inactive';
            $user->pw = password_hash('testpass', PASSWORD_DEFAULT);
            $user->status = UserStatus::Suspended;
            $user->hash_id = 'phpunit_hash_'.Str::random(16);
            $user->userlevel = UserLevel::User;
            $user->roles = json_encode([]);
            $user->refresh_token = false;
            $user->save();
        });

        $response = $this->post('/api/v2/oauth/token', [
            'grant_type' => 'password',
            'client_id' => self::$client->id,
            'username' => 'phpunit_inactive',
            'password' => 'testpass',
        ], [
            'aula-instance-code' => 'TEST001',
        ]);

        $response->assertStatus(400)
            ->assertJsonMissingPath('token_type')
            ->assertJsonMissingPath('access_token')
            ->assertJsonMissingPath('refresh_token')
            ->assertJsonMissingPath('JWT');
    }

    /**
     * au_users_basedata.pw is nullable, and a TypeError on the way to the
     * hasher answered 500 where an unknown username answers 400, which tells a
     * caller the username exists.
     */
    public function test_a_passwordless_user_is_refused_like_an_unknown_one(): void
    {
        $tenant = self::$testTenant;
        $this->assertNotNull($tenant);

        $tenant->run(function () {
            LegacyUser::where('username', 'phpunit_shell')->delete();

            $user = new LegacyUser();
            $user->username = 'phpunit_shell';
            $user->pw = null;
            $user->status = UserStatus::Active;
            $user->hash_id = 'phpunit_hash_'.Str::random(16);
            $user->userlevel = UserLevel::User;
            $user->roles = json_encode([]);
            $user->refresh_token = false;
            $user->save();
        });

        $unknown = $this->post('/api/v2/oauth/token', [
            'grant_type' => 'password',
            'client_id' => self::$client->id,
            'username' => 'phpunit_no_such_user',
            'password' => 'anything',
        ], ['aula-instance-code' => 'TEST001']);

        $passwordless = $this->post('/api/v2/oauth/token', [
            'grant_type' => 'password',
            'client_id' => self::$client->id,
            'username' => 'phpunit_shell',
            'password' => 'anything',
        ], ['aula-instance-code' => 'TEST001']);

        $passwordless->assertStatus(400)->assertJsonMissingPath('access_token');
        $this->assertSame(
            $unknown->getStatusCode(),
            $passwordless->getStatusCode(),
            'a real passwordless user must be indistinguishable from an unknown one',
        );

        $tenant->run(function () {
            LegacyUser::where('username', 'phpunit_shell')->delete();
        });
    }

    /**
     * Passport uses the configured hasher unless the model validates for
     * itself, which skipped temp_pw and locked out anyone mid password reset.
     */
    public function test_login_with_a_temporary_password(): void
    {
        $tenant = self::$testTenant;
        $this->assertNotNull($tenant);

        $tenant->run(function () {
            LegacyUser::where('username', 'phpunit_temp')->delete();

            $user = new LegacyUser();
            $user->username = 'phpunit_temp';
            $user->pw = null;
            $user->temp_pw = 'temp123';
            $user->status = UserStatus::Active;
            $user->hash_id = 'phpunit_hash_'.Str::random(16);
            $user->userlevel = UserLevel::User;
            $user->roles = json_encode([]);
            $user->refresh_token = false;
            $user->save();
        });

        $response = $this->post('/api/v2/oauth/token', [
            'grant_type' => 'password',
            'client_id' => self::$client->id,
            'username' => 'phpunit_temp',
            'password' => 'temp123',
        ], ['aula-instance-code' => 'TEST001']);

        $response->assertOk()->assertJsonStructure(['access_token']);

        // The frontend routes on this claim to force a password change.
        $payload = json_decode(base64_decode(explode('.', $response->json('access_token'))[1]), true);
        $this->assertTrue((bool) $payload['temp_pw']);

        $this->post('/api/v2/oauth/token', [
            'grant_type' => 'password',
            'client_id' => self::$client->id,
            'username' => 'phpunit_temp',
            'password' => 'not-the-temp-password',
        ], ['aula-instance-code' => 'TEST001'])->assertStatus(400);

        $tenant->run(function () {
            LegacyUser::where('username', 'phpunit_temp')->delete();
        });
    }

    /**
     * The refresh grant sends no username or password, so validating them on
     * every request to this endpoint locks it out.
     */
    public function test_refresh_token_grant_issues_a_new_token_pair(): void
    {
        $tenant = self::$testTenant;
        $this->assertNotNull($tenant);

        $password = 'testpass123';
        $tenant->run(function () use ($password) {
            LegacyUser::where('username', 'phpunit_refresh')->delete();

            $user = new LegacyUser();
            $user->username = 'phpunit_refresh';
            $user->pw = password_hash($password, PASSWORD_DEFAULT);
            $user->status = UserStatus::Active;
            $user->hash_id = 'phpunit_hash_'.Str::random(16);
            $user->userlevel = UserLevel::User;
            $user->roles = json_encode([]);
            $user->refresh_token = false;
            $user->save();
        });

        $refreshToken = $this->post('/api/v2/oauth/token', [
            'grant_type' => 'password',
            'client_id' => self::$client->id,
            'username' => 'phpunit_refresh',
            'password' => $password,
        ], [
            'aula-instance-code' => 'TEST001',
        ])->assertOk()->json('refresh_token');

        $this->assertNotEmpty($refreshToken);

        $response = $this->post('/api/v2/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => self::$client->id,
            'refresh_token' => $refreshToken,
        ], [
            'aula-instance-code' => 'TEST001',
        ]);

        $response->assertOk()
            ->assertJson(['token_type' => 'Bearer'])
            ->assertJsonStructure(['access_token', 'refresh_token']);

        // A refreshed token must carry the aula claims the legacy API reads.
        $payload = json_decode(base64_decode(explode('.', $response->json('access_token'))[1]), true);
        $this->assertArrayHasKey('user_id', $payload);
        $this->assertArrayHasKey('user_hash', $payload);
        $this->assertEquals(UserLevel::User->value, $payload['user_level']);

        $tenant->run(function () {
            LegacyUser::where('username', 'phpunit_refresh')->delete();
        });
    }

    /**
     * AulaClaims looks the user up again to build the claims, and a refresh
     * token outlives the row it was issued for.
     */
    public function test_refreshing_for_a_deleted_user_is_an_oauth_error(): void
    {
        $tenant = self::$testTenant;
        $this->assertNotNull($tenant);

        $password = 'testpass123';
        $tenant->run(function () use ($password) {
            LegacyUser::where('username', 'phpunit_doomed')->delete();

            $user = new LegacyUser();
            $user->username = 'phpunit_doomed';
            $user->pw = password_hash($password, PASSWORD_DEFAULT);
            $user->status = UserStatus::Active;
            $user->hash_id = 'phpunit_hash_'.Str::random(16);
            $user->userlevel = UserLevel::User;
            $user->roles = json_encode([]);
            $user->refresh_token = false;
            $user->save();
        });

        $refreshToken = $this->post('/api/v2/oauth/token', [
            'grant_type' => 'password',
            'client_id' => self::$client->id,
            'username' => 'phpunit_doomed',
            'password' => $password,
        ], ['aula-instance-code' => 'TEST001'])->assertOk()->json('refresh_token');

        $tenant->run(function () {
            LegacyUser::where('username', 'phpunit_doomed')->delete();
        });

        $this->post('/api/v2/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => self::$client->id,
            'refresh_token' => $refreshToken,
        ], ['aula-instance-code' => 'TEST001'])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_grant');
    }

    public function test_password_grant_still_requires_credentials(): void
    {
        $this->assertNotNull(self::$testTenant);

        $this->postJson('/api/v2/oauth/token', [
            'grant_type' => 'password',
            'client_id' => self::$client->id,
        ], [
            'aula-instance-code' => 'TEST001',
        ])->assertStatus(422)->assertJsonValidationErrors(['username', 'password']);
    }

    public function test_token_has_legacy_fields(): void
    {
        $tenant = self::$testTenant;
        $this->assertNotNull($tenant);
        $userId = 0;

        $tenant->run(function () use (&$userId) {
            LegacyUser::where('username', 'phpunit_testuser')->delete();

            $user = new LegacyUser();
            $user->username = 'phpunit_testuser';
            $user->pw = password_hash('testpass', PASSWORD_DEFAULT);
            $user->status = UserStatus::Active;
            $user->hash_id = 'hash123';
            $user->userlevel = UserLevel::User;
            $user->roles = json_encode([['room' => 'abc', 'role' => 20]]);
            $user->temp_pw = 'test';
            $user->refresh_token = false;
            $user->save();
            $userId = $user->refresh()->id;
        });

        $response = $this->post('/api/v2/oauth/token', [
            'grant_type' => 'password',
            'client_id' => self::$client->id,
            'username' => 'phpunit_testuser',
            'password' => 'testpass',
        ], [
            'aula-instance-code' => 'TEST001',
        ])
            ->assertOk()
            ->assertJson(['token_type' => 'Bearer'])
            ->assertJsonStructure(['access_token', 'refresh_token']);

        $token = json_decode($response->getContent())->access_token;
        $parts = explode('.', $token);
        $header = json_decode(base64_decode($parts[0]), true);
        $payload = json_decode(base64_decode($parts[1]), true);

        /* $this->assertEquals('HS512', $header['alg']); */
        $this->assertEquals('JWT', $header['typ']);

        $this->assertArrayHasKey('exp', $payload);
        $this->assertGreaterThan(time() - 100, $payload['exp']);
        $this->assertLessThanOrEqual(time() + 86400, $payload['exp']);

        $this->assertArrayHasKey('user_id', $payload);
        $this->assertArrayHasKey('user_hash', $payload);
        $this->assertArrayHasKey('temp_pw', $payload);
        $this->assertArrayHasKey('user_level', $payload);
        $this->assertArrayHasKey('roles', $payload);

        $this->assertEquals($userId, $payload['user_id']);
        $this->assertEquals('hash123', $payload['user_hash']);
        $this->assertEquals(1, $payload['temp_pw']);
        $this->assertEquals(20, $payload['user_level']);
        $this->assertEquals([['room' => 'abc', 'role' => 20]], $payload['roles']);
    }
}
