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
}
