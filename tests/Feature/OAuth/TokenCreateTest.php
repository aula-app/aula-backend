<?php

namespace Tests\Feature\OAuth;

use App\Enums\UserLevel;
use App\Enums\UserStatus;
use App\Models\LegacyUser;
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
            $user->hash_id = 'phpunit_hash_' . uniqid();
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
            $user->hash_id = 'phpunit_hash_' . uniqid();
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
            $user->hash_id = 'phpunit_hash_' . uniqid();
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

    public function test_token_has_legacy_fields(): void
    {
        $tenant = self::$testTenant;
        $this->assertNotNull($tenant);
        $userId = 0;

        $tenant->run(function () use ($userId) {
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

        // not publishing user_id anymore
        // $this->assertArrayHasKey('user_id', $payload);
        $this->assertArrayHasKey('user_hash', $payload);
        $this->assertArrayHasKey('temp_pw', $payload);
        $this->assertArrayHasKey('user_level', $payload);
        $this->assertArrayHasKey('roles', $payload);

        $this->assertEquals('hash123', $payload['user_hash']);
        $this->assertEquals(1, $payload['temp_pw']);
        $this->assertEquals(20, $payload['user_level']);
        $this->assertEquals([['room' => 'abc', 'role' => 20]], $payload['roles']);
    }
}
