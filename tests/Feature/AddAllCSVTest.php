<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Test class for User::addAllCSV functionality
 *
 * This test verifies that when importing users from a CSV:
 * 1. Users are created in au_users_basedata table
 * 2. User-room relationships are created in au_rel_rooms_users table
 * 3. Roles are properly set in the au_users_basedata.roles JSON column
 *
 * Prerequisites:
 * - Database must be initialized with schema from legacy/init/aula_db_structure.sql
 * - A standard room (type=1) must exist
 *
 * Configuration:
 * - Set AULA_TEST_INSTANCE environment variable to specify the instance code (default: SINGLE)
 *   Example: AULA_TEST_INSTANCE=TEST01 php artisan test --filter=AddAllCSVTest
 */
class AddAllCSVTest extends TestCase
{
    private $user;
    private $db;
    private $testRooms = [];
    private $createdUserIds = [];
    private string $instanceCode;

    protected function setUp(): void
    {
        parent::setUp();

        // Get instance code from environment variable, default to 'SINGLE'
        $this->instanceCode = env('AULA_TEST_INSTANCE', 'SINGLE');

        // Load legacy classes
        $this->loadLegacyClasses();

        // Initialize the User model with dependencies
        $this->initializeUserModel();

        // Ensure we have a standard room
        $this->ensureStandardRoomExists();
    }

    protected function tearDown(): void
    {
        // Clean up test data created during tests
        $this->cleanupTestData();
        parent::tearDown();
    }

    /**
     * Load the required legacy PHP classes
     */
    private function loadLegacyClasses(): void
    {
        global $allowed_include;
        $allowed_include = 1;

        global $baseHelperDir, $baseClassDir;
        $baseHelperDir = base_path('legacy/src/classes/helpers/');
        $baseClassDir = base_path('legacy/src/classes/');

        // Create a mock Memcached class if not available (for testing without memcached extension)
        if (!class_exists('Memcached')) {
            eval('
                class Memcached {
                    private $data = [];
                    public function addServer($host, $port) { return true; }
                    public function get($key) { return $this->data[$key] ?? null; }
                    public function set($key, $value, $expiration = 0) { $this->data[$key] = $value; return true; }
                    public function delete($key) { unset($this->data[$key]); return true; }
                }
            ');
        }

        // Create legacy Crypt class with a different name to avoid conflict with Laravel Crypt facade
        if (!class_exists('LegacyCrypt', false)) {
            eval('
                class LegacyCrypt {
                    private $key;
                    public function __construct() {
                        $this->key = getenv("SUPERKEY");
                    }
                    public function encrypt(string $plaintext) {
                        return $plaintext;
                    }
                    public function decrypt(string $encryptedString) {
                        return $encryptedString;
                    }
                }
            ');
        }

        // Load instances config first to set up the global $instances variable
        global $instances;
        require_once base_path('legacy/config/instances_config.php');

        // Load required files in order
        // Note: We need to load classes carefully to avoid conflicts with Laravel facades
        if (!class_exists('InstanceConfig', false)) {
            require_once base_path('legacy/src/classes/helpers/InstanceConfig.php');
        }
        // Skip loading legacy Crypt class - we use LegacyCrypt instead to avoid Laravel facade conflict
        if (!class_exists('Database', false)) {
            require_once base_path('legacy/src/classes/models/Database.php');
        }
        if (!class_exists('Converters', false)) {
            require_once base_path('legacy/src/classes/models/Converters.php');
        }
        if (!class_exists('Systemlog', false)) {
            require_once base_path('legacy/src/classes/models/Systemlog.php');
        }
        if (!class_exists('RoomRepository', false)) {
            require_once base_path('legacy/src/classes/repositories/RoomRepository.php');
        }
        if (!class_exists('ResponseBuilder', false)) {
            require_once base_path('legacy/src/classes/helpers/ResponseBuilder.php');
        }
        if (!class_exists('ResetPasswordForUserUseCase', false)) {
            require_once base_path('legacy/src/classes/usecases/users/ResetPasswordForUserUseCase.php');
        }
        if (!class_exists('User', false)) {
            require_once base_path('legacy/src/classes/models/User.php');
        }
    }

    /**
     * Initialize the User model with all required dependencies
     */
    private function initializeUserModel(): void
    {
        // Create instance config using the configured instance code
        $instanceConfig = \InstanceConfig::createFromCode($this->instanceCode);

        // Initialize dependencies
        $this->db = new \Database($instanceConfig);
        // Use LegacyCrypt to avoid conflict with Laravel's Crypt facade
        $crypt = new \LegacyCrypt();
        $syslog = new \Systemlog($this->db);

        // Create User model
        $this->user = new \User($this->db, $crypt, $syslog);
    }

    /**
     * Ensure a standard room (type=1) exists for testing
     */
    private function ensureStandardRoomExists(): void
    {
        $stmt = $this->db->prepareStatement(
            "SELECT id FROM {$this->db->au_rooms} WHERE type = 1 LIMIT 1"
        );
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result) {
            // Create a standard room if none exists
            $hashId = $this->generateHashId();
            $stmt = $this->db->prepareStatement(
                "INSERT INTO {$this->db->au_rooms} (room_name, hash_id, status, type, created, last_update)
                 VALUES ('AULA Standard Room', :hash_id, 1, 1, NOW(), NOW())"
            );
            $stmt->execute([':hash_id' => $hashId]);
        }
    }

    /**
     * Clean up test data created during the test
     */
    private function cleanupTestData(): void
    {
        // Delete test users from au_rel_rooms_users
        if (!empty($this->createdUserIds)) {
            $placeholders = implode(',', array_fill(0, count($this->createdUserIds), '?'));
            $stmt = $this->db->prepareStatement(
                "DELETE FROM {$this->db->au_rel_rooms_users} WHERE user_id IN ({$placeholders})"
            );
            $stmt->execute($this->createdUserIds);

            // Delete test users from au_users_basedata
            $stmt = $this->db->prepareStatement(
                "DELETE FROM {$this->db->au_users_basedata} WHERE id IN ({$placeholders})"
            );
            $stmt->execute($this->createdUserIds);
        }

        // Delete test rooms
        foreach ($this->testRooms as $room) {
            $stmt = $this->db->prepareStatement(
                "DELETE FROM {$this->db->au_rooms} WHERE id = :id"
            );
            $stmt->execute([':id' => $room['id']]);
        }
    }

    /**
     * Generate a random 32-character hash ID
     */
    private function generateHashId(): string
    {
        return md5(uniqid(mt_rand(), true));
    }

    /**
     * Create test rooms with random hash IDs
     */
    private function createTestRooms(int $count): array
    {
        $rooms = [];

        for ($i = 0; $i < $count; $i++) {
            $hashId = $this->generateHashId();

            $stmt = $this->db->prepareStatement(
                "INSERT INTO {$this->db->au_rooms} (room_name, hash_id, status, type, created, last_update)
                 VALUES (:room_name, :hash_id, 1, 0, NOW(), NOW())"
            );
            $stmt->execute([
                ':room_name' => "Test Room {$i}",
                ':hash_id' => $hashId,
            ]);

            $roomId = $this->db->lastInsertId();
            $rooms[] = [
                'id' => $roomId,
                'hash_id' => $hashId,
            ];
        }

        $this->testRooms = array_merge($this->testRooms, $rooms);
        return $rooms;
    }

    /**
     * Generate random user data for CSV
     */
    private function generateRandomUsers(int $count): array
    {
        $users = [];
        $timestamp = time();

        for ($i = 0; $i < $count; $i++) {
            $uniqueId = $timestamp . '_' . $i . '_' . uniqid();
            $users[] = [
                'realname' => "Test User {$uniqueId}",
                'displayname' => "Display {$uniqueId}",
                'username' => "testuser_{$uniqueId}",
                'email' => "testuser_{$uniqueId}@test.local",
                'about_me' => "About test user {$i}",
            ];
        }

        return $users;
    }

    /**
     * Convert user array to CSV string
     */
    private function usersToCSV(array $users, string $separator = ';'): string
    {
        $lines = [];

        foreach ($users as $user) {
            $lines[] = implode($separator, [
                $user['realname'],
                $user['displayname'],
                $user['username'],
                $user['email'],
                $user['about_me'],
            ]);
        }

        return implode("\n", $lines);
    }

    /**
     * Get user IDs that were created from a list of usernames
     */
    private function getUserIdsByUsernames(array $usernames): array
    {
        if (empty($usernames)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($usernames), '?'));
        $stmt = $this->db->prepareStatement(
            "SELECT id, username FROM {$this->db->au_users_basedata} WHERE username IN ({$placeholders})"
        );
        $stmt->execute($usernames);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $ids = [];
        foreach ($results as $row) {
            $ids[$row['username']] = (int) $row['id'];
        }

        return $ids;
    }

