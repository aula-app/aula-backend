<?php

namespace Database\Seeders;

use Database\Seeders\Tenants\UserForTenants;
use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([UserForTenants::class]);
    }
}
