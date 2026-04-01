<?php

declare(strict_types=1);

namespace App\UseCases;

use App\Models\Tenant;
use App\Services\TenantsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTenantUseCase
{
    public function __construct(private readonly TenantsService $tenantsService) {}

    public function execute(
        string $name,
        string $instanceCode,
        string $admin1Username,
        string $admin1FullName,
        string $admin1Email,
        string $admin2Username,
        string $admin2FullName,
        string $admin2Email,
    ): Tenant {
        $jwtKey = Str::random(64);
        $apiBaseUrl = config('app.url');
        $admin1Secret = Str::random(64);
        $admin2Secret = Str::random(64);

        $tenant = Tenant::create([
            'name' => $name,
            'api_base_url' => $apiBaseUrl,
            'instance_code' => $instanceCode,
            'jwt_key' => $jwtKey,
            'admin1_name' => $admin1FullName,
            'admin1_username' => $admin1Username,
            'admin1_email' => $admin1Email,
            'admin2_name' => $admin2FullName,
            'admin2_username' => $admin2Username,
            'admin2_email' => $admin2Email,
            'admin1_init_pass_url' => "{$apiBaseUrl}/password/{$admin1Secret}?code={$instanceCode}",
            'admin2_init_pass_url' => "{$apiBaseUrl}/password/{$admin2Secret}?code={$instanceCode}",
        ]);

        // Tenant::create() triggers CreateDatabase + MigrateDatabase via TenancyServiceProvider events.
        // Now populate the tenant DB with initial system data and admin users.
        $tenant->run(function () use (
            $name,
            $admin1Username, $admin1FullName, $admin1Email, $admin1Secret,
            $admin2Username, $admin2FullName, $admin2Email, $admin2Secret,
        ) {
            $this->insertInitialSystemData($name);
            $this->insertAdminUser($admin1Username, $admin1FullName, $admin1Email, $admin1Secret);
            $this->insertAdminUser($admin2Username, $admin2FullName, $admin2Email, $admin2Secret);
        });

        return $tenant;
    }

    private function insertInitialSystemData(string $name): void
    {
        $now = now();
        $appendix = microtime(true).rand(100, 10000000);
        $hashId = md5('Schule'.$appendix);

        DB::table('au_rooms')->insert([
            'room_name' => 'Schule',
            'description_internal' => null,
            'hash_id' => $hashId,
            'status' => 1,
            'type' => 1,
        ]);

        DB::table('au_system_current_state')->insert([
            'online_mode' => 1,
            'created' => $now,
            'last_update' => $now,
            'updater_id' => 1,
        ]);

        DB::table('au_system_global_config')->insert([
            'name' => $name,
            'allow_registration' => 0,
        ]);
    }

    private function insertAdminUser(
        string $username,
        string $fullName,
        string $email,
        string $secret,
    ): void {
        $now = now();

        $userId = DB::table('au_users_basedata')->insertGetId([
            'realname' => $fullName,
            'displayname' => $fullName,
            'username' => $username,
            'email' => $email,
            'pw' => '',
            'hash_id' => Str::random(32),
            'registration_status' => 2,
            'status' => 1,
            'userlevel' => 50,
            'created' => $now,
            'last_update' => $now,
            'pw_changed' => 0,
            'presence' => 1,
            'roles' => '[]',
        ]);

        DB::table('au_change_password')->insert([
            'user_id' => $userId,
            'secret' => $secret,
            'created_at' => $now,
        ]);
    }
}
