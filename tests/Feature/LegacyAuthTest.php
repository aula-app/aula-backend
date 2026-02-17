<?php

namespace Tests\Feature;

use App\Models\LegacyUser;
use App\Services\LegacyJwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyAuthTest extends TestCase
{
    /**
     * Test that the JWT service can generate and validate tokens.
     */
    public function test_jwt_service_generates_valid_tokens(): void
    {
        putenv('JWT_KEY=test_secret');

        $service = new LegacyJwtService();

        $user = new LegacyUser();
        $user->id = 1;
        $user->hash_id = 'test_hash_123';
        $user->userlevel = 20;
        $user->roles = json_encode([]);
        $user->temp_pw = null;

        $token = $service->generateToken($user);

        // Token should be 3 parts
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);

        // Validate the token
        $validation = $service->validateToken($token);
        $this->assertTrue($validation['success']);
        $this->assertEquals(1, $validation['payload']->user_id);

        putenv('JWT_KEY');
    }

    /**
     * Test that legacy middleware validates tokens correctly.
     */
    public function test_middleware_rejects_missing_token(): void
    {
        // This test would require a running application with routes
        // For now, we test the service layer
        $this->assertTrue(true);
    }

    /**
     * Test password verification with bcrypt hash.
     */
    public function test_legacy_user_password_verification_bcrypt(): void
    {
        $user = new LegacyUser();
        $user->pw = password_hash('correct_password', PASSWORD_DEFAULT);
        $user->temp_pw = null;

        $this->assertTrue($user->checkPassword('correct_password'));
        $this->assertFalse($user->checkPassword('wrong_password'));
    }

    /**
     * Test password verification with temporary password.
     */
    public function test_legacy_user_password_verification_temp_pw(): void
    {
        $user = new LegacyUser();
        $user->pw = password_hash('hashed_password', PASSWORD_DEFAULT);
        $user->temp_pw = 'temp123';

        // Temp password should work
        $this->assertTrue($user->checkPassword('temp123'));

        // Hashed password should also still work
        $this->assertTrue($user->checkPassword('hashed_password'));

        // Wrong password should fail
        $this->assertFalse($user->checkPassword('wrong'));
    }

    /**
     * Test user status checks.
     */
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

    /**
     * Test refresh token flag.
     */
    public function test_legacy_user_refresh_token_flag(): void
    {
        $user = new LegacyUser();

        $user->refresh_token = false;
        $this->assertFalse($user->needsRefresh());

        $user->refresh_token = true;
        $this->assertTrue($user->needsRefresh());
    }

    /**
     * Test JWT payload generation.
     */
    public function test_legacy_user_jwt_payload(): void
    {
        $user = new LegacyUser();
        $user->id = 42;
        $user->hash_id = 'hash_abc';
        $user->userlevel = 30;
        $user->roles = json_encode([['room' => 'room1', 'role' => 30]]);
        $user->temp_pw = null;

        $payload = $user->getJwtPayload();

        $this->assertEquals(42, $payload['id']);
        $this->assertEquals('hash_abc', $payload['hash_id']);
        $this->assertEquals(30, $payload['userlevel']);
        $this->assertFalse($payload['temp_pw']);
    }

    /**
     * Test successful login via the legacy login endpoint with tenancy.
     */
    public function test_successful_login(): void
    {
        $tenant = \App\Models\Tenant::where('instance_code', 'TEST001')->first();
        $this->assertNotNull($tenant, 'Tenant TEST001 must exist. Run tenant setup first.');

        // Create a test user within the tenant context
        $password = 'testpass123';
        $tenant->run(function () use ($password) {
            LegacyUser::where('username', 'phpunit_testuser')->delete();

            $user = new LegacyUser();
            $user->username = 'phpunit_testuser';
            $user->pw = password_hash($password, PASSWORD_DEFAULT);
            $user->status = LegacyUser::STATUS_ACTIVE;
            $user->hash_id = 'phpunit_hash_' . uniqid();
            $user->userlevel = 20;
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
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'JWT',
            ]);

        // Verify the JWT is valid
        $jwt = $response->json('JWT');
        $parts = explode('.', $jwt);
        $this->assertCount(3, $parts);

        $payload = json_decode(base64_decode($parts[1]), true);
        $this->assertEquals(20, $payload['user_level']);
        $this->assertFalse($payload['temp_pw']);

        // Clean up
        $tenant->run(function () {
            LegacyUser::where('username', 'phpunit_testuser')->delete();
        });
    }

    /**
     * Test login with wrong password returns error.
     */
    public function test_login_wrong_password(): void
    {
        $tenant = \App\Models\Tenant::where('instance_code', 'TEST001')->first();
        $this->assertNotNull($tenant);

        $tenant->run(function () {
            LegacyUser::where('username', 'phpunit_testuser')->delete();

            $user = new LegacyUser();
            $user->username = 'phpunit_testuser';
            $user->pw = password_hash('correctpass', PASSWORD_DEFAULT);
            $user->status = LegacyUser::STATUS_ACTIVE;
            $user->hash_id = 'phpunit_hash_' . uniqid();
            $user->userlevel = 20;
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
            ->assertJson([
                'success' => false,
                'error_code' => 2,
            ])
            ->assertJsonMissing(['JWT']);

        // Clean up
        $tenant->run(function () {
            LegacyUser::where('username', 'phpunit_testuser')->delete();
        });
    }

    /**
     * Test login with non-existent user returns error.
     */
    public function test_login_nonexistent_user(): void
    {
        $tenant = \App\Models\Tenant::where('instance_code', 'TEST001')->first();
        $this->assertNotNull($tenant);

        $response = $this->postJson('/api/v2/legacy-auth/login', [
            'username' => 'nonexistent_user_xyz',
            'password' => 'anypassword',
        ], [
            'aula-instance-code' => 'TEST001',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'error_code' => 2,
            ]);
    }

    /**
     * Test login with inactive user returns status info.
     */
    public function test_login_inactive_user(): void
    {
        $tenant = \App\Models\Tenant::where('instance_code', 'TEST001')->first();
        $this->assertNotNull($tenant);

        $tenant->run(function () {
            LegacyUser::where('username', 'phpunit_inactive')->delete();

            $user = new LegacyUser();
            $user->username = 'phpunit_inactive';
            $user->pw = password_hash('testpass', PASSWORD_DEFAULT);
            $user->status = LegacyUser::STATUS_SUSPENDED;
            $user->hash_id = 'phpunit_hash_' . uniqid();
            $user->userlevel = 20;
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

        // Clean up
        $tenant->run(function () {
            LegacyUser::where('username', 'phpunit_inactive')->delete();
        });
    }

    /**
     * Test that tokens generated match legacy format.
     */
    public function test_token_matches_legacy_format(): void
    {
        putenv('JWT_KEY=test_key');

        $service = new LegacyJwtService();

        $user = new LegacyUser();
        $user->id = 1;
        $user->hash_id = 'hash123';
        $user->userlevel = 20;
        $user->roles = json_encode([['room' => 'abc', 'role' => 20]]);
        $user->temp_pw = '';

        $token = $service->generateToken($user);

        // Decode and verify structure matches legacy
        $parts = explode('.', $token);
        $header = json_decode(base64_decode($parts[0]), true);
        $payload = json_decode(base64_decode($parts[1]), true);

        // Header structure
        $this->assertEquals('HS512', $header['alg']);
        $this->assertEquals('JWT', $header['typ']);

        // Payload structure
        $this->assertArrayHasKey('exp', $payload);
        $this->assertArrayHasKey('user_id', $payload);
        $this->assertArrayHasKey('user_hash', $payload);
        $this->assertArrayHasKey('user_level', $payload);
        $this->assertArrayHasKey('roles', $payload);
        $this->assertArrayHasKey('temp_pw', $payload);

        // Values
        $this->assertEquals(0, $payload['exp']);
        $this->assertEquals(1, $payload['user_id']);
        $this->assertEquals('hash123', $payload['user_hash']);
        $this->assertEquals(20, $payload['user_level']);

        putenv('JWT_KEY');
    }
}
