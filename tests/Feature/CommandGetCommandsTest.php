<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class CommandGetCommandsTest extends TestCase
{
    use CreatesTestTenant;

    private $db;
    private $command;
    private array $insertedIds = [];
    private string $testTag = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        $this->loadLegacyClasses();
        $this->initializeDependencies();
        $this->testTag = 'phpunit_cmd_' . uniqid();
    }

    protected function tearDown(): void
    {
        if ($this->insertedIds) {
            $placeholders = implode(',', array_fill(0, count($this->insertedIds), '?'));
            $stmt = $this->db->prepareStatement(
                "DELETE FROM {$this->db->au_commands} WHERE id IN ({$placeholders})"
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
        if (!class_exists('Command', false)) {
            require_once base_path('legacy/src/classes/models/Command.php');
        }
    }

    private function initializeDependencies(): void
    {
        $instanceConfig = \InstanceConfig::createFromCode('TEST001');
        $this->db = new \Database($instanceConfig);
        $syslog = new \Systemlog($this->db);
        $this->command = new \Command($this->db, null, $syslog);
    }

    private function insertCommand(int $active): int
    {
        $stmt = $this->db->prepareStatement(
            "INSERT INTO {$this->db->au_commands}
             (cmd_id, command, date_start, parameters, active, status, created, last_update, target_id, updater_id)
             VALUES (0, :command, NOW(), '', :active, 0, NOW(), NOW(), 0, 0)"
        );
        $stmt->execute([':command' => $this->testTag, ':active' => $active]);
        $id = (int) $this->db->lastInsertId();
        $this->insertedIds[] = $id;
        return $id;
    }

    private function scopedWhere(): string
    {
        return " AND command = '{$this->testTag}'";
    }

    public function test_getCommands_count_matches_active_commands_only(): void
    {
        $this->insertCommand(1);
        $this->insertCommand(1);
        $this->insertCommand(0);
        $this->insertCommand(0);
        $this->insertCommand(0);

        $result = $this->command->getCommands(0, 0, 0, 0, 1, 0, $this->scopedWhere());

        $this->assertTrue($result['success']);
        $this->assertCount(
            2,
            $result['data'],
            'data array should contain only the 2 active commands'
        );
        $this->assertEquals(
            2,
            $result['count'],
            'count should equal the number of active commands, not total commands'
        );
    }

    public function test_getCommands_count_matches_inactive_commands_only(): void
    {
        $this->insertCommand(1);
        $this->insertCommand(0);
        $this->insertCommand(0);

        $result = $this->command->getCommands(0, 0, 0, 0, 0, 0, $this->scopedWhere());

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['data']);
        $this->assertEquals(2, $result['count']);
    }
}
