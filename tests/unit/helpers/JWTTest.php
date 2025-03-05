<?php

require_once __DIR__ . '/../../BaseTestCase.php';

/**
 * Test case for JWT helper class
 */
class JWTTest extends BaseTestCase
{
    /**
     * @var JWT JWT helper instance
     */
    protected $jwtHelper;
    
    /**
     * @var string Test JWT token
     */
    protected $testToken;
    
    /**
     * Setup method run before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        global $jwtKeyFile;
        
        // Initialize JWT helper
        $this->jwtHelper = new JWT($jwtKeyFile, $this->db, $this->crypt, $this->syslog);
        
        // Create test token
        $user = [
            'id' => 1,
            'hash_id' => 'test_hash_id',
            'userlevel' => 10,
            'roles' => json_encode([
                ['role' => 10, 'room' => 'room1'],
                ['role' => 20, 'room' => 'room2']
            ]),
            'temp_pw' => false
        ];
        
        $this->testToken = $this->jwtHelper->gen_jwt($user);
    }
    
    /**
     * Test gen_jwt method
     */
    public function testGenJwt()
    {
        // Create a user for token generation
        $user = [
            'id' => 1,
            'hash_id' => 'test_hash_id',
            'userlevel' => 10,
            'roles' => json_encode([
                ['role' => 10, 'room' => 'room1']
            ]),
            'temp_pw' => false
        ];
        
        // Generate token
        $token = $this->jwtHelper->gen_jwt($user);
        
        // Assert token is a string
        $this->assertIsString($token);
        
        // JWT tokens have 3 parts separated by dots
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
        
        // Verify each part is base64 encoded
        foreach ($parts as $part) {
            $this->assertTrue(ctype_alnum(str_replace(['-', '_'], '', $part)));
        }
    }
    
    /**
     * Test token structure
     */
    public function testTokenStructure()
    {
        // Split token into parts
        $parts = explode('.', $this->testToken);
        
        // Decode header and payload
        $header = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[0]) . str_repeat('=', 4 - (strlen($parts[0]) % 4))), true);
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]) . str_repeat('=', 4 - (strlen($parts[1]) % 4))), true);
        
        // Verify header structure
        $this->assertEquals('HS512', $header['alg']);
        $this->assertEquals('JWT', $header['typ']);
        
        // Verify payload structure
        $this->assertArrayHasKey('user_id', $payload);
        $this->assertArrayHasKey('user_hash', $payload);
        $this->assertArrayHasKey('user_level', $payload);
        $this->assertArrayHasKey('roles', $payload);
        $this->assertArrayHasKey('temp_pw', $payload);
    }
}