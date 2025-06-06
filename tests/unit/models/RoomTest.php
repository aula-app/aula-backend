<?php

require_once __DIR__ . '/../../BaseTestCase.php';

/**
 * Test case for Room model
 */
class RoomTest extends BaseTestCase
{
    /**
     * @var Room Room model instance
     */
    protected $roomModel;
    
    /**
     * Setup method run before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize Room model
        $this->roomModel = new Room($this->db, $this->crypt, $this->syslog);
    }
    
    /**
     * Test getRoomBaseData method
     */
    public function testGetRoomBaseData()
    {
        // Get room data for test room
        $result = $this->roomModel->getRoomBaseData(1);
        
        // Assert response is successful
        $this->assertResponseSuccess($result);
        
        // Assert room data is returned
        $this->assertResponseHasData($result);
        
        // Verify expected fields are present
        $this->assertArrayHasKey('id', $result['data']);
        $this->assertArrayHasKey('name', $result['data']);
        $this->assertArrayHasKey('number_of_users', $result['data']);
    }
    
    /**
     * Test getNumberOfUsers method
     */
    public function testGetNumberOfUsers()
    {
        // Get number of users in room 1
        $result = $this->roomModel->getNumberOfUsers(1);
        
        // Assert the result is a number
        $this->assertIsNumeric($result);
    }
    
    /**
     * Test getNumberOfTopics method
     */
    public function testGetNumberOfTopics()
    {
        // Get number of topics in room 1
        $result = $this->roomModel->getNumberOfTopics(1);
        
        // Assert response is successful
        $this->assertResponseSuccess($result);
        
        // Assert the result is a number
        $this->assertIsNumeric($result['data']);
    }
    
    /**
     * Test getNumberOfIdeas method
     */
    public function testGetNumberOfIdeas()
    {
        // Get number of ideas in room 1
        $result = $this->roomModel->getNumberOfIdeas(1);
        
        // Assert response is successful
        $this->assertResponseSuccess($result);
        
        // Assert the result is a number
        $this->assertIsNumeric($result['data']);
    }
    
    /**
     * Test getRoomHashId method
     */
    public function testGetRoomHashId()
    {
        // Get hash ID for room 1
        $result = $this->roomModel->getRoomHashId(1);
        
        // Assert response is successful
        $this->assertResponseSuccess($result);
        
        // Verify hash ID is returned
        $this->assertResponseHasData($result);
        
        // Hash ID should be a string
        $this->assertIsString($result['data']);
    }
    
    /**
     * Test validSearchField method
     */
    public function testValidSearchField()
    {
        // Test valid field
        $resultValid = $this->roomModel->validSearchField('room_name');
        
        // Test invalid field
        $resultInvalid = $this->roomModel->validSearchField('invalid_field');
        
        // Valid field should return true
        $this->assertTrue($resultValid);
        
        // Invalid field should return false
        $this->assertFalse($resultInvalid);
    }
    
    /**
     * Test getRooms method
     */
    public function testGetRooms()
    {
        // Get rooms with default parameters
        $result = $this->roomModel->getRooms(0, 10);
        
        // Assert response is successful
        $this->assertResponseSuccess($result);
        
        // If rooms exist, verify data structure
        if ($result['count'] > 0) {
            $this->assertResponseHasData($result);
            
            // Check room data structure
            $this->assertArrayHasKey('id', $result['data'][0]);
            $this->assertArrayHasKey('name', $result['data'][0]);
        }
    }
    
    /**
     * Test getRoomsByUser method
     */
    public function testGetRoomsByUser()
    {
        // Get rooms for user 1
        $result = $this->roomModel->getRoomsByUser(1);
        
        // Assert response is successful
        $this->assertResponseSuccess($result);
        
        // If user is in rooms, verify data structure
        if ($result['count'] > 0) {
            $this->assertResponseHasData($result);
            
            // Check room data structure
            $this->assertArrayHasKey('id', $result['data'][0]);
            $this->assertArrayHasKey('name', $result['data'][0]);
        }
    }
}