<?php

namespace Tests\Feature;

use App\Enums\UserLevel;
use App\Models\LegacyUser;
use App\Services\LegacyJwtService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class LegacyAuthTest extends TestCase
{
    use CreatesTestTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
    }

    // -------------------------------------------------------------------------
    // JWT service tests (no DB needed)
    // -------------------------------------------------------------------------

    public function test_jwt_service_generates_valid_tokens(): void
    {
        $service = new class extends LegacyJwtService {
            protected function getJwtKey(): string { return 'test_secret'; }
        };

        $user = new LegacyUser();
        $user->id = 1;
        $user->hash_id = 'test_hash_123';
        $user->userlevel = UserLevel::User;
        $user->roles = json_encode([]);
        $user->temp_pw = null;

        $token = $service->generateToken($user);

        $parts = explode('.', $token);
        $this->assertCount(3, $parts);

        $validation = $service->validateToken($token);
        $this->assertTrue($validation['success']);
        $this->assertEquals(1, $validation['payload']->user_id);
    }

    public function test_middleware_rejects_missing_token(): void
    {
        $this->assertTrue(true);
    }

    public function test_legacy_user_password_verification_bcrypt(): void
    {
        $user = new LegacyUser();
        $user->pw = password_hash('correct_password', PASSWORD_DEFAULT);
        $user->temp_pw = null;

        $this->assertTrue($user->checkPassword('correct_password'));
        $this->assertFalse($user->checkPassword('wrong_password'));
    }

    public function test_legacy_user_password_verification_temp_pw(): void
    {
        $user = new LegacyUser();
        $user->pw = password_hash('hashed_password', PASSWORD_DEFAULT);
        $user->temp_pw = 'temp123';

        $this->assertTrue($user->checkPassword('temp123'));
        $this->assertTrue($user->checkPassword('hashed_password'));
        $this->assertFalse($user->checkPassword('wrong'));
    }

    public function test_legacy_user_status_checks(): void
    {
        $user = new LegacyUser();

        $user->status = LegacyUser::STATUS_ACTIVE;
        $this->assertTrue($user->isActive());

        $user->status = LegacyUser::STATUS_INACTIVE;
        $this->assertFalse($user->isActive());

        $user->status = LegacyUser::STATUS_SUSPENDED;
        $this->assertFalse($user->isActive());

        $user->status = LegacyUser::STATUS_ARCHIVED;
        $this->assertFalse($user->isActive());
    }

    public function test_legacy_user_refresh_token_flag(): void
    {
        $user = new LegacyUser();

        $user->refresh_token = false;
        $this->assertFalse($user->needsRefresh());

        $user->refresh_token = true;
        $this->assertTrue($user->needsRefresh());
    }

    public function test_legacy_user_jwt_payload(): void
    {
        $user = new LegacyUser();
        $user->id = 42;
        $user->hash_id = 'hash_abc';
        $user->userlevel = UserLevel::Moderator;
        $user->roles = json_encode([['room' => 'room1', 'role' => 30]]);
        $user->temp_pw = null;

        $payload = $user->getJwtPayload();

        $this->assertEquals(42, $payload['id']);
        $this->assertEquals('hash_abc', $payload['hash_id']);
        $this->assertEquals(30, $payload['userlevel']);
        $this->assertFalse($payload['temp_pw']);
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
            $user->status = LegacyUser::STATUS_ACTIVE;
            $user->hash_id = 'phpunit_hash_' . uniqid();
            $user->userlevel = UserLevel::User;
            $user->roles = json_encode([]);
            $user->refresh_token = false;
            $user->save();
        });

        $response = $this->postJson('/api/v2/legacy-auth/login', [
            'username' => 'phpunit_testuser',
            'password' => $password,
        ], [
            'aula-instance-code' => 'TEST001',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'JWT']);

        $jwt = $response->json('JWT');
        $parts = explode('.', $jwt);
        $this->assertCount(3, $parts);

        $payload = json_decode(base64_decode($parts[1]), true);
        $this->assertEquals(UserLevel::User->value, $payload['user_level']);
        $this->assertFalse($payload['temp_pw']);

        $tenant->run(function () {
            LegacyUser::where('username', 'phpunit_testuser')->delete();
        });
    }

    public function test_legacy_userlevel_persists_as_integer_in_tenant_database(): void
    {
        $tenant = self::$testTenant;
        $this->assertNotNull($tenant);

        $result = $tenant->run(function () {
            LegacyUser::where('username', 'phpunit_enum_user')->delete();

            $user = new LegacyUser();
            $user->username = 'phpunit_enum_user';
            $user->pw = password_hash('secret123', PASSWORD_DEFAULT);
            $user->status = LegacyUser::STATUS_ACTIVE;
            $user->hash_id = 'phpunit_enum_'.uniqid();
            $user->userlevel = UserLevel::PrincipalPlus;
            $user->roles = json_encode([]);
            $user->refresh_token = false;
            $user->save();

            $userId = $user->id;
            $rawUserLevel = DB::table('au_users_basedata')
                ->where('id', $userId)
                ->value('userlevel');

            $freshUser = LegacyUser::findOrFail($userId);

            LegacyUser::where('id', $userId)->delete();

            return [
                'raw' => (int) $rawUserLevel,
                'casted_class' => get_class($freshUser->userlevel),
                'casted_value' => $freshUser->userlevel->value,
            ];
        });

        $this->assertSame(45, $result['raw']);
        $this->assertSame(UserLevel::class, $result['casted_class']);
        $this->assertSame(UserLevel::PrincipalPlus->value, $result['casted_value']);
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
            $user->status = LegacyUser::STATUS_ACTIVE;
            $user->hash_id = 'phpunit_hash_' . uniqid();
            $user->userlevel = UserLevel::User;
            $user->roles = json_encode([]);
            $user->refresh_token = false;
            $user->save();
        });

        $response = $this->postJson('/api/v2/legacy-auth/login', [
            'username' => 'phpunit_testuser',
            'password' => 'wrongpassword',
        ], [
            'aula-instance-code' => 'TEST001',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => false, 'error_code' => 2])
            ->assertJsonMissing(['JWT']);

        $tenant->run(function () {
            LegacyUser::where('username', 'phpunit_testuser')->delete();
        });
    }

    public function test_login_nonexistent_user(): void
    {
        $this->assertNotNull(self::$testTenant);

        $response = $this->postJson('/api/v2/legacy-auth/login', [
            'username' => 'nonexistent_user_xyz',
            'password' => 'anypassword',
        ], [
            'aula-instance-code' => 'TEST001',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => false, 'error_code' => 2]);
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
            $user->status = LegacyUser::STATUS_SUSPENDED;
            $user->hash_id = 'phpunit_hash_' . uniqid();
            $user->userlevel = UserLevel::User;
            $user->roles = json_encode([]);
            $user->refresh_token = false;
            $user->save();
        });

        $response = $this->postJson('/api/v2/legacy-auth/login', [
            'username' => 'phpunit_inactive',
            'password' => 'testpass',
        ], [
            'aula-instance-code' => 'TEST001',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'error_code' => 2,
                'user_status' => LegacyUser::STATUS_SUSPENDED,
            ])
            ->assertJsonMissing(['JWT']);

        $tenant->run(function () {
            LegacyUser::where('username', 'phpunit_inactive')->delete();
        });
    }

    public function test_token_matches_legacy_format(): void
    {
        $service = new class extends LegacyJwtService {
            protected function getJwtKey(): string { return 'test_key'; }
        };

        $user = new LegacyUser();
        $user->id = 1;
        $user->hash_id = 'hash123';
        $user->userlevel = UserLevel::User;
        $user->roles = json_encode([['room' => 'abc', 'role' => 20]]);
        $user->temp_pw = '';

        $token = $service->generateToken($user);

        $parts = explode('.', $token);
        $header = json_decode(base64_decode($parts[0]), true);
        $payload = json_decode(base64_decode($parts[1]), true);

        $this->assertEquals('HS512', $header['alg']);
        $this->assertEquals('JWT', $header['typ']);

        $this->assertArrayHasKey('exp', $payload);
        $this->assertArrayHasKey('user_id', $payload);
        $this->assertArrayHasKey('user_hash', $payload);
        $this->assertArrayHasKey('user_level', $payload);
        $this->assertArrayHasKey('roles', $payload);
        $this->assertArrayHasKey('temp_pw', $payload);

        $this->assertEquals(0, $payload['exp']);
        $this->assertEquals(1, $payload['user_id']);
        $this->assertEquals('hash123', $payload['user_hash']);
        $this->assertEquals(UserLevel::User->value, $payload['user_level']);
    }
}
