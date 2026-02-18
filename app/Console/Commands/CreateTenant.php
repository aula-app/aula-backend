<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\LegacyMkdir;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTenant extends Command
{
    protected $signature = 'tenant:create';

    protected $description = 'Create a new tenant with database, admin users, and JWT key';

    public function handle(): int
    {
        $this->info('=== Create New Tenant ===');
        $this->newLine();

        // Collect tenant information
        do {
            $tenantName = $this->ask('Tenant name');
            if (empty($tenantName)) {
                $this->warn('Tenant has to have a name, maybe a school name?');

                continue;
            }
            if (Tenant::firstWhere('name', $tenantName) !== null) {
                $this->warn('Tenant\'s name has to be unique. Try choosing another name.');

                continue;
            }
            break;
        } while (true);

        // Admin user information
        $this->info('--- Admin User ---');
        do {
            $adminUsername = $this->ask('Admin username');
        } while (empty($adminUsername));
        $adminFullName = $this->ask('Admin full name', 'admin1');
        do {
            $adminEmail = $this->ask('Admin email');
        } while (empty($adminEmail));

        // Second admin user information
        $this->newLine();
        $this->info('--- Second Admin User ---');
        do {
            $secondAdminUsername = $this->ask('Second admin username');
        } while (empty($secondAdminUsername));
        $secondAdminFullName = $this->ask('Second admin full name', 'admin2');
        do {
            $secondAdminEmail = $this->ask('Second admin email');
        } while (empty($secondAdminEmail));

        // Generate and confirm instance code
        $instanceCode = $this->generateUniqueInstanceCode();

        // Generate JWT secret
        $jwtKey = Str::random(64);

        // Derive API base URL from APP_URL
        $apiBaseUrl = config('app.url');

        // Show summary
        $this->newLine();
        $this->info('=== Summary ===');
        $this->table(
            ['Field', 'Value'],
            [
                ['Tenant Name', $tenantName],
                ['Instance Code', $instanceCode],
                ['API Base URL', $apiBaseUrl],
                ['JWT Key', Str::limit($jwtKey, 20, '...')],
                ['Admin Username', $adminUsername],
                ['Admin Full Name', $adminFullName],
                ['Admin Email', $adminEmail],
                ['Second Admin Username', $secondAdminUsername],
                ['Second Admin Full Name', $secondAdminFullName],
                ['Second Admin Email', $secondAdminEmail],
            ]
        );

        if (! $this->confirm('Do you want to proceed with creating this tenant?', true)) {
            $this->warn('Tenant creation cancelled.');

            return self::SUCCESS;
        }

        // Create the tenant
        $this->info('Creating tenant...');

        try {
            $tenant = Tenant::create([
                'name' => $tenantName,
                'api_base_url' => $apiBaseUrl,
                'instance_code' => $instanceCode,
                'jwt_key' => $jwtKey,
                'admin1_name' => $adminFullName,
                'admin1_username' => $adminUsername,
                'admin1_email' => $adminEmail,
                'admin2_name' => $secondAdminFullName,
                'admin2_username' => $secondAdminUsername,
                'admin2_email' => $secondAdminEmail,
            ]);

            $this->info("Tenant created with ID: {$tenant->id}");
            $this->info('Database created and migrations executed.');

            $config = <<<END

              \$instances["$instanceCode"] = [
                "host" => "mariadb",
                "user" => "{$tenant->getInternal('db_username')}",
                "pass" => "{$tenant->getInternal('db_password')}",
                "dbname" => "{$tenant->getInternal('db_name')}",
                "jwt_key" => "{$jwtKey}",
                "instance_api_url" => "{$apiBaseUrl}"
              ];

            END;
            $this->info('Appending instance config to legacy instances_config.php for interoperability...');
            $this->appendLegacyConfigInplace('/mnt/aula-backend-legacy/config/instances_config.php', $config);
            $this->info('Legacy instances_config.php updated.');
            $this->info('Creating legacy folder for the instance file uploads...');
            LegacyMkdir::dispatch($instanceCode)->onQueue('legacy-mkdir');
            $this->info('Created legacy folder for the instance file uploads.');
        } catch (\Exception $e) {
            $this->error("Failed to create tenant: {$e->getMessage()}");

            return self::FAILURE;
        }

        // Create admin users in the tenant database
        $this->info('Creating admin users in tenant database...');

        try {
            $tenant->run(function () use (
                $adminUsername, $adminFullName, $adminEmail,
                $secondAdminUsername, $secondAdminFullName, $secondAdminEmail,
                $instanceCode
            ) {
                $now = now();

                $this->insertInitialSystemData();

                // Create first admin user
                $adminId = DB::table('au_users_basedata')->insertGetId([
                    'realname' => $adminFullName,
                    'displayname' => $adminFullName,
                    'username' => $adminUsername,
                    'email' => $adminEmail,
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

                // Create password reset entry for first admin
                $adminSecret = Str::random(64);
                DB::table('au_change_password')->insert([
                    'user_id' => $adminId,
                    'secret' => $adminSecret,
                    'created_at' => $now,
                ]);

                // Create second admin user
                $secondAdminId = DB::table('au_users_basedata')->insertGetId([
                    'realname' => $secondAdminFullName,
                    'displayname' => $secondAdminFullName,
                    'username' => $secondAdminUsername,
                    'email' => $secondAdminEmail,
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

                // Create password reset entry for second admin
                $secondAdminSecret = Str::random(64);
                DB::table('au_change_password')->insert([
                    'user_id' => $secondAdminId,
                    'secret' => $secondAdminSecret,
                    'created_at' => $now,
                ]);

                $baseUrl = config('app.url');

                $this->newLine();
                $this->info('=== Password Reset URLs ===');
                $this->line("Admin ({$adminUsername}):");
                $this->line("  {$baseUrl}/password/{$adminSecret}?code={$instanceCode}");
                $this->newLine();
                $this->line("Second Admin ({$secondAdminUsername}):");
                $this->line("  {$baseUrl}/password/{$secondAdminSecret}?code={$instanceCode}");
            });

            // Store init password URLs on the tenant record
            $tenant->update([
                'admin1_init_pass_url' => 'pending',
                'admin2_init_pass_url' => 'pending',
            ]);

        } catch (\Exception $e) {
            $this->error("Failed to create admin users: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Tenant created successfully!');

        return self::SUCCESS;
    }

    protected function appendLegacyConfigInplace($configFile, $newConfig)
    {
        $reading = fopen($configFile, 'r');
        if (! $reading) {
            $this->error("Failed to open file {$configFile} for reading.");
            throw new \RuntimeException("Failed to open file {$configFile} for reading.");
        }
        $writing = fopen("{$configFile}.tmp", 'w');

        $placeholder = '// END_OF_CONFIG';
        while (! feof($reading)) {
            $line = fgets($reading);
            if (! $line) {
                break;
            }
            if (stristr($line, $placeholder)) {
                $line = "{$newConfig}\n$placeholder\n";
            }
            fwrite($writing, $line);
        }
        fclose($reading);
        fclose($writing);

        $date = date('Y-m-d H:i:s');
        copy($configFile, "{$configFile}.{$date}.bak");

        return rename("{$configFile}.tmp", $configFile);
    }

    private function generateUniqueInstanceCode(): string
    {
        do {
            $code = strtolower(Str::random(5));
            if (Tenant::firstWhere('instance_code', $code) !== null) {
                $this->warn("Instance code '{$code}' already exists, generating a new one...");

                continue;
            }

            if (! $this->confirm("Use instance code '{$code}'?", true)) {
                continue;
            }

            return $code;
        } while (true);
    }

    private function insertInitialSystemData(): void
    {
        $now = now();

        DB::table('au_rooms')->insert([
            'room_name' => 'Schule',
            'description_internal' => null,
            'status' => 1,
            'type' => 1,
        ]);

        DB::table('au_system_current_state')->insert([
            'created' => $now,
            'last_update' => $now,
            'updater_id' => 2,
        ]);

        DB::table('au_system_global_config')->insert([
            'allow_registration' => 0,
            'default_email_address' => null,
        ]);
    }
}
