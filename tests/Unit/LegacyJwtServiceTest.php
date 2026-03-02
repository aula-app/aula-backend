<?php

namespace Tests\Unit;

use App\Models\LegacyUser;
use App\Services\LegacyJwtService;
use Mockery;
use Tests\TestCase;

class LegacyJwtServiceTest extends TestCase
{
    protected LegacyJwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();

        // Set a known JWT key for testing
        putenv('JWT_KEY=test_jwt_secret_key');

        $this->jwtService = new LegacyJwtService();
    }

    protected function tearDown(): void
    {
        putenv('JWT_KEY');
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create a mock LegacyUser for testing.
     */
    protected function createMockUser(array $attributes = []): LegacyUser
    {
        $user = new LegacyUser();
        $user->id = $attributes['id'] ?? 1;
        $user->hash_id = $attributes['hash_id'] ?? 'abc123def456';
        $user->username = $attributes['username'] ?? 'testuser';
        $user->email = $attributes['email'] ?? 'test@example.com';
        $user->userlevel = $attributes['userlevel'] ?? 20;
        $user->roles = $attributes['roles'] ?? json_encode([['room' => 'room1', 'role' => 20]]);
        $user->status = $attributes['status'] ?? LegacyUser::STATUS_ACTIVE;
        $user->temp_pw = $attributes['temp_pw'] ?? null;

        return $user;
    }

    public function test_generateToken_creates_valid_jwt_format(): void
    {
        $user = $this->createMockUser();
        $token = $this->jwtService->generateToken($user);

        // JWT should have 3 parts separated by dots
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);

        // Each part should be base64url encoded
        foreach ($parts as $part) {
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $part);
        }
    }

    public function test_generateToken_includes_correct_header(): void
    {
        $user = $this->createMockUser();
        $token = $this->jwtService->generateToken($user);

        $parts = explode('.', $token);
        $header = json_decode(base64_decode($parts[0]), true);

        $this->assertEquals('HS512', $header['alg']);
        $this->assertEquals('JWT', $header['typ']);
    }

    public function test_generateToken_includes_correct_payload(): void
    {
        $user = $this->createMockUser([
            'id' => 42,
            'hash_id' => 'unique_hash_123',
            'userlevel' => 30,
            'roles' => json_encode([['room' => 'test_room', 'role' => 30]]),
        ]);

        $token = $this->jwtService->generateToken($user);

        $parts = explode('.', $token);
        $payload = json_decode(base64_decode($parts[1]));

        $this->assertEquals(0, $payload->exp);
        $this->assertEquals(42, $payload->user_id);
        $this->assertEquals('unique_hash_123', $payload->user_hash);
        $this->assertEquals(30, $payload->user_level);
        $this->assertIsArray($payload->roles);
        $this->assertFalse($payload->temp_pw);
    }

    public function test_generateToken_sets_temp_pw_true_when_set(): void
    {
        $user = $this->createMockUser(['temp_pw' => 'temporary123']);

        $token = $this->jwtService->generateToken($user);

        $parts = explode('.', $token);
        $payload = json_decode(base64_decode($parts[1]));

        $this->assertTrue($payload->temp_pw);
    }

    public function test_validateToken_accepts_valid_token(): void
    {
        $user = $this->createMockUser();
        $token = $this->jwtService->generateToken($user);

        $result = $this->jwtService->validateToken($token);

        $this->assertTrue($result['success']);
        $this->assertObjectHasProperty('user_id', $result['payload']);
        $this->assertEquals($user->id, $result['payload']->user_id);
    }

    public function test_validateToken_rejects_invalid_format(): void
    {
        $result = $this->jwtService->validateToken('invalid_token');

        $this->assertFalse($result['success']);
        $this->assertEquals('invalid_token_format', $result['error']);
    }

    public function test_validateToken_rejects_wrong_signature(): void
    {
        $user = $this->createMockUser();
        $token = $this->jwtService->generateToken($user);

        // Tamper with the signature
        $parts = explode('.', $token);
        $parts[2] = 'tampered_signature_abc123';
        $tamperedToken = implode('.', $parts);

        $result = $this->jwtService->validateToken($tamperedToken);

        $this->assertFalse($result['success']);
        $this->assertEquals('invalid_signature', $result['error']);
    }

    public function test_validateToken_rejects_tampered_payload(): void
    {
        $user = $this->createMockUser();
        $token = $this->jwtService->generateToken($user);

        // Tamper with the payload
        $parts = explode('.', $token);
        $payload = json_decode(base64_decode($parts[1]), true);
        $payload['user_id'] = 999;  // Change user ID
        $parts[1] = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));
        $tamperedToken = implode('.', $parts);

        $result = $this->jwtService->validateToken($tamperedToken);

        $this->assertFalse($result['success']);
        $this->assertEquals('invalid_signature', $result['error']);
    }

    public function test_getPayload_extracts_payload_without_validation(): void
    {
        $user = $this->createMockUser(['id' => 123]);
        $token = $this->jwtService->generateToken($user);

        $payload = $this->jwtService->getPayload($token);

        $this->assertNotNull($payload);
        $this->assertEquals(123, $payload->user_id);
    }

    public function test_getPayload_returns_null_for_invalid_token(): void
    {
        $payload = $this->jwtService->getPayload('invalid');

        $this->assertNull($payload);
    }

    public function test_extractBearerToken_extracts_token_correctly(): void
    {
        $token = $this->jwtService->extractBearerToken('Bearer abc123token');

        $this->assertEquals('abc123token', $token);
    }

    public function test_extractBearerToken_handles_lowercase_bearer(): void
    {
        $token = $this->jwtService->extractBearerToken('bearer abc123token');

        $this->assertEquals('abc123token', $token);
    }

    public function test_extractBearerToken_returns_null_for_missing_header(): void
    {
        $token = $this->jwtService->extractBearerToken(null);

        $this->assertNull($token);
    }

    public function test_extractBearerToken_returns_null_for_invalid_format(): void
    {
        $token = $this->jwtService->extractBearerToken('Basic abc123');

        $this->assertNull($token);
    }

    public function test_token_is_compatible_with_legacy_format(): void
    {
        // This test ensures the token format matches the legacy PHP implementation
        $user = $this->createMockUser([
            'id' => 1,
            'hash_id' => 'test_hash',
            'userlevel' => 20,
            'roles' => '[]',
        ]);

        $token = $this->jwtService->generateToken($user);

        // Verify token can be decoded manually like legacy system does
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);

        // Verify base64 decoding works
        $header = base64_decode($parts[0]);
        $payload = base64_decode($parts[1]);

        $this->assertNotFalse($header);
        $this->assertNotFalse($payload);

        // Verify JSON decoding works
        $headerData = json_decode($header, true);
        $payloadData = json_decode($payload, true);

        $this->assertIsArray($headerData);
        $this->assertIsArray($payloadData);
        $this->assertEquals('HS512', $headerData['alg']);
    }
}
