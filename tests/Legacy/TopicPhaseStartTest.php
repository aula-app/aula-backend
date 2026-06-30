<?php

namespace Tests\Legacy;

use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

/**
 * Regression tests for issue #543 — phase durations accumulating across phases.
 *
 * The fix introduces a `phase_start` column on au_topics that records when the
 * current phase began. The frontend countdown is computed from
 * `phase_start + phase_duration[current_phase]`, so the timer must reset to
 * "now" whenever the phase actually changes — and must NOT move on unrelated
 * edits. Time passing is simulated by back-dating `phase_start` directly in the
 * database and asserting how the real Topic methods treat it.
 */
class TopicPhaseStartTest extends TestCase
{
    use CreatesTestTenant;

    private $db;
    private $topic;
    private int $roomId = 0;
    private array $insertedTopicIds = [];
    private string $testTag = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        $this->loadLegacyClasses();
        $this->initializeDependencies();
        $this->testTag = 'phpunit_topic_' . uniqid();
        $this->roomId = $this->insertRoom();
    }

    protected function tearDown(): void
    {
        if ($this->insertedTopicIds) {
            $placeholders = implode(',', array_fill(0, count($this->insertedTopicIds), '?'));
            $stmt = $this->db->prepareStatement(
                "DELETE FROM {$this->db->au_topics} WHERE id IN ({$placeholders})"
            );
            $stmt->execute($this->insertedTopicIds);
        }
        if ($this->roomId) {
            $stmt = $this->db->prepareStatement("DELETE FROM {$this->db->au_rooms} WHERE id = ?");
            $stmt->execute([$this->roomId]);
        }
        parent::tearDown();
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

        // Helper classes are not covered by the legacy model autoloader.
        foreach (['InstanceConfig', 'Crypt', 'ResponseBuilder'] as $helper) {
            if (!class_exists($helper, false)) {
                require_once $baseHelperDir . $helper . '.php';
            }
        }

        // Register the legacy model autoloader so Topic's dependency chain
        // (User, Converters, Mail, ...) resolves the same way it does in prod.
        require_once base_path('legacy/src/functions.php');
        $GLOBALS['baseClassModelDir'] = $baseClassModelDir;
    }

    private function initializeDependencies(): void
    {
        $instanceConfig = \InstanceConfig::createFromCode('TEST001');
        $this->db = new \Database($instanceConfig);
        $crypt = new \Crypt();
        $syslog = new \Systemlog($this->db);
        $this->topic = new \Topic($this->db, $crypt, $syslog);
    }

    private function insertRoom(): int
    {
        $stmt = $this->db->prepareStatement(
            "INSERT INTO {$this->db->au_rooms} (room_name, status, hash_id, created, last_update)
             VALUES (:name, 1, :hash, NOW(), NOW())"
        );
        $stmt->execute([':name' => $this->testTag, ':hash' => $this->testTag]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Insert a topic directly so phase_id and phase_start can be controlled
     * precisely. $phaseStartDaysAgo simulates time passing in a phase.
     */
    private function insertTopic(int $phaseId, int $phaseStartDaysAgo): int
    {
        $stmt = $this->db->prepareStatement(
            "INSERT INTO {$this->db->au_topics}
             (name, description_public, description_internal, status, order_importance,
              created, last_update, hash_id, room_id, phase_id, phase_start,
              phase_duration_0, phase_duration_1, phase_duration_2, phase_duration_3, phase_duration_4)
             VALUES (:name, '', '', 1, 10, NOW(), NOW(), :hash, :room, :phase,
                     DATE_SUB(NOW(), INTERVAL :days DAY), 0, 14, 14, 14, 14)"
        );
        $stmt->execute([
            ':name'  => $this->testTag,
            ':hash'  => $this->testTag . '_' . $phaseId,
            ':room'  => $this->roomId,
            ':phase' => $phaseId,
            ':days'  => $phaseStartDaysAgo,
        ]);
        $id = (int) $this->db->lastInsertId();
        $this->insertedTopicIds[] = $id;
        return $id;
    }

    private function readPhaseStart(int $topicId): ?string
    {
        $result = $this->topic->getTopicBaseData($topicId);
        $this->assertTrue($result['success']);
        return $result['data']['phase_start'] ?? null;
    }

    private function ageInDays(string $datetime): float
    {
        return (time() - strtotime($datetime)) / 86400;
    }

    public function test_addTopic_sets_phase_start_to_now(): void
    {
        $result = $this->topic->addTopic(
            $this->testTag, '', '', 1, 10, 0, $this->roomId, 1, 10,
            0, 14, 14, 14, 14
        );
        $this->assertTrue($result['success']);
        $topicId = (int) $result['data'];
        $this->insertedTopicIds[] = $topicId;

        $phaseStart = $this->readPhaseStart($topicId);
        $this->assertNotNull($phaseStart);
        $this->assertLessThan(1, $this->ageInDays($phaseStart), 'phase_start should be ~now on creation');
    }

    public function test_changing_phase_resets_phase_start(): void
    {
        // Discussion phase began 20 days ago.
        $topicId = $this->insertTopic(10, 20);
        $this->assertGreaterThan(19, $this->ageInDays($this->readPhaseStart($topicId)));

        // Moderator advances the box to voting (phase 30).
        $result = $this->topic->editTopic(
            $this->testTag, '', $topicId, '', 1, 10, 0, $this->roomId, 1, 30,
            0, 14, 14, 14, 14
        );
        $this->assertTrue($result['success']);

        // Voting must start a fresh countdown — phase_start reset to ~now.
        $this->assertLessThan(
            1,
            $this->ageInDays($this->readPhaseStart($topicId)),
            'phase_start must reset when the phase changes'
        );
    }

    public function test_editing_without_phase_change_keeps_phase_start(): void
    {
        // Voting phase began 5 days ago.
        $topicId = $this->insertTopic(30, 5);

        // A plain edit that re-sends the same phase_id (e.g. description change).
        $result = $this->topic->editTopic(
            $this->testTag, 'updated description', $topicId, '', 1, 10, 0, $this->roomId, 1, 30,
            0, 14, 14, 14, 14
        );
        $this->assertTrue($result['success']);

        // The countdown must NOT restart on an unrelated edit.
        $age = $this->ageInDays($this->readPhaseStart($topicId));
        $this->assertGreaterThan(4, $age, 'phase_start must survive a non-phase edit');
        $this->assertLessThan(7, $age);
    }

    public function test_setTopicProperty_phase_id_resets_only_on_change(): void
    {
        $topicId = $this->insertTopic(10, 8);

        // Setting a non-phase property must not touch phase_start.
        $this->topic->setTopicProperty($topicId, 'status', 1, 0);
        $this->assertGreaterThan(7, $this->ageInDays($this->readPhaseStart($topicId)));

        // Setting phase_id to a new value resets phase_start.
        $this->topic->setTopicProperty($topicId, 'phase_id', 30, 0);
        $this->assertLessThan(1, $this->ageInDays($this->readPhaseStart($topicId)));
    }

    public function test_getTopics_exposes_phase_start(): void
    {
        $topicId = $this->insertTopic(30, 3);

        $result = $this->topic->getTopics(0, 100, 0, 0, '', $this->roomId);
        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['data']);
        $this->assertArrayHasKey('phase_start', $result['data'][0]);
    }
}
