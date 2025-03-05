<?php

/**
 * TestDatabase class for managing the test database
 */
class TestDatabase
{
    /**
     * @var Database Database instance
     */
    private $db;

    /**
     * @var string Path to database schema file
     */
    private $schemaFile = __DIR__ . '/../../init/aula_db_test.sql';

    /**
     * Constructor
     */
    public function __construct()
    {
        // Initialize database connection
        $this->db = new Database('test');
    }

    /**
     * Setup test database with clean data
     */
    public function setup()
    {
        // Drop and recreate test database
        $this->resetDatabase();

        // Seed test data
        $this->seedTestData();
    }

    /**
     * Reset database to clean state
     */
    private function resetDatabase()
    {
        // Execute schema SQL to reset database
        if (file_exists($this->schemaFile)) {
            $sql = file_get_contents($this->schemaFile);
            $statements = $this->splitSqlStatements($sql);
            
            foreach ($statements as $statement) {
                if (trim($statement) !== '') {
                    $this->db->query($statement);
                }
            }
        }
    }

    /**
     * Seed database with test data
     */
    private function seedTestData()
    {
        // Create test users
        $this->createTestUsers();
        
        // Create test rooms
        $this->createTestRooms();
        
        // Create test ideas
        $this->createTestIdeas();
    }

    /**
     * Create test users for testing
     */
    private function createTestUsers()
    {
        // Admin user
        $this->db->query("INSERT INTO au_users (username, password, email, userlevel, displayname) 
                        VALUES ('test_admin', ?, 'admin@test.com', 50, 'Test Admin')", 
                        [password_hash('admin123', PASSWORD_DEFAULT)]);
        
        // Regular user
        $this->db->query("INSERT INTO au_users (username, password, email, userlevel, displayname) 
                        VALUES ('test_user', ?, 'user@test.com', 10, 'Test User')", 
                        [password_hash('user123', PASSWORD_DEFAULT)]);
                        
        // Moderator user
        $this->db->query("INSERT INTO au_users (username, password, email, userlevel, displayname) 
                        VALUES ('test_mod', ?, 'mod@test.com', 40, 'Test Moderator')", 
                        [password_hash('mod123', PASSWORD_DEFAULT)]);
    }

    /**
     * Create test rooms
     */
    private function createTestRooms()
    {
        // Test room 1
        $this->db->query("INSERT INTO au_rooms (name, slug, descr, active, box)
                        VALUES ('Test Room 1', 'test-room-1', 'Test room description', 1, 1)");
                        
        // Test room 2
        $this->db->query("INSERT INTO au_rooms (name, slug, descr, active, box)
                        VALUES ('Test Room 2', 'test-room-2', 'Another test room', 1, 1)");
    }

    /**
     * Create test ideas
     */
    private function createTestIdeas()
    {
        // Test idea in room 1
        $this->db->query("INSERT INTO au_ideas (title, descr, user_id, room_id, status)
                        VALUES ('Test Idea 1', 'Test idea description', 1, 1, 'open')");
                        
        // Test idea in room 2
        $this->db->query("INSERT INTO au_ideas (title, descr, user_id, room_id, status)
                        VALUES ('Test Idea 2', 'Another test idea', 2, 2, 'open')");
    }
    
    /**
     * Split SQL statements
     * 
     * @param string $sql SQL statements
     * @return array Array of SQL statements
     */
    private function splitSqlStatements($sql)
    {
        // Remove comments and split by semicolon
        $sql = preg_replace('/--.*?\\n|#.*?\\n|/\'.*?\'.|`.*?`.|(?:\\/\\*(?:[^*]|(?:\\*[^\\/]))*\\*\\/)/s', '', $sql);
        return preg_split('/;\\n/', $sql);
    }
}