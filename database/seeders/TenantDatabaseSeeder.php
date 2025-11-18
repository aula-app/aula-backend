<?php

namespace Database\Seeders;

use Database\Seeders\Tenants\PassportForTenants;
use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([PassportForTenants::class]);
    }
}
