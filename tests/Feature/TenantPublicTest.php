<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class TenantPublicTest extends TestCase
{
    use CreatesTestTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTestTenantExists();
    }

    public function test_tenant_public()
    {
        $result = $this
            ->getJson('/api/v2/tenants')
            ->assertOk()
            ->json();
        $this->assertIsArray($result);
        $this->assertContains(
            [
                'instance_code' => 'TEST001',
                'name' => 'Test Tenant 001 (PHPUnit)',
            ],
            $result
        );
    }
}
