<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TenantsFromAulaManagerSeeder extends Seeder
{
    /**
     * Seed the tenants table with instances from aula-manager.
     */
    public function run(): void
    {
        $file_path = resource_path('sql/2025_12_09_113800_insert_tenants.sql');

        \DB::unprepared(
            file_get_contents($file_path)
        );
        $this->command->info("Tenants seeded from aula-manager dump from file {$file_path}");
    }
}
