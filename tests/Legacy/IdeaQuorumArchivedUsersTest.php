<?php

namespace Tests\Legacy;

use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

/**
 * Regression tests for issue #587: archived users counted into the room user
 * total that the frontend divides votes by to decide whether the quorum is met.
 *
 * Only users with status = 1 (active) are potential voters, so archived room
 * members and archived super voters (userlevel 41/45) must not appear in
 * number_of_users or voters_count.
 */
class IdeaQuorumArchivedUsersTest extends TestCase
{
    use CreatesTestTenant;

    private $db;
    private $idea;
    private string $tag = '';
    private int $roomId = 0;
    private int $ideaId = 0;
    private int $activeUserId = 0;
    private int $archivedUserId = 0;
    private array $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        $this->loadLegacyClasses();
        $this->initializeDependencies();

        $this->tag = 'phpunit_quorum_' . uniqid();
        $this->roomId = $this->insertRoom();
        $this->activeUserId = $this->insertRoomMember('active', 1);
        $this->archivedUserId = $this->insertRoomMember('archived', 3);
        $this->ideaId = $this->insertIdea($this->activeUserId);
        $this->insertVote($this->activeUserId);
    }

    protected function tearDown(): void
    {
        $this->exec("DELETE FROM {$this->db->au_votes} WHERE idea_id = ?", [$this->ideaId]);
        $this->exec("DELETE FROM {$this->db->au_ideas} WHERE id = ?", [$this->ideaId]);
        $this->exec("DELETE FROM {$this->db->au_rel_rooms_users} WHERE room_id = ?", [$this->roomId]);
        if ($this->userIds) {
            $placeholders = implode(',', array_fill(0, count($this->userIds), '?'));
            $this->exec("DELETE FROM {$this->db->au_users_basedata} WHERE id IN ({$placeholders})", $this->userIds);
        }
        $this->exec("DELETE FROM {$this->db->au_rooms} WHERE id = ?", [$this->roomId]);
        parent::tearDown();
    }

    public function test_archived_room_members_are_excluded_from_number_of_users(): void
    {
        $withArchived = $this->numberOfUsers();

        $this->setUserStatus($this->archivedUserId, 1);

        $this->assertSame($withArchived + 1, $this->numberOfUsers());
    }

    public function test_archived_room_members_are_excluded_from_voters_count(): void
    {
        $withArchived = $this->votersCount();

        $this->setUserStatus($this->archivedUserId, 1);

        $this->assertSame($withArchived + 1, $this->votersCount());
    }

    public function test_archived_super_voters_are_excluded_from_both_counts(): void
    {
        $principalId = $this->insertUser('principal', 3, 41);
        $numberOfUsers = $this->numberOfUsers();
        $votersCount = $this->votersCount();

        $this->setUserStatus($principalId, 1);

        $this->assertSame($numberOfUsers + 1, $this->numberOfUsers());
        $this->assertSame($votersCount + 1, $this->votersCount());
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function numberOfUsers(): int
    {
        $response = $this->idea->getIdeaBaseData($this->ideaId);
        $this->assertNotFalse($response['data'], 'the seeded idea should be readable');

        return (int) $response['data']['number_of_users'];
    }

    private function votersCount(): int
    {
        $response = $this->idea->getIdeaVoteStats($this->ideaId);
        $this->assertNotFalse($response['data'], 'the seeded idea should carry a vote');

        return (int) $response['data']['voters_count'];
    }

    private function setUserStatus(int $userId, int $status): void
    {
        $this->exec("UPDATE {$this->db->au_users_basedata} SET status = ? WHERE id = ?", [$status, $userId]);
    }

    private function exec(string $sql, array $params): void
    {
        $stmt = $this->db->prepareStatement($sql);
        $stmt->execute($params);
    }

    private function insertRoom(): int
    {
        $this->exec(
            "INSERT INTO {$this->db->au_rooms} (room_name, status, hash_id, created, last_update)
             VALUES (?, 1, ?, NOW(), NOW())",
            [$this->tag, $this->tag]
        );

        return (int) $this->db->lastInsertId();
    }

    private function insertUser(string $suffix, int $status, int $userlevel, string $roles = '[]'): int
    {
        $username = $this->tag . '_' . $suffix;
        $this->exec(
            "INSERT INTO {$this->db->au_users_basedata}
             (username, displayname, hash_id, status, userlevel, roles, created, last_update)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [$username, $username, md5($username), $status, $userlevel, $roles]
        );
        $id = (int) $this->db->lastInsertId();
        $this->userIds[] = $id;

        return $id;
    }

    // Room member with voting role 20 in this room, room membership active.
    private function insertRoomMember(string $suffix, int $status): int
    {
        $roles = json_encode([['room' => $this->tag, 'role' => 20]]);
        $id = $this->insertUser($suffix, $status, 20, $roles);
        $this->exec(
            "INSERT INTO {$this->db->au_rel_rooms_users} (room_id, user_id, status, created, last_update)
             VALUES (?, ?, 1, NOW(), NOW())",
            [$this->roomId, $id]
        );

        return $id;
    }

    private function insertIdea(int $userId): int
    {
        $this->exec(
            "INSERT INTO {$this->db->au_ideas}
             (title, content, user_id, status, room_id, hash_id, sum_likes, sum_votes, sum_comments, created, last_update)
             VALUES (?, '', ?, 1, ?, ?, 0, 0, 0, NOW(), NOW())",
            [$this->tag, $userId, $this->roomId, $this->tag]
        );

        return (int) $this->db->lastInsertId();
    }

    private function insertVote(int $userId): void
    {
        $this->exec(
            "INSERT INTO {$this->db->au_votes}
             (user_id, idea_id, vote_value, vote_weight, status, hash_id, created, last_update)
             VALUES (?, ?, 1, 1, 1, ?, NOW(), NOW())",
            [$userId, $this->ideaId, $this->tag]
        );
    }

    private function loadLegacyClasses(): void
    {
        global $allowed_include;
        $allowed_include = 1;

        $legacyBaseConfig = base_path('legacy/config/base_config.php');
        if (file_exists($legacyBaseConfig)) {
            require_once $legacyBaseConfig;
        }

        global $baseHelperDir, $baseClassDir, $baseClassModelDir;
        $baseHelperDir     = base_path('legacy/src/classes/helpers/');
        $baseClassDir      = base_path('legacy/src/classes/');
        $baseClassModelDir = base_path('legacy/src/classes/models/');

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

        foreach (['InstanceConfig', 'Crypt', 'ResponseBuilder'] as $helper) {
            if (!class_exists($helper, false)) {
                require_once $baseHelperDir . $helper . '.php';
            }
        }

        require_once base_path('legacy/src/functions.php');
        $GLOBALS['baseClassModelDir'] = $baseClassModelDir;
    }

    private function initializeDependencies(): void
    {
        $instanceConfig = \InstanceConfig::createFromCode('TEST001');
        $this->db = new \Database($instanceConfig);
        $crypt = new \Crypt();
        $syslog = new \Systemlog($this->db);
        $this->idea = new \Idea($this->db, $crypt, $syslog);
    }
}
