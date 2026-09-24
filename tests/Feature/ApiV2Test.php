<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ApiV2Test extends TestCase
{
    public function test_no_tenant_instancecode_header_404s(): void
    {
        $this->getJson('/api/v2/users')
            ->assertNotFound();
    }
}
