<?php

declare(strict_types=1);

namespace App\Data\Tenant;

use Spatie\LaravelData\Data;

class DomainTenantPublicData extends Data
{
    public function __construct(
        public readonly string $instance_code,
        public readonly string $name
    ) {
    }
}
