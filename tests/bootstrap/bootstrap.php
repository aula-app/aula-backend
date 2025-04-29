<?php

// Bootstrap file for PHPUnit tests

// Composer autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

// Set test environment
putenv('APP_ENV=testing');

// Include base configuration for testing
require_once __DIR__ . '/TestConfig.php';

// Load test database configuration
$testConfig = new TestConfig();
$testConfig->setupTestEnvironment();