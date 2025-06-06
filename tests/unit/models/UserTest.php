<?php

require_once __DIR__ . '/../../BaseTestCase.php';

/**
 * Test case for User model
 */
class UserTest extends BaseTestCase
{
    /**
     * @var User User model instance
     */
    protected $userModel;
    
    /**
     * Setup method run before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize User model
        $this->userModel = new User($this->db, $this->crypt, $this->syslog);
    }
    
    /**
     * Test getUserBaseData method
     */
    public function testGetUserBaseData()
    {
        // Get user data for test user
        $result = $this->userModel->getUserBaseData(1);
        
        // Assert response is successful
        $this->assertResponseSuccess($result);
        
        // Assert user data is returned
        $this->assertResponseHasData($result);
        
        // Verify expected fields are present
        $this->assertArrayHasKey('id', $result['data']);
        $this->assertArrayHasKey('username', $result['data']);
        $this->assertArrayHasKey('userlevel', $result['data']);
    }
    
    /**
     * Test checkLogin method with valid credentials
     */
    public function testCheckLoginWithValidCredentials()
    {
        // Test valid login
        $result = $this->userModel->checkLogin('test_user', 'user123');
        
        // Assert response is successful
        $this->assertResponseSuccess($result);
        
        // Assert user data is returned
        $this->assertResponseHasData($result);
        
        // Verify login was successful
        $this->assertEquals(0, $result['error_code']);
    }
    
    /**
     * Test checkLogin method with invalid credentials
     */
    public function testCheckLoginWithInvalidCredentials()
    {
        // Test invalid login
        $result = $this->userModel->checkLogin('test_user', 'wrong_password');
        
        // Assert response structure is correct
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('error_code', $result);
        
        // Verify login failed
        $this->assertEquals(3, $result['error_code']);
    }
    
    /**
     * Test addUser and deleteUser methods
     */
    public function testAddAndDeleteUser()
    {
        // Create a test user
        $realname = 'Test User';
        $displayname = 'Test Display';
        $username = 'test_user_' . time(); // Ensure unique username
        $email = 'test_' . time() . '@example.com';
        $password = 'Password123!';
        
        // Add the user
        $result = $this->userModel->addUser(
            $realname,
            $displayname,
            $username,
            $email,
            $password,
            1, // status
            'About me text',
            0, // updater_id
            10, // userlevel
            true // nomail
        );
        
        // Assert user was created successfully
        $this->assertResponseSuccess($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('insert_id', $result['data']);
        
        // Get the new user ID
        $userId = $result['data']['insert_id'];
        
        // Verify user exists
        $userResult = $this->userModel->getUserBaseData($userId);
        $this->assertResponseSuccess($userResult);
        
        // Delete the user
        $deleteResult = $this->userModel->deleteUser($userId);
        
        // Assert deletion was successful
        $this->assertResponseSuccess($deleteResult);
    }
    
    /**
     * Test getUserRooms method
     */
    public function testGetUserRooms()
    {
        // Get rooms for test user
        $result = $this->userModel->getUserRooms(1);
        
        // Assert response is successful
        $this->assertResponseSuccess($result);
        
        // Verify room data is returned
        if ($result['count'] > 0) {
            $this->assertResponseHasData($result);
            
            // Check room data structure
            $this->assertArrayHasKey('hash_id', $result['data'][0]);
        }
    }
    
    /**
     * Test addUserToRoom and removeUserFromRoom methods
     */
    public function testAddAndRemoveUserFromRoom()
    {
        // Create a test user
        $username = 'room_test_user_' . time();
        $addUserResult = $this->userModel->addUser(
            'Room Test User',
            'Room Test Display',
            $username,
            'room_test_' . time() . '@example.com',
            'Password123!',
            1,
            '',
            0,
            10,
            true
        );
        
        $userId = $addUserResult['data']['insert_id'];
        
        // Add user to room 1
        $addToRoomResult = $this->userModel->addUserToRoom($userId, 1);
        
        // Assert addition was successful
        $this->assertResponseSuccess($addToRoomResult);
        
        // Get user's rooms
        $roomsResult = $this->userModel->getUserRooms($userId);
        
        // Assert user is in the room
        $this->assertResponseSuccess($roomsResult);
        $this->assertGreaterThan(0, $roomsResult['count']);
        
        // Remove user from room
        $removeResult = $this->userModel->removeUserFromRoom(1, $userId);
        
        // Assert removal was successful
        $this->assertResponseSuccess($removeResult);
        
        // Clean up - delete test user
        $this->userModel->deleteUser($userId);
    }
    
    /**
     * Test getUserLevel method
     */
    public function testGetUserLevel()
    {
        // Get user level for test user
        $result = $this->userModel->getUserLevel(1);
        
        // Assert response is successful
        $this->assertResponseSuccess($result);
        
        // Verify user level is returned
        $this->assertResponseHasData($result);
        
        // User level should be an integer
        $this->assertIsNumeric($result['data']);
    }
    
    /**
     * Test setUserProperty method
     */
    public function testSetUserProperty()
    {
        // Create a test user
        $username = 'property_test_user_' . time();
        $addUserResult = $this->userModel->addUser(
            'Property Test User',
            'Property Test Display',
            $username,
            'property_test_' . time() . '@example.com',
            'Password123!',
            1,
            '',
            0,
            10,
            true
        );
        
        $userId = $addUserResult['data']['insert_id'];
        
        // Set a property
        $result = $this->userModel->setUserProperty($userId, 'about_me', 'New about me text');
        
        // Assert property was set successfully
        $this->assertResponseSuccess($result);
        
        // Get user data to verify the change
        $userResult = $this->userModel->getUserBaseData($userId);
        
        // Clean up - delete test user
        $this->userModel->deleteUser($userId);
    }
    
    /**
     * Test getUsersByRoom method
     */
    public function testGetUsersByRoom()
    {
        // Get users in room 1
        $result = $this->userModel->getUsersByRoom(1);
        
        // Assert response is successful
        $this->assertResponseSuccess($result);
        
        // If users exist in the room, verify data structure
        if ($result['count'] > 0) {
            $this->assertResponseHasData($result);
            
            // Check user data structure
            $this->assertArrayHasKey('id', $result['data'][0]);
            $this->assertArrayHasKey('username', $result['data'][0]);
        }
    }
}