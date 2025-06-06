<?php

use PHPUnit\Framework\TestCase;

/**
 * Base test case for all unit tests
 */
class BaseTestCase extends TestCase
{
    /**
     * @var Database Database instance
     */
    protected $db;
    
    /**
     * @var Crypt Crypt instance
     */
    protected $crypt;
    
    /**
     * @var Systemlog Systemlog instance
     */
    protected $syslog;
    
    /**
     * @var TestDatabase Test database manager
     */
    protected $testDb;
    
    /**
     * Setup method run before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize database connection
        global $cryptFile;
        
        $this->db = new Database('test');
        $this->crypt = new Crypt($cryptFile);
        $this->syslog = new Systemlog($this->db);
        
        // Setup test database
        require_once __DIR__ . '/../bootstrap/TestDatabase.php';
        $this->testDb = new TestDatabase();
        $this->testDb->setup();
    }
    
    /**
     * Create a test JWT token for authentication
     * 
     * @param int $userId User ID
     * @param int $userLevel User level
     * @param array $roles User roles
     * @return string JWT token
     */
    protected function createTestToken($userId = 1, $userLevel = 10, $roles = ['user'])
    {
        global $jwtKeyFile;
        
        $jwt = new JWT($jwtKeyFile, $this->db, $this->crypt, $this->syslog);
        
        $user = [
            'id' => $userId,
            'hash_id' => 'test_hash_' . $userId,
            'userlevel' => $userLevel,
            'roles' => json_encode($roles),
            'temp_pw' => false
        ];
        
        return $jwt->gen_jwt($user);
    }
    
    /**
     * Assert that an API response is successful
     * 
     * @param array $response API response
     */
    protected function assertResponseSuccess($response)
    {
        $this->assertArrayHasKey('success', $response);
        $this->assertTrue($response['success']);
    }
    
    /**
     * Assert that an API response failed
     * 
     * @param array $response API response
     */
    protected function assertResponseFailure($response)
    {
        $this->assertArrayHasKey('success', $response);
        $this->assertFalse($response['success']);
    }
    
    /**
     * Assert that the response contains data
     * 
     * @param array $response API response
     */
    protected function assertResponseHasData($response)
    {
        $this->assertArrayHasKey('data', $response);
        $this->assertNotEmpty($response['data']);
    }
}