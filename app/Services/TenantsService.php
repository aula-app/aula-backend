<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Str;

class TenantsService
{
    public function generateUniqueInstanceCode(): string
    {
        do {
            $code = strtolower(Str::random(5));
        } while (Tenant::firstWhere('instance_code', $code) !== null);

        return $code;
    }
}
