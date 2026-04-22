<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class TextGetTextsTest extends TestCase
{
    use CreatesTestTenant;

    private $db;
    private $text;
    private array $insertedIds = [];
    private string $testTag = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        $this->loadLegacyClasses();
        $this->initializeDependencies();
        $this->testTag = 'phpunit_text_' . uniqid();
    }

    protected function tearDown(): void
    {
        if ($this->insertedIds) {
            $placeholders = implode(',', array_fill(0, count($this->insertedIds), '?'));
            $stmt = $this->db->prepareStatement(
                "DELETE FROM {$this->db->au_texts} WHERE id IN ({$placeholders})"
            );
            $stmt->execute($this->insertedIds);
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

        global $baseHelperDir, $baseClassDir;
        $baseHelperDir = base_path('legacy/src/classes/helpers/');
        $baseClassDir  = base_path('legacy/src/classes/');

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

        if (!class_exists('InstanceConfig', false)) {
            require_once base_path('legacy/src/classes/helpers/InstanceConfig.php');
        }
        if (!class_exists('Database', false)) {
            require_once base_path('legacy/src/classes/models/Database.php');
        }
        if (!class_exists('Converters', false)) {
            require_once base_path('legacy/src/classes/models/Converters.php');
        }
        if (!class_exists('Systemlog', false)) {
            require_once base_path('legacy/src/classes/models/Systemlog.php');
        }
        if (!class_exists('ResponseBuilder', false)) {
            require_once base_path('legacy/src/classes/helpers/ResponseBuilder.php');
        }
        if (!class_exists('Text', false)) {
            require_once base_path('legacy/src/classes/models/Text.php');
        }
    }

    private function initializeDependencies(): void
    {
        $instanceConfig = \InstanceConfig::createFromCode('TEST001');
        $this->db = new \Database($instanceConfig);
        $syslog = new \Systemlog($this->db);
        $this->text = new \Text($this->db, null, $syslog);
    }

    private function insertText(int $status, int $creatorId = 0): int
    {
        $stmt = $this->db->prepareStatement(
            "INSERT INTO {$this->db->au_texts}
             (headline, body, status, user_needs_to_consent, creator_id, created, last_update)
             VALUES (:headline, '', :status, 0, :creator_id, NOW(), NOW())"
        );
        $stmt->execute([
            ':headline'   => $this->testTag,
            ':status'     => $status,
            ':creator_id' => $creatorId,
        ]);
        $id = (int) $this->db->lastInsertId();
        $this->insertedIds[] = $id;
        return $id;
    }

    private function scopedWhere(): string
    {
        return " AND headline = '{$this->testTag}'";
    }

    public function test_getTexts_does_not_throw_on_status_filter_with_limit(): void
    {
        $this->insertText(1);
        $this->insertText(1);
        $this->insertText(0);

        // With limit active, the count query runs — it must not throw on unbound :status
        $result = $this->text->getTexts(0, 100, 0, 0, 1, $this->scopedWhere());

        $this->assertTrue($result['success']);
    }

    public function test_getTexts_count_matches_filtered_status_with_limit(): void
    {
        $this->insertText(1);
        $this->insertText(1);
        $this->insertText(0);

        $result = $this->text->getTexts(0, 100, 0, 0, 1, $this->scopedWhere());

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['data']);
        $this->assertEquals(2, $result['count']);
    }

    public function test_getTexts_count_matches_inactive_status_with_limit(): void
    {
        $this->insertText(1);
        $this->insertText(0);
        $this->insertText(0);

        $result = $this->text->getTexts(0, 100, 0, 0, 0, $this->scopedWhere());

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['data']);
        $this->assertEquals(2, $result['count']);
    }
}
