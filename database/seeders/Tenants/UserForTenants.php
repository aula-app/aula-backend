<?php

namespace Database\Seeders\Tenants;

use App\Enums\UserLevel;
use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\LegacyUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserForTenants extends Seeder
{
    /**
     * Seed the tenant's database with passport clients.
     */
    public function run(): void
    {
        $tenant = Tenant::query()->whereId(tenant('id'))->first();

        $seedId = Str::random(6);
        new LegacyUser([
            'username' => 'test_'.$seedId,
            'displayname' => 'test_'.$seedId,
            'hash_id' => 'test_'.$seedId,
            'email' => "test.{$seedId}@example.com",
            'pw' => 'password',
            'userlevel' => UserLevel::User,
            'status' => UserStatus::Active
        ])->save();
    }
}
