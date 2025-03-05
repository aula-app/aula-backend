<?php

/**
 * TestConfig class for setting up the test environment
 */
class TestConfig
{
    /**
     * Database credentials for testing
     */
    private $dbHost = 'localhost';
    private $dbName = 'aula_test';
    private $dbUser = 'aula_test';
    private $dbPass = 'aula_test';

    /**
     * Path to test JWT key file
     */
    private $jwtKeyFile = __DIR__ . '/../../jwt_key.test.ini';

    /**
     * Path to test crypt file
     */
    private $cryptFile = __DIR__ . '/../../tests/bootstrap/test_crypt.ini';

    /**
     * Setup test environment
     */
    public function setupTestEnvironment()
    {
        // Create test database connection
        $this->setupTestDatabase();

        // Create test JWT key file if it doesn't exist
        $this->setupTestJwtKey();

        // Create test crypt file if it doesn't exist
        $this->setupTestCrypt();

        // Define global constants for testing
        $this->defineTestConstants();
    }

    /**
     * Setup test database connection
     */
    private function setupTestDatabase()
    {
        // Make test database connection available globally
        global $dbHost, $dbName, $dbUser, $dbPass;
        $dbHost = $this->dbHost;
        $dbName = $this->dbName;
        $dbUser = $this->dbUser;
        $dbPass = $this->dbPass;
    }

    /**
     * Setup test JWT key
     */
    private function setupTestJwtKey()
    {
        if (!file_exists($this->jwtKeyFile)) {
            // Create test JWT key file with a test secret
            $jwtKey = bin2hex(random_bytes(32));
            file_put_contents($this->jwtKeyFile, "secret=$jwtKey");
        }

        // Make JWT key file path available globally
        global $jwtKeyFile;
        $jwtKeyFile = $this->jwtKeyFile;
    }

    /**
     * Setup test crypt configuration
     */
    private function setupTestCrypt()
    {
        if (!file_exists($this->cryptFile)) {
            // Create directory if it doesn't exist
            $cryptDir = dirname($this->cryptFile);
            if (!is_dir($cryptDir)) {
                mkdir($cryptDir, 0755, true);
            }

            // Create test crypt file with test keys
            $key = bin2hex(random_bytes(32));
            $iv = bin2hex(random_bytes(16));
            $contents = "key=$key\niv=$iv";
            file_put_contents($this->cryptFile, $contents);
        }

        // Make crypt file path available globally
        global $cryptFile;
        $cryptFile = $this->cryptFile;
    }

    /**
     * Define constants needed for tests
     */
    private function defineTestConstants()
    {
        global $baseModelDir, $baseHelperDir;
        
        // Set model and helper directories
        $baseModelDir = __DIR__ . '/../../classes/models/';
        $baseHelperDir = __DIR__ . '/../../classes/helpers/';
    }
}