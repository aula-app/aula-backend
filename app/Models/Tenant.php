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
            'admin_name',
            'admin_username',
            'admin_email',
            'admin_init_pass_url',
            'tech_admin_name',
            'tech_admin_username',
            'tech_admin_email',
            'tech_admin_init_pass_url',
            'instance_code',
            'jwt_key',
        ]);
    }
}
