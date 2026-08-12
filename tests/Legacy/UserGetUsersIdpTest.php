<?php

namespace Tests\Legacy;

use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

/**
 * getUsers has to say where an account came from.
 *
 * The settings table labels each person "aula" or "sso: <provider>". It can
 * only do that if the payload carries the provider identity, and the identity
 * has to be idp_user_id: the import sets it when it creates the account, while
 * sso_sub appears only once that person has signed in. Selecting sso_sub alone
 * labelled every imported account "aula" until its owner first logged in.
 */
class UserGetUsersIdpTest extends TestCase
{
    use CreatesTestTenant;

    private $db;

    private $user;

    private array $insertedIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
        $this->loadLegacyClasses();
        $this->initializeDependencies();
    }

    protected function tearDown(): void
    {
        if ($this->insertedIds) {
            $placeholders = implode(',', array_fill(0, count($this->insertedIds), '?'));
            $stmt = $this->db->prepareStatement(
                "DELETE FROM {$this->db->au_users_basedata} WHERE id IN ({$placeholders})"
            );
            $stmt->execute($this->insertedIds);
        }
        parent::tearDown();
    }

    public function test_it_reports_the_provider_identity_of_an_imported_account(): void
    {
        $username = 'idpcol_imported_'.uniqid();
        $this->insertUser($username, idpUserId: 'person-from-directory', ssoSub: null);

        $row = $this->findUser($username);

        $this->assertNotNull($row, 'the seeded user should come back from getUsers');
        $this->assertArrayHasKey('idp_user_id', $row);
        // Never signed in, so sso_sub is null; the account is still the
        // provider's and must not read as locally made.
        $this->assertSame('person-from-directory', $row['idp_user_id']);
        $this->assertNull($row['sso_sub']);
    }

    public function test_an_account_made_in_aula_carries_neither(): void
    {
        $username = 'idpcol_local_'.uniqid();
        $this->insertUser($username, idpUserId: null, ssoSub: null);

        $row = $this->findUser($username);

        $this->assertNotNull($row);
        $this->assertNull($row['idp_user_id']);
        $this->assertNull($row['sso_sub']);
    }

    // =========================================================
    // Helpers
    // =========================================================

    /**
     * @return array<string, mixed>|null
     */
    private function findUser(string $username): ?array
    {
        $response = $this->user->getUsers(0, 500);

        foreach ($response['data'] ?: [] as $row) {
            if (($row['username'] ?? null) === $username) {
                return $row;
            }
        }

        return null;
    }

    private function insertUser(string $username, ?string $idpUserId, ?string $ssoSub): void
    {
        $stmt = $this->db->prepareStatement(
            "INSERT INTO {$this->db->au_users_basedata}
             (username, displayname, hash_id, status, userlevel, idp_user_id, sso_sub)
             VALUES (?, ?, ?, 1, 20, ?, ?)"
        );
        $stmt->execute([$username, $username, md5($username), $idpUserId, $ssoSub]);

        $this->insertedIds[] = (int) $this->db->lastInsertId();
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
        $baseClassDir = base_path('legacy/src/classes/');

        if (! class_exists('Memcached')) {
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

        foreach ([
            'InstanceConfig' => 'legacy/src/classes/helpers/InstanceConfig.php',
            'ResponseBuilder' => 'legacy/src/classes/helpers/ResponseBuilder.php',
            'Database' => 'legacy/src/classes/models/Database.php',
            'Converters' => 'legacy/src/classes/models/Converters.php',
            'Systemlog' => 'legacy/src/classes/models/Systemlog.php',
            'User' => 'legacy/src/classes/models/User.php',
        ] as $class => $path) {
            if (! class_exists($class, false)) {
                require_once base_path($path);
            }
        }
    }

    private function initializeDependencies(): void
    {
        $instanceConfig = \InstanceConfig::createFromCode('TEST001');
        $this->db = new \Database($instanceConfig);
        $syslog = new \Systemlog($this->db);
        $this->user = new \User($this->db, null, $syslog);
    }
}
