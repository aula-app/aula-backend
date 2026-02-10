<?php

namespace App\Models;

use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;

    public static function getCustomColumns(): array
    {
        return array_merge(parent::getCustomColumns(), [
            'name',
            'api_base_url',
            'contact_info',
            'admin1_name',
            'admin1_username',
            'admin1_email',
            'admin1_init_pass_url',
            'admin2_name',
            'admin2_username',
            'admin2_email',
            'admin2_init_pass_url',
            'instance_code',
            'jwt_key',
        ]);
    }
}
