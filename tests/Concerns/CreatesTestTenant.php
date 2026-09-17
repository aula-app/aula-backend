<?php

namespace Tests\Concerns;

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

/**
 * Creates (or finds) the TEST001 tenant once per test class and runs its
 * migrations. The tenant is intentionally not deleted after tests so that
 * test classes that run later in the same suite can reuse it without having
 * to create the database again. In Docker the whole DB is ephemeral anyway;
 * locally the tenant simply persists between runs, which is harmless.
 */
trait CreatesTestTenant
{
    private const TEST_JWT_KEY = 'phpunit_test_jwt_key_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';

    private static ?Tenant $testTenant = null;

    private static ?Client $client = null;

    protected function ensureTestTenantExists(): void
    {
        if (self::$testTenant !== null) {
            return;
        }

        self::$testTenant = Tenant::updateOrCreate(
            ['instance_code' => 'TEST001'],
            [
                'name' => 'Test Tenant 001 (PHPUnit)',
                'jwt_key' => self::TEST_JWT_KEY,
                'api_base_url' => 'https://test001.example',
                'admin1_username' => 'phpunit_admin',
                'admin1_email' => 'phpunit_admin@test001.example',
            ]
        );

        Artisan::call('tenants:migrate', [
            '--tenants' => [self::$testTenant->id],
        ]);

        /** @var ClientRepository $clientRepo */
        $clientRepo = app(ClientRepository::class);

        $passwordName = 'password_grants_tenant_users_'.self::$testTenant->id;
        $personalName = 'personal_access_tenant_users_'.self::$testTenant->id;

        // Looked up before creating: oauth_clients is central and shared, and
        // every process running this trait would otherwise add another pair.
        self::$client = $this->findClientByName($passwordName)
            ?? $clientRepo->createPasswordGrantClient($passwordName, 'aula_users', false);

        $this->findClientByName($personalName)
            ?? $clientRepo->createPersonalAccessGrantClient($personalName, 'aula_users');
    }

    private function findClientByName(string $name): ?Client
    {
        /** @var Client|null $client */
        $client = Passport::client()->newQuery()->where('name', $name)->first();

        return $client;
    }
}