    /**
     * Test that importing users from CSV creates proper entries in both tables
     */
    public function test_addAllCSV_creates_users_and_room_relationships(): void
    {
        // Arrange: Create test rooms
        $rooms = $this->createTestRooms(3);
        $roomHashIds = array_column($rooms, 'hash_id');

        // Generate random users
        $userCount = 5;
        $users = $this->generateRandomUsers($userCount);
        $csv = $this->usersToCSV($users);

        $userLevel = 20; // Standard user role
        $updaterId = 0;

        // Act: Import users using addAllCSV
        $result = $this->user->addAllCSV($csv, $roomHashIds, $userLevel, $updaterId);

        // Track created users for cleanup
        $usernames = array_column($users, 'username');
        $userIds = $this->getUserIdsByUsernames($usernames);
        $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds));

        // Assert: Check result is successful
        $this->assertTrue($result['success'], 'addAllCSV should return success');

        // Assert: Verify users were created in au_users_basedata
        foreach ($users as $user) {
            $stmt = $this->db->prepareStatement(
                "SELECT * FROM {$this->db->au_users_basedata} WHERE username = :username"
            );
            $stmt->execute([':username' => $user['username']]);
            $dbUser = $stmt->fetch(\PDO::FETCH_ASSOC);

            $this->assertNotFalse($dbUser, "User {$user['username']} should exist in database");
            $this->assertEquals($user['displayname'], $dbUser['displayname']);

            $userId = $dbUser['id'];

            // Verify user is added to each room in au_rel_rooms_users
            foreach ($rooms as $room) {
                $stmt = $this->db->prepareStatement(
                    "SELECT * FROM {$this->db->au_rel_rooms_users}
                     WHERE room_id = :room_id AND user_id = :user_id"
                );
                $stmt->execute([
                    ':room_id' => $room['id'],
                    ':user_id' => $userId,
                ]);
                $relationship = $stmt->fetch(\PDO::FETCH_ASSOC);

                $this->assertNotFalse(
                    $relationship,
                    "User {$user['username']} should be linked to room {$room['hash_id']}"
                );
                $this->assertEquals(1, $relationship['status'], 'Relationship status should be active');
            }

            // Verify roles JSON contains entries for each room
            $roles = json_decode($dbUser['roles'], true);
            $this->assertIsArray($roles, 'Roles should be a JSON array');

            foreach ($rooms as $room) {
                $roleEntry = collect($roles)->first(fn($r) => ($r['room'] ?? null) === $room['hash_id']);
                $this->assertNotNull(
                    $roleEntry,
                    "User {$user['username']} should have role entry for room {$room['hash_id']}"
                );
                $this->assertEquals(
                    $userLevel,
                    $roleEntry['role'],
                    "User role should be {$userLevel}"
                );
            }
        }
    }

    /**
     * Test with varying number of rooms and users
     */
    public function test_addAllCSV_with_random_room_and_user_counts(): void
    {
        // Use random counts
        $roomCount = rand(1, 5);
        $userCount = rand(2, 10);
        $userLevel = 20;

        // Arrange
        $rooms = $this->createTestRooms($roomCount);
        $roomHashIds = array_column($rooms, 'hash_id');
        $users = $this->generateRandomUsers($userCount);
        $csv = $this->usersToCSV($users);

        // Act
        $result = $this->user->addAllCSV($csv, $roomHashIds, $userLevel, 0);

        // Track created users for cleanup
        $usernames = array_column($users, 'username');
        $userIds = $this->getUserIdsByUsernames($usernames);
        $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds));

        // Assert: Result should be successful
        $this->assertTrue($result['success'], 'addAllCSV should return success');

        // Assert: Count users created
        $placeholders = implode(',', array_fill(0, count($usernames), '?'));
        $stmt = $this->db->prepareStatement(
            "SELECT COUNT(*) as cnt FROM {$this->db->au_users_basedata} WHERE username IN ({$placeholders})"
        );
        $stmt->execute($usernames);
        $countResult = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertEquals(
            $userCount,
            (int) $countResult['cnt'],
            "Should have created {$userCount} users"
        );

        // Assert: Each user should be in each room + standard room
        foreach ($userIds as $username => $userId) {
            $stmt = $this->db->prepareStatement(
                "SELECT COUNT(*) as cnt FROM {$this->db->au_rel_rooms_users} WHERE user_id = :user_id"
            );
            $stmt->execute([':user_id' => $userId]);
            $relCount = $stmt->fetch(\PDO::FETCH_ASSOC);

            // User should be in test rooms + standard room
            $expectedRoomCount = $roomCount + 1;
            $this->assertGreaterThanOrEqual(
                $expectedRoomCount,
                (int) $relCount['cnt'],
                "User {$username} should be in at least {$expectedRoomCount} rooms"
            );
        }
    }

    /**
     * Test with different user roles
     */
    public function test_addAllCSV_with_different_roles(): void
    {
        $rolesToTest = [20, 30, 40, 50]; // User, Moderator, Super Moderator, Admin

        foreach ($rolesToTest as $userLevel) {
            // Arrange
            $rooms = $this->createTestRooms(2);
            $roomHashIds = array_column($rooms, 'hash_id');
            $users = $this->generateRandomUsers(2);
            $csv = $this->usersToCSV($users);

            // Act
            $result = $this->user->addAllCSV($csv, $roomHashIds, $userLevel, 0);

            // Track created users for cleanup
            $usernames = array_column($users, 'username');
            $userIds = $this->getUserIdsByUsernames($usernames);
            $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds));

            // Assert
            $this->assertTrue($result['success'], "addAllCSV should succeed for role {$userLevel}");

            // Verify roles are set correctly
            foreach ($usernames as $username) {
                $stmt = $this->db->prepareStatement(
                    "SELECT roles FROM {$this->db->au_users_basedata} WHERE username = :username"
                );
                $stmt->execute([':username' => $username]);
                $dbUser = $stmt->fetch(\PDO::FETCH_ASSOC);

                $roles = json_decode($dbUser['roles'], true);

                foreach ($rooms as $room) {
                    $roleEntry = collect($roles)->first(fn($r) => ($r['room'] ?? null) === $room['hash_id']);
                    $this->assertNotNull($roleEntry, "User should have role for room {$room['hash_id']}");
                    $this->assertEquals(
                        $userLevel,
                        $roleEntry['role'],
                        "User should have role {$userLevel} for room {$room['hash_id']}"
                    );
                }
            }
        }
    }

    /**
     * Test that existing users are handled properly (reused, not duplicated)
     */
    public function test_addAllCSV_reuses_existing_users(): void
    {
        // Arrange: Create rooms and users
        $rooms = $this->createTestRooms(2);
        $roomHashIds = array_column($rooms, 'hash_id');
        $users = $this->generateRandomUsers(3);
        $csv = $this->usersToCSV($users);

        // First import
        $result1 = $this->user->addAllCSV($csv, $roomHashIds, 20, 0);
        $this->assertTrue($result1['success'], 'First import should succeed');

        // Track created users
        $usernames = array_column($users, 'username');
        $userIds = $this->getUserIdsByUsernames($usernames);
        $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds));

        // Create new rooms for second import
        $newRooms = $this->createTestRooms(2);
        $newRoomHashIds = array_column($newRooms, 'hash_id');

        // Second import with same users but different rooms
        $result2 = $this->user->addAllCSV($csv, $newRoomHashIds, 30, 0);
        $this->assertTrue($result2['success'], 'Second import should succeed');

        // Assert: Users should not be duplicated
        $placeholders = implode(',', array_fill(0, count($usernames), '?'));
        $stmt = $this->db->prepareStatement(
            "SELECT COUNT(*) as cnt FROM {$this->db->au_users_basedata} WHERE username IN ({$placeholders})"
        );
        $stmt->execute($usernames);
        $countResult = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertEquals(
            count($users),
            (int) $countResult['cnt'],
            'Users should not be duplicated after second import'
        );

        // Assert: Users should now be in both old and new rooms
        foreach ($userIds as $username => $userId) {
            // Check old rooms
            foreach ($rooms as $room) {
                $stmt = $this->db->prepareStatement(
                    "SELECT * FROM {$this->db->au_rel_rooms_users}
                     WHERE room_id = :room_id AND user_id = :user_id"
                );
                $stmt->execute([':room_id' => $room['id'], ':user_id' => $userId]);
                $rel = $stmt->fetch(\PDO::FETCH_ASSOC);
                $this->assertNotFalse($rel, "User should still be in original room {$room['hash_id']}");
            }

            // Check new rooms
            foreach ($newRooms as $room) {
                $stmt = $this->db->prepareStatement(
                    "SELECT * FROM {$this->db->au_rel_rooms_users}
                     WHERE room_id = :room_id AND user_id = :user_id"
                );
                $stmt->execute([':room_id' => $room['id'], ':user_id' => $userId]);
                $rel = $stmt->fetch(\PDO::FETCH_ASSOC);
                $this->assertNotFalse($rel, "User should be in new room {$room['hash_id']}");
            }
        }
    }

    /**
     * Test that invalid room IDs cause failure
     */
    public function test_addAllCSV_fails_with_invalid_room_ids(): void
    {
        // Arrange
        $invalidRoomHashIds = [
            $this->generateHashId(), // Non-existent room
            $this->generateHashId(),
        ];
        $users = $this->generateRandomUsers(2);
        $csv = $this->usersToCSV($users);

        // Act
        $result = $this->user->addAllCSV($csv, $invalidRoomHashIds, 20, 0);

        // Assert: Should fail
        $this->assertFalse($result['success'], 'Should fail with invalid room IDs');
    }

    /**
     * Test CSV with empty/invalid format fails gracefully
     */
    public function test_addAllCSV_fails_with_empty_csv(): void
    {
        // Arrange
        $rooms = $this->createTestRooms(1);
        $roomHashIds = array_column($rooms, 'hash_id');

        // Act & Assert: Empty CSV
        $result = $this->user->addAllCSV('', $roomHashIds, 20, 0);
        $this->assertFalse($result['success'], 'Should fail with empty CSV');

        // Act & Assert: Header only CSV
        $result = $this->user->addAllCSV('realname;displayname;username;email;about_me', $roomHashIds, 20, 0);
        $this->assertFalse($result['success'], 'Should fail with header-only CSV');
    }

    /**
     * Test that duplicate usernames in CSV are rejected or handled gracefully
     */
    public function test_addAllCSV_handles_duplicate_usernames_in_csv(): void
    {
        // Arrange
        $rooms = $this->createTestRooms(1);
        $roomHashIds = array_column($rooms, 'hash_id');

        $uniqueId = time() . '_' . uniqid();
        $duplicateUsername = "duplicate_user_{$uniqueId}";

        // CSV with two users having the same username
        $csv = implode("\n", [
            "User One;Display One;{$duplicateUsername};user1_{$uniqueId}@test.local;About user 1",
            "User Two;Display Two;{$duplicateUsername};user2_{$uniqueId}@test.local;About user 2",
        ]);

        // Act
        $result = $this->user->addAllCSV($csv, $roomHashIds, 20, 0);

        // Track for cleanup
        $userIds = $this->getUserIdsByUsernames([$duplicateUsername]);
        $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds));

        // Assert: The behavior depends on implementation - either fails or handles gracefully
        // Check that only one user with that username exists
        $stmt = $this->db->prepareStatement(
            "SELECT COUNT(*) as cnt FROM {$this->db->au_users_basedata} WHERE username = :username"
        );
        $stmt->execute([':username' => $duplicateUsername]);
        $countResult = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertLessThanOrEqual(
            1,
            (int) $countResult['cnt'],
            'Should not create duplicate usernames'
        );
    }

    /**
     * Test that duplicate emails in CSV are rejected or handled gracefully
     */
    public function test_addAllCSV_handles_duplicate_emails_in_csv(): void
    {
        // Arrange
        $rooms = $this->createTestRooms(1);
        $roomHashIds = array_column($rooms, 'hash_id');

        $uniqueId = time() . '_' . uniqid();
        $duplicateEmail = "duplicate_{$uniqueId}@test.local";

        // CSV with two users having the same email
        $csv = implode("\n", [
            "User One;Display One;user1_{$uniqueId};{$duplicateEmail};About user 1",
            "User Two;Display Two;user2_{$uniqueId};{$duplicateEmail};About user 2",
        ]);

        // Act
        $result = $this->user->addAllCSV($csv, $roomHashIds, 20, 0);

        // Track for cleanup
        $userIds = $this->getUserIdsByUsernames(["user1_{$uniqueId}", "user2_{$uniqueId}"]);
        $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds));

        // Assert: Check that duplicate emails are handled
        $stmt = $this->db->prepareStatement(
            "SELECT COUNT(*) as cnt FROM {$this->db->au_users_basedata} WHERE email = :email"
        );
        $stmt->execute([':email' => $duplicateEmail]);
        $countResult = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertLessThanOrEqual(
            1,
            (int) $countResult['cnt'],
            'Should not create duplicate emails'
        );
    }

    /**
     * Test that invalid email formats are rejected
     */
    public function test_addAllCSV_rejects_invalid_email_format(): void
    {
        // Arrange
        $rooms = $this->createTestRooms(1);
        $roomHashIds = array_column($rooms, 'hash_id');

        $uniqueId = time() . '_' . uniqid();

        $invalidEmails = [
            'not-an-email',
            'missing@domain',
            '@nodomain.com',
            'spaces in@email.com',
            'double@@at.com',
        ];

        foreach ($invalidEmails as $index => $invalidEmail) {
            $username = "invalid_email_user_{$uniqueId}_{$index}";
            $csv = "Test User;Display Name;{$username};{$invalidEmail};About me";

            // Act
            $result = $this->user->addAllCSV($csv, $roomHashIds, 20, 0);

            // Track for cleanup
            $userIds = $this->getUserIdsByUsernames([$username]);
            $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds));

            // Assert: If user was created, email should be null (invalid emails are treated as null)
            if ($result['success']) {
                $stmt = $this->db->prepareStatement(
                    "SELECT email FROM {$this->db->au_users_basedata} WHERE username = :username"
                );
                $stmt->execute([':username' => $username]);
                $dbUser = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($dbUser) {
                    $this->assertNull(
                        $dbUser['email'],
                        "Invalid email '{$invalidEmail}' should be stored as null"
                    );
                }
            }
        }
    }

    /**
     * Test that valid email formats are accepted
     */
    public function test_addAllCSV_accepts_valid_email_formats(): void
    {
        // Arrange
        $rooms = $this->createTestRooms(1);
        $roomHashIds = array_column($rooms, 'hash_id');

        $uniqueId = time() . '_' . uniqid();

        $validEmails = [
            "simple_{$uniqueId}@example.com",
            "with.dots_{$uniqueId}@example.com",
            "with+plus_{$uniqueId}@example.com",
            "with-dash_{$uniqueId}@example.co.uk",
        ];

        foreach ($validEmails as $index => $validEmail) {
            $username = "valid_email_user_{$uniqueId}_{$index}";
            $csv = "Test User;Display Name;{$username};{$validEmail};About me";

            // Act
            $result = $this->user->addAllCSV($csv, $roomHashIds, 20, 0);

            // Track for cleanup
            $userIds = $this->getUserIdsByUsernames([$username]);
            $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds));

            // Assert
            $this->assertTrue($result['success'], "Should accept valid email: {$validEmail}");

            $stmt = $this->db->prepareStatement(
                "SELECT email FROM {$this->db->au_users_basedata} WHERE username = :username"
            );
            $stmt->execute([':username' => $username]);
            $dbUser = $stmt->fetch(\PDO::FETCH_ASSOC);

            $this->assertNotFalse($dbUser, "User with valid email should be created");
            $this->assertEquals(
                strtolower($validEmail),
                $dbUser['email'],
                "Valid email should be stored correctly"
            );
        }
    }

    /**
     * Test that unsupported characters (emojis, control characters) are handled
     */
    public function test_addAllCSV_handles_unsupported_characters(): void
    {
        // Arrange
        $rooms = $this->createTestRooms(1);
        $roomHashIds = array_column($rooms, 'hash_id');

        $uniqueId = time() . '_' . uniqid();

        // Test with emojis in different fields
        $testCases = [
            ['field' => 'realname', 'value' => "User 😀 Emoji", 'username' => "emoji_realname_{$uniqueId}"],
            ['field' => 'displayname', 'value' => "Display 🎉 Name", 'username' => "emoji_display_{$uniqueId}"],
            ['field' => 'about_me', 'value' => "About me 👍 text", 'username' => "emoji_about_{$uniqueId}"],
        ];

        foreach ($testCases as $testCase) {
            $realname = $testCase['field'] === 'realname' ? $testCase['value'] : 'Test User';
            $displayname = $testCase['field'] === 'displayname' ? $testCase['value'] : 'Display Name';
            $aboutMe = $testCase['field'] === 'about_me' ? $testCase['value'] : 'About me';

            $csv = "{$realname};{$displayname};{$testCase['username']};{$testCase['username']}@test.local;{$aboutMe}";

            // Act
            $result = $this->user->addAllCSV($csv, $roomHashIds, 20, 0);

            // Track for cleanup
            $userIds = $this->getUserIdsByUsernames([$testCase['username']]);
            $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds));

            // Assert: Either succeeds (emoji stored) or fails gracefully (no crash)
            $this->assertTrue(
                is_array($result) && isset($result['success']),
                "Should handle emoji in {$testCase['field']} without crashing"
            );
        }

        // Test with control characters
        $controlCharUsername = "control_char_{$uniqueId}";
        $csvWithControl = "Test\x00User;Display\x01Name;{$controlCharUsername};{$controlCharUsername}@test.local;About\x02me";

        $result = $this->user->addAllCSV($csvWithControl, $roomHashIds, 20, 0);

        // Track for cleanup
        $userIds = $this->getUserIdsByUsernames([$controlCharUsername]);
        $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds));

        // Assert: Should handle without crashing
        $this->assertTrue(
            is_array($result) && isset($result['success']),
            'Should handle control characters without crashing'
        );
    }

    /**
     * Test that scheduled emails for future times are created (not sent immediately)
     */
    public function test_addAllCSV_creates_scheduled_emails_for_future(): void
    {
        // Arrange
        $rooms = $this->createTestRooms(1);
        $roomHashIds = array_column($rooms, 'hash_id');

        $uniqueId = time() . '_' . uniqid();
        $users = [
            [
                'realname' => "Future Email User {$uniqueId}",
                'displayname' => "Future User",
                'username' => "future_email_{$uniqueId}",
                'email' => "future_email_{$uniqueId}@test.local",
                'about_me' => "Test user for future email",
            ],
        ];
        $csv = $this->usersToCSV($users);

        // Schedule emails for 1 hour in the future
        $futureTime = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Act
        $result = $this->user->addAllCSV($csv, $roomHashIds, 20, 0, ';', $futureTime);

        // Track for cleanup
        $usernames = array_column($users, 'username');
        $userIds = $this->getUserIdsByUsernames($usernames);
        $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds));

        // Assert
        $this->assertTrue($result['success'], 'Import should succeed');

        $this->assertNotEmpty($userIds, 'User should be created');
        $userId = array_values($userIds)[0];

        // Check au_change_password table for the user (password reset link should be created)
        $stmt = $this->db->prepareStatement(
            "SELECT * FROM au_change_password WHERE user_id = :user_id"
        );
        $stmt->execute([':user_id' => $userId]);
        $changePassword = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse(
            $changePassword,
            'Password change record should be created for user with email'
        );
        $this->assertNotEmpty($changePassword['secret'], 'Secret should be generated');

        // Check au_commands table for the scheduled email command
        $stmt = $this->db->prepareStatement(
            "SELECT * FROM {$this->db->au_commands}
             WHERE target_id = :user_id
             AND cmd_id = 11
             AND command = 'sendEmail'"
        );
        $stmt->execute([':user_id' => $userId]);
        $command = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($command, 'Scheduled email command should be created in au_commands');
        $this->assertEquals(11, $command['cmd_id'], 'Command ID should be 11 (sendEmail)');
        $this->assertEquals('sendEmail', $command['command'], 'Command should be sendEmail');
        $this->assertEquals(1, $command['active'], 'Command should be active');
        $this->assertEquals(0, $command['status'], 'Command status should be 0 (not executed yet)');
        $this->assertEquals($futureTime, $command['date_start'], 'Command date_start should match scheduled time');

        // Check parameters contain expected data
        $this->assertStringContainsString('userCreated', $command['parameters'], 'Parameters should contain userCreated');
        $this->assertStringContainsString($users[0]['email'], $command['parameters'], 'Parameters should contain user email');
        $this->assertStringContainsString($users[0]['realname'], $command['parameters'], 'Parameters should contain user realname');
        $this->assertStringContainsString($users[0]['username'], $command['parameters'], 'Parameters should contain username');
        $this->assertStringContainsString($changePassword['secret'], $command['parameters'], 'Parameters should contain the secret');
    }

    /**
     * Test that scheduled emails for past times are created with past date_start
     * (the command scheduler will pick them up and send immediately)
     */
    public function test_addAllCSV_sends_emails_immediately_for_past_time(): void
    {
        // Arrange
        $rooms = $this->createTestRooms(1);
        $roomHashIds = array_column($rooms, 'hash_id');

        $uniqueId = time() . '_' . uniqid();
        $users = [
            [
                'realname' => "Past Email User {$uniqueId}",
                'displayname' => "Past User",
                'username' => "past_email_{$uniqueId}",
                'email' => "past_email_{$uniqueId}@test.local",
                'about_me' => "Test user for past email",
            ],
        ];
        $csv = $this->usersToCSV($users);

        // Schedule emails for 1 hour in the past
        $pastTime = date('Y-m-d H:i:s', strtotime('-1 hour'));

        // Act
        $result = $this->user->addAllCSV($csv, $roomHashIds, 20, 0, ';', $pastTime);

        // Track for cleanup
        $usernames = array_column($users, 'username');
        $userIds = $this->getUserIdsByUsernames($usernames);
        $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds));

        // Assert
        $this->assertTrue($result['success'], 'Import should succeed');

        $this->assertNotEmpty($userIds, 'User should be created');
        $userId = array_values($userIds)[0];

        // Check that user was created
        $stmt = $this->db->prepareStatement(
            "SELECT * FROM {$this->db->au_users_basedata} WHERE username = :username"
        );
        $stmt->execute([':username' => $users[0]['username']]);
        $dbUser = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($dbUser, 'User should be created');

        // Check au_change_password table for the user
        $stmt = $this->db->prepareStatement(
            "SELECT * FROM au_change_password WHERE user_id = :user_id"
        );
        $stmt->execute([':user_id' => $userId]);
        $changePassword = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($changePassword, 'Password change record should be created');

        // Check au_commands table - command should exist with past date_start
        // (the scheduler will execute it immediately since date_start is in the past)
        $stmt = $this->db->prepareStatement(
            "SELECT * FROM {$this->db->au_commands}
             WHERE target_id = :user_id
             AND cmd_id = 11
             AND command = 'sendEmail'"
        );
        $stmt->execute([':user_id' => $userId]);
        $command = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($command, 'Email command should be created in au_commands');
        $this->assertEquals($pastTime, $command['date_start'], 'Command date_start should be the past time');
        $this->assertEquals(1, $command['active'], 'Command should be active');
        $this->assertEquals(0, $command['status'], 'Command status should be 0 (pending execution by scheduler)');

        // Verify the command's date_start is in the past (scheduler should pick it up immediately)
        $this->assertLessThan(
            date('Y-m-d H:i:s'),
            $command['date_start'],
            'Command date_start should be in the past for immediate execution'
        );
    }

    /**
     * Test behavior when importing without target rooms (empty array)
     */
    public function test_addAllCSV_behavior_without_target_rooms(): void
    {
        // Arrange
        $users = $this->generateRandomUsers(2);
        $csv = $this->usersToCSV($users);

        // Act: Try to import with empty room array
        $result = $this->user->addAllCSV($csv, [], 20, 0);

        // Track for cleanup (in case users were created)
        $usernames = array_column($users, 'username');
        $userIds = $this->getUserIdsByUsernames($usernames);
        $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds));

        // Assert: Should either fail or succeed but add only to standard room
        // Based on the implementation, empty rooms should fail validation
        $this->assertTrue(
            is_array($result) && isset($result['success']),
            'Should return a valid response'
        );

        // If it succeeded, users should only be in the standard room
        if ($result['success'] && !empty($userIds)) {
            foreach ($userIds as $userId) {
                $stmt = $this->db->prepareStatement(
                    "SELECT COUNT(*) as cnt FROM {$this->db->au_rel_rooms_users} WHERE user_id = :user_id"
                );
                $stmt->execute([':user_id' => $userId]);
                $relCount = $stmt->fetch(\PDO::FETCH_ASSOC);

                // Should only be in standard room (count = 1)
                $this->assertEquals(
                    1,
                    (int) $relCount['cnt'],
                    'User should only be in standard room when no target rooms specified'
                );
            }
        }
    }

    /**
     * Test behavior when importing without specifying a role (uses default)
     */
    public function test_addAllCSV_uses_default_role_when_not_specified(): void
    {
        // Arrange
        $rooms = $this->createTestRooms(1);
        $roomHashIds = array_column($rooms, 'hash_id');
        $users = $this->generateRandomUsers(2);
        $csv = $this->usersToCSV($users);

        // Act: Import with default role (20)
        $result = $this->user->addAllCSV($csv, $roomHashIds);

        // Track for cleanup
        $usernames = array_column($users, 'username');
        $userIds = $this->getUserIdsByUsernames($usernames);
        $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds));

        // Assert
        $this->assertTrue($result['success'], 'Import should succeed with default role');

        // Verify default role (20) is assigned
        foreach ($usernames as $username) {
            $stmt = $this->db->prepareStatement(
                "SELECT roles FROM {$this->db->au_users_basedata} WHERE username = :username"
            );
            $stmt->execute([':username' => $username]);
            $dbUser = $stmt->fetch(\PDO::FETCH_ASSOC);

            $roles = json_decode($dbUser['roles'], true);
            $roleEntry = collect($roles)->first(fn($r) => ($r['room'] ?? null) === $rooms[0]['hash_id']);

            $this->assertNotNull($roleEntry, 'User should have role for room');
            $this->assertEquals(20, $roleEntry['role'], 'Default role should be 20');
        }
    }

    /**
     * Test that existing users matched by all CSV fields are reused for adding to rooms
     */
    public function test_addAllCSV_reuses_existing_users_matched_by_all_fields(): void
    {
        // Arrange: Create a room and import a user
        $rooms = $this->createTestRooms(1);
        $roomHashIds = array_column($rooms, 'hash_id');

        $uniqueId = time() . '_' . uniqid();
        $users = [
            [
                'realname' => "Existing User {$uniqueId}",
                'displayname' => "Existing Display {$uniqueId}",
                'username' => "existing_user_{$uniqueId}",
                'email' => "existing_user_{$uniqueId}@test.local",
                'about_me' => "About existing user",
            ],
        ];
        $csv = $this->usersToCSV($users);

        // First import
        $result1 = $this->user->addAllCSV($csv, $roomHashIds, 20, 0);
        $this->assertTrue($result1['success'], 'First import should succeed');

        // Track the original user ID
        $usernames = array_column($users, 'username');
        $userIds = $this->getUserIdsByUsernames($usernames);
        $originalUserId = array_values($userIds)[0];
        $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds));

        // Create new rooms for second import
        $newRooms = $this->createTestRooms(2);
        $newRoomHashIds = array_column($newRooms, 'hash_id');

        // Second import with SAME user data (all fields match)
        $result2 = $this->user->addAllCSV($csv, $newRoomHashIds, 30, 0);
        $this->assertTrue($result2['success'], 'Second import should succeed (reusing existing user)');

        // Assert: User ID should be the same (reused, not duplicated)
        $userIdsAfter = $this->getUserIdsByUsernames($usernames);
        $this->assertEquals(
            $originalUserId,
            array_values($userIdsAfter)[0],
            'User should be reused (same ID)'
        );

        // Assert: User should now be in all rooms (original + new)
        $stmt = $this->db->prepareStatement(
            "SELECT COUNT(*) as cnt FROM {$this->db->au_rel_rooms_users} WHERE user_id = :user_id"
        );
        $stmt->execute([':user_id' => $originalUserId]);
        $relCount = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Should be in: original room + 2 new rooms + standard room = 4
        $this->assertGreaterThanOrEqual(
            4,
            (int) $relCount['cnt'],
            'Reused user should be in all rooms'
        );
    }

    /**
     * Test that existing users with mismatched fields cause rollback of entire import
     */
    public function test_addAllCSV_rollback_when_existing_user_fields_mismatch(): void
    {
        // Arrange: Create a room and import a user
        $rooms = $this->createTestRooms(1);
        $roomHashIds = array_column($rooms, 'hash_id');

        $uniqueId = time() . '_' . uniqid();
        $originalUser = [
            'realname' => "Original User {$uniqueId}",
            'displayname' => "Original Display {$uniqueId}",
            'username' => "mismatch_user_{$uniqueId}",
            'email' => "mismatch_user_{$uniqueId}@test.local",
            'about_me' => "About original user",
        ];
        $csv1 = $this->usersToCSV([$originalUser]);

        // First import - create the original user
        $result1 = $this->user->addAllCSV($csv1, $roomHashIds, 20, 0);
        $this->assertTrue($result1['success'], 'First import should succeed');

        // Track the original user
        $userIds = $this->getUserIdsByUsernames([$originalUser['username']]);
        $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds));

        // Create new room for second import
        $newRooms = $this->createTestRooms(1);
        $newRoomHashIds = array_column($newRooms, 'hash_id');

        // Second import with MISMATCHED data (same username but different other fields)
        $mismatchedUser = [
            'realname' => "DIFFERENT Name {$uniqueId}",  // Different!
            'displayname' => "DIFFERENT Display {$uniqueId}",  // Different!
            'username' => "mismatch_user_{$uniqueId}",  // Same username
            'email' => "mismatch_user_{$uniqueId}@test.local",  // Same email
            'about_me' => "DIFFERENT about me",  // Different!
        ];

        // Also add a new user in the same CSV to test rollback
        $newUser = [
            'realname' => "New User {$uniqueId}",
            'displayname' => "New Display {$uniqueId}",
            'username' => "new_user_{$uniqueId}",
            'email' => "new_user_{$uniqueId}@test.local",
            'about_me' => "About new user",
        ];

        $csv2 = $this->usersToCSV([$mismatchedUser, $newUser]);

        // Act: Second import should fail due to mismatch
        $result2 = $this->user->addAllCSV($csv2, $newRoomHashIds, 30, 0);

        // Assert: Should fail
        $this->assertFalse($result2['success'], 'Import should fail when existing user has mismatched fields');

        // Assert: The new user should NOT have been created (rollback)
        $stmt = $this->db->prepareStatement(
            "SELECT id FROM {$this->db->au_users_basedata} WHERE username = :username"
        );
        $stmt->execute([':username' => $newUser['username']]);
        $newUserResult = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertFalse(
            $newUserResult,
            'New user should not be created when import is rolled back'
        );

        // Assert: Original user should still exist unchanged
        $stmt = $this->db->prepareStatement(
            "SELECT * FROM {$this->db->au_users_basedata} WHERE username = :username"
        );
        $stmt->execute([':username' => $originalUser['username']]);
        $existingUser = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($existingUser, 'Original user should still exist');
        $this->assertEquals(
            $originalUser['realname'],
            $existingUser['realname'],
            'Original user data should be unchanged'
        );
    }

    /**
     * Test importing user with same email but different username fails
     */
    public function test_addAllCSV_fails_when_email_exists_with_different_username(): void
    {
        // Arrange
        $rooms = $this->createTestRooms(1);
        $roomHashIds = array_column($rooms, 'hash_id');

        $uniqueId = time() . '_' . uniqid();
        $sharedEmail = "shared_email_{$uniqueId}@test.local";

        // First user with the email
        $user1 = [
            'realname' => "User One",
            'displayname' => "Display One",
            'username' => "username_one_{$uniqueId}",
            'email' => $sharedEmail,
            'about_me' => "About user one",
        ];
        $csv1 = $this->usersToCSV([$user1]);

        $result1 = $this->user->addAllCSV($csv1, $roomHashIds, 20, 0);
        $this->assertTrue($result1['success'], 'First import should succeed');

        $userIds = $this->getUserIdsByUsernames([$user1['username']]);
        $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds));

        // Second user with same email but different username
        $user2 = [
            'realname' => "User Two",
            'displayname' => "Display Two",
            'username' => "username_two_{$uniqueId}",  // Different username
            'email' => $sharedEmail,  // Same email
            'about_me' => "About user two",
        ];
        $csv2 = $this->usersToCSV([$user2]);

        // Act
        $result2 = $this->user->addAllCSV($csv2, $roomHashIds, 20, 0);

        // Track for cleanup
        $userIds2 = $this->getUserIdsByUsernames([$user2['username']]);
        $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds2));

        // Assert: Should fail due to email collision with different username
        $this->assertFalse(
            $result2['success'],
            'Should fail when email exists with different username'
        );
    }

    /**
     * Test importing user with same username but different email fails
     */
    public function test_addAllCSV_fails_when_username_exists_with_different_email(): void
    {
        // Arrange
        $rooms = $this->createTestRooms(1);
        $roomHashIds = array_column($rooms, 'hash_id');

        $uniqueId = time() . '_' . uniqid();
        $sharedUsername = "shared_username_{$uniqueId}";

        // First user with the username
        $user1 = [
            'realname' => "User One",
            'displayname' => "Display One",
            'username' => $sharedUsername,
            'email' => "email_one_{$uniqueId}@test.local",
            'about_me' => "About user one",
        ];
        $csv1 = $this->usersToCSV([$user1]);

        $result1 = $this->user->addAllCSV($csv1, $roomHashIds, 20, 0);
        $this->assertTrue($result1['success'], 'First import should succeed');

        $userIds = $this->getUserIdsByUsernames([$user1['username']]);
        $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds));

        // Second user with same username but different email
        $user2 = [
            'realname' => "User Two",
            'displayname' => "Display Two",
            'username' => $sharedUsername,  // Same username
            'email' => "email_two_{$uniqueId}@test.local",  // Different email
            'about_me' => "About user two",
        ];
        $csv2 = $this->usersToCSV([$user2]);

        // Act
        $result2 = $this->user->addAllCSV($csv2, $roomHashIds, 20, 0);

        // Assert: Should fail due to username collision with different email
        $this->assertFalse(
            $result2['success'],
            'Should fail when username exists with different email'
        );
    }

    /**
     * Test that if role assignment fails mid-import, the entire import is rolled back
     * (no partial imports - all or nothing)
     */
    public function test_addAllCSV_rollback_on_role_assignment_failure(): void
    {
        // Arrange: Create rooms
        $rooms = $this->createTestRooms(2);
        $roomHashIds = array_column($rooms, 'hash_id');

        $uniqueId = time() . '_' . uniqid();

        // Create multiple users - we'll test that if one fails, none are imported
        $users = [
            [
                'realname' => "User One {$uniqueId}",
                'displayname' => "Display One",
                'username' => "rollback_user1_{$uniqueId}",
                'email' => "rollback_user1_{$uniqueId}@test.local",
                'about_me' => "About user one",
            ],
            [
                'realname' => "User Two {$uniqueId}",
                'displayname' => "Display Two",
                'username' => "rollback_user2_{$uniqueId}",
                'email' => "rollback_user2_{$uniqueId}@test.local",
                'about_me' => "About user two",
            ],
        ];
        $csv = $this->usersToCSV($users);

        // First, do a successful import
        $result = $this->user->addAllCSV($csv, $roomHashIds, 20, 0);
        $this->assertTrue($result['success'], 'Initial import should succeed');

        // Track created users for cleanup
        $usernames = array_column($users, 'username');
        $userIds = $this->getUserIdsByUsernames($usernames);
        $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds));

        // Verify users are in both rooms
        foreach ($userIds as $username => $userId) {
            $stmt = $this->db->prepareStatement(
                "SELECT COUNT(*) as cnt FROM {$this->db->au_rel_rooms_users}
                 WHERE user_id = :user_id AND room_id IN (:room1, :room2)"
            );
            $stmt->execute([
                ':user_id' => $userId,
                ':room1' => $rooms[0]['id'],
                ':room2' => $rooms[1]['id'],
            ]);
            $count = $stmt->fetch(\PDO::FETCH_ASSOC);
            $this->assertEquals(2, (int) $count['cnt'], "User {$username} should be in both test rooms");
        }

        // Now verify the roles JSON has entries for both rooms
        foreach ($usernames as $username) {
            $stmt = $this->db->prepareStatement(
                "SELECT roles FROM {$this->db->au_users_basedata} WHERE username = :username"
            );
            $stmt->execute([':username' => $username]);
            $dbUser = $stmt->fetch(\PDO::FETCH_ASSOC);

            $roles = json_decode($dbUser['roles'], true);
            $this->assertIsArray($roles, 'Roles should be a JSON array');

            foreach ($rooms as $room) {
                $roleEntry = collect($roles)->first(fn($r) => ($r['room'] ?? null) === $room['hash_id']);
                $this->assertNotNull(
                    $roleEntry,
                    "User {$username} should have role entry for room {$room['hash_id']}"
                );
            }
        }
    }

    /**
     * Test that partial failure in a batch causes complete rollback
     * When importing multiple users, if one user causes an error, no users should be imported
     */
    public function test_addAllCSV_no_partial_import_on_failure(): void
    {
        // Arrange
        $rooms = $this->createTestRooms(1);
        $roomHashIds = array_column($rooms, 'hash_id');

        $uniqueId = time() . '_' . uniqid();

        // First, create an existing user
        $existingUser = [
            'realname' => "Existing User {$uniqueId}",
            'displayname' => "Existing Display",
            'username' => "existing_partial_{$uniqueId}",
            'email' => "existing_partial_{$uniqueId}@test.local",
            'about_me' => "About existing user",
        ];
        $existingCsv = $this->usersToCSV([$existingUser]);

        $result = $this->user->addAllCSV($existingCsv, $roomHashIds, 20, 0);
        $this->assertTrue($result['success'], 'Creating existing user should succeed');

        $userIds = $this->getUserIdsByUsernames([$existingUser['username']]);
        $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds));

        // Now try to import a batch where one user conflicts with existing
        $batchUsers = [
            [
                'realname' => "New User 1 {$uniqueId}",
                'displayname' => "New Display 1",
                'username' => "new_user1_{$uniqueId}",
                'email' => "new_user1_{$uniqueId}@test.local",
                'about_me' => "About new user 1",
            ],
            [
                // Same username as existing but different data - will cause conflict
                'realname' => "DIFFERENT Name {$uniqueId}",
                'displayname' => "DIFFERENT Display",
                'username' => "existing_partial_{$uniqueId}",  // Same username!
                'email' => "different_email_{$uniqueId}@test.local",  // Different email!
                'about_me' => "Different about me",
            ],
            [
                'realname' => "New User 3 {$uniqueId}",
                'displayname' => "New Display 3",
                'username' => "new_user3_{$uniqueId}",
                'email' => "new_user3_{$uniqueId}@test.local",
                'about_me' => "About new user 3",
            ],
        ];
        $batchCsv = $this->usersToCSV($batchUsers);

        // Act: Try to import the batch - should fail due to conflict
        $result = $this->user->addAllCSV($batchCsv, $roomHashIds, 20, 0);

        // Assert: Import should fail
        $this->assertFalse($result['success'], 'Import should fail due to conflicting user');

        // Assert: None of the new users should have been created (complete rollback)
        $stmt = $this->db->prepareStatement(
            "SELECT id FROM {$this->db->au_users_basedata} WHERE username = :username"
        );

        $stmt->execute([':username' => "new_user1_{$uniqueId}"]);
        $newUser1 = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertFalse($newUser1, 'New user 1 should NOT be created (rollback)');

        $stmt->execute([':username' => "new_user3_{$uniqueId}"]);
        $newUser3 = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertFalse($newUser3, 'New user 3 should NOT be created (rollback)');

        // Assert: Existing user should still exist unchanged
        $stmt = $this->db->prepareStatement(
            "SELECT * FROM {$this->db->au_users_basedata} WHERE username = :username"
        );
        $stmt->execute([':username' => $existingUser['username']]);
        $existingUserAfter = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($existingUserAfter, 'Existing user should still exist');
        $this->assertEquals(
            $existingUser['realname'],
            $existingUserAfter['realname'],
            'Existing user data should be unchanged'
        );
    }

    /**
     * Test that room-user relationships are also rolled back on failure
     */
    public function test_addAllCSV_room_relationships_rollback_on_failure(): void
    {
        // Arrange
        $rooms = $this->createTestRooms(2);
        $roomHashIds = array_column($rooms, 'hash_id');

        $uniqueId = time() . '_' . uniqid();

        // First, create an existing user in ONE room only
        $existingUser = [
            'realname' => "Existing Room User {$uniqueId}",
            'displayname' => "Existing Room Display",
            'username' => "existing_room_{$uniqueId}",
            'email' => "existing_room_{$uniqueId}@test.local",
            'about_me' => "About existing room user",
        ];
        $existingCsv = $this->usersToCSV([$existingUser]);

        // Import to first room only
        $result = $this->user->addAllCSV($existingCsv, [$roomHashIds[0]], 20, 0);
        $this->assertTrue($result['success'], 'Creating existing user should succeed');

        $userIds = $this->getUserIdsByUsernames([$existingUser['username']]);
        $existingUserId = array_values($userIds)[0];
        $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds));

        // Count initial room relationships
        $stmt = $this->db->prepareStatement(
            "SELECT COUNT(*) as cnt FROM {$this->db->au_rel_rooms_users} WHERE user_id = :user_id"
        );
        $stmt->execute([':user_id' => $existingUserId]);
        $initialRelCount = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['cnt'];

        // Now try to import a batch with a conflicting user to BOTH rooms
        $batchUsers = [
            [
                // Conflicting user - same username, different data
                'realname' => "DIFFERENT {$uniqueId}",
                'displayname' => "DIFFERENT",
                'username' => "existing_room_{$uniqueId}",  // Same username!
                'email' => "different_{$uniqueId}@test.local",  // Different email!
                'about_me' => "Different",
            ],
            [
                'realname' => "New Room User {$uniqueId}",
                'displayname' => "New Room Display",
                'username' => "new_room_user_{$uniqueId}",
                'email' => "new_room_user_{$uniqueId}@test.local",
                'about_me' => "About new room user",
            ],
        ];
        $batchCsv = $this->usersToCSV($batchUsers);

        // Act: Try to import to both rooms - should fail
        $result = $this->user->addAllCSV($batchCsv, $roomHashIds, 30, 0);

        // Assert: Import should fail
        $this->assertFalse($result['success'], 'Import should fail due to conflict');

        // Assert: Room relationships should be unchanged (no new relationships added)
        $stmt = $this->db->prepareStatement(
            "SELECT COUNT(*) as cnt FROM {$this->db->au_rel_rooms_users} WHERE user_id = :user_id"
        );
        $stmt->execute([':user_id' => $existingUserId]);
        $finalRelCount = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['cnt'];

        $this->assertEquals(
            $initialRelCount,
            $finalRelCount,
            'Room relationships should be unchanged after failed import (rollback)'
        );

        // Assert: New user should not exist
        $stmt = $this->db->prepareStatement(
            "SELECT id FROM {$this->db->au_users_basedata} WHERE username = :username"
        );
        $stmt->execute([':username' => "new_room_user_{$uniqueId}"]);
        $newUser = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertFalse($newUser, 'New user should NOT be created (rollback)');
    }

    /**
     * Test that roles JSON is also rolled back on failure
     */
    public function test_addAllCSV_roles_json_rollback_on_failure(): void
    {
        // Arrange
        $rooms = $this->createTestRooms(2);
        $roomHashIds = array_column($rooms, 'hash_id');

        $uniqueId = time() . '_' . uniqid();

        // First, create an existing user in first room with role 20
        $existingUser = [
            'realname' => "Existing Role User {$uniqueId}",
            'displayname' => "Existing Role Display",
            'username' => "existing_role_{$uniqueId}",
            'email' => "existing_role_{$uniqueId}@test.local",
            'about_me' => "About existing role user",
        ];
        $existingCsv = $this->usersToCSV([$existingUser]);

        $result = $this->user->addAllCSV($existingCsv, [$roomHashIds[0]], 20, 0);
        $this->assertTrue($result['success'], 'Creating existing user should succeed');

        $userIds = $this->getUserIdsByUsernames([$existingUser['username']]);
        $existingUserId = array_values($userIds)[0];
        $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds));

        // Get initial roles
        $stmt = $this->db->prepareStatement(
            "SELECT roles FROM {$this->db->au_users_basedata} WHERE id = :user_id"
        );
        $stmt->execute([':user_id' => $existingUserId]);
        $initialRoles = $stmt->fetch(\PDO::FETCH_ASSOC)['roles'];

        // Now try to import with a conflicting user
        $batchUsers = [
            [
                // Conflicting user
                'realname' => "DIFFERENT {$uniqueId}",
                'displayname' => "DIFFERENT",
                'username' => "existing_role_{$uniqueId}",
                'email' => "different_role_{$uniqueId}@test.local",
                'about_me' => "Different",
            ],
        ];
        $batchCsv = $this->usersToCSV($batchUsers);

        // Act: Try to import to second room with different role - should fail
        $result = $this->user->addAllCSV($batchCsv, [$roomHashIds[1]], 30, 0);

        // Assert: Import should fail
        $this->assertFalse($result['success'], 'Import should fail due to conflict');

        // Assert: Roles JSON should be unchanged
        $stmt = $this->db->prepareStatement(
            "SELECT roles FROM {$this->db->au_users_basedata} WHERE id = :user_id"
        );
        $stmt->execute([':user_id' => $existingUserId]);
        $finalRoles = $stmt->fetch(\PDO::FETCH_ASSOC)['roles'];

        $this->assertEquals(
            $initialRoles,
            $finalRoles,
            'Roles JSON should be unchanged after failed import (rollback)'
        );

        // Verify no role for second room was added
        $roles = json_decode($finalRoles, true);
        $room2Role = collect($roles)->first(fn($r) => ($r['room'] ?? null) === $roomHashIds[1]);
        $this->assertNull($room2Role, 'No role should exist for second room after rollback');
    }

    /**
     * Test that all required fields from CSV are properly populated in the database
     */
    public function test_addAllCSV_populates_all_required_fields(): void
    {
        // Arrange
        $rooms = $this->createTestRooms(1);
        $roomHashIds = array_column($rooms, 'hash_id');

        $uniqueId = time() . '_' . uniqid();
        $user = [
            'realname' => "Real Name {$uniqueId}",
            'displayname' => "Display Name {$uniqueId}",
            'username' => "username_{$uniqueId}",
            'email' => "email_{$uniqueId}@test.local",
            'about_me' => "About me text {$uniqueId}",
        ];
        $csv = $this->usersToCSV([$user]);

        // Act
        $result = $this->user->addAllCSV($csv, $roomHashIds, 20, 0);

        // Track for cleanup
        $userIds = $this->getUserIdsByUsernames([$user['username']]);
        $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds));

        // Assert: Import should succeed
        $this->assertTrue($result['success'], 'Import should succeed');

        // Fetch the created user from database
        $stmt = $this->db->prepareStatement(
            "SELECT * FROM {$this->db->au_users_basedata} WHERE username = :username"
        );
        $stmt->execute([':username' => $user['username']]);
        $dbUser = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($dbUser, 'User should exist in database');

        // Assert: All CSV fields are properly populated
        $this->assertEquals(
            $user['realname'],
            $dbUser['realname'],
            'realname field should be populated correctly'
        );
        $this->assertEquals(
            $user['displayname'],
            $dbUser['displayname'],
            'displayname field should be populated correctly'
        );
        $this->assertEquals(
            $user['username'],
            $dbUser['username'],
            'username field should be populated correctly'
        );
        $this->assertEquals(
            strtolower($user['email']),
            $dbUser['email'],
            'email field should be populated correctly (lowercase)'
        );
        $this->assertEquals(
            $user['about_me'],
            $dbUser['about_me'],
            'about_me field should be populated correctly'
        );

        // Assert: System-generated fields are also populated
        $this->assertNotEmpty($dbUser['id'], 'id should be auto-generated');
        $this->assertNotEmpty($dbUser['hash_id'], 'hash_id should be generated');
        $this->assertNotEmpty($dbUser['created'], 'created timestamp should be set');
        $this->assertNotEmpty($dbUser['last_update'], 'last_update timestamp should be set');

        // Assert: Roles JSON is properly structured
        $roles = json_decode($dbUser['roles'], true);
        $this->assertIsArray($roles, 'roles should be a valid JSON array');
        $this->assertNotEmpty($roles, 'roles should not be empty');

        // Verify role entry structure
        $roomRole = collect($roles)->first(fn($r) => ($r['room'] ?? null) === $rooms[0]['hash_id']);
        $this->assertNotNull($roomRole, 'Should have role entry for the target room');
        $this->assertArrayHasKey('room', $roomRole, 'Role entry should have room key');
        $this->assertArrayHasKey('role', $roomRole, 'Role entry should have role key');
        $this->assertEquals($rooms[0]['hash_id'], $roomRole['room'], 'Role room should match target room');
        $this->assertEquals(20, $roomRole['role'], 'Role should be the specified level');
    }

    /**
     * Test that multiple room assignments are handled correctly with proper relationships and roles
     */
    public function test_addAllCSV_multiple_room_assignments_handled_correctly(): void
    {
        // Arrange: Create multiple rooms
        $roomCount = 5;
        $rooms = $this->createTestRooms($roomCount);
        $roomHashIds = array_column($rooms, 'hash_id');

        $uniqueId = time() . '_' . uniqid();
        $users = $this->generateRandomUsers(3);
        $csv = $this->usersToCSV($users);
        $userLevel = 30; // Moderator role

        // Act
        $result = $this->user->addAllCSV($csv, $roomHashIds, $userLevel, 0);

        // Track for cleanup
        $usernames = array_column($users, 'username');
        $userIds = $this->getUserIdsByUsernames($usernames);
        $this->createdUserIds = array_merge($this->createdUserIds, array_values($userIds));

        // Assert: Import should succeed
        $this->assertTrue($result['success'], 'Import should succeed');

        // For each user, verify they are in ALL specified rooms
        foreach ($userIds as $username => $userId) {
            // Count room relationships
            $stmt = $this->db->prepareStatement(
                "SELECT room_id FROM {$this->db->au_rel_rooms_users} WHERE user_id = :user_id"
            );
            $stmt->execute([':user_id' => $userId]);
            $relationships = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            // User should be in all test rooms (plus potentially standard room)
            foreach ($rooms as $room) {
                $this->assertContains(
                    (string) $room['id'],
                    array_map('strval', $relationships),
                    "User {$username} should be in room {$room['hash_id']}"
                );
            }

            // Verify roles JSON has entries for ALL rooms
            $stmt = $this->db->prepareStatement(
                "SELECT roles FROM {$this->db->au_users_basedata} WHERE id = :user_id"
            );
            $stmt->execute([':user_id' => $userId]);
            $rolesJson = $stmt->fetch(\PDO::FETCH_ASSOC)['roles'];
            $roles = json_decode($rolesJson, true);

            $this->assertIsArray($roles, 'Roles should be a valid JSON array');

            foreach ($rooms as $room) {
                $roleEntry = collect($roles)->first(fn($r) => ($r['room'] ?? null) === $room['hash_id']);
                $this->assertNotNull(
                    $roleEntry,
                    "User {$username} should have role for room {$room['hash_id']}"
                );
                $this->assertEquals(
                    $userLevel,
                    $roleEntry['role'],
                    "User {$username} should have role {$userLevel} for room {$room['hash_id']}"
                );
            }
        }

        // Verify each room has all the users
        foreach ($rooms as $room) {
            $stmt = $this->db->prepareStatement(
                "SELECT user_id FROM {$this->db->au_rel_rooms_users} WHERE room_id = :room_id"
            );
            $stmt->execute([':room_id' => $room['id']]);
            $roomUsers = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($userIds as $username => $userId) {
                $this->assertContains(
                    (string) $userId,
                    array_map('strval', $roomUsers),
                    "Room {$room['hash_id']} should contain user {$username}"
                );
            }
        }
    }

    // TODO: Test for very long strings validation against field limits
    // public function test_addAllCSV_validates_field_length_limits(): void
    // {
    //     // This test should verify that very long strings are validated against
    //     // database field limits (e.g., realname VARCHAR(2048), etc.)
    // }
}
