<?php

namespace Tests\Concerns;

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;

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

    protected function ensureTestTenantExists(): void
    {
        if (self::$testTenant !== null) {
            return;
        }

        $existing = Tenant::where('instance_code', 'TEST001')->first();

        if (!$existing) {
            $existing = Tenant::create([
                'name'            => 'Test Tenant 001 (PHPUnit)',
                'instance_code'   => 'TEST001',
                'jwt_key'         => self::TEST_JWT_KEY,
                'api_base_url'    => 'https://test001.example',
                'admin1_username' => 'phpunit_admin',
                'admin1_email'    => 'phpunit_admin@test001.example',
            ]);
        }

        self::$testTenant = $existing;

        Artisan::call('tenants:migrate', [
            '--tenants' => [self::$testTenant->id],
        ]);
    }
}
