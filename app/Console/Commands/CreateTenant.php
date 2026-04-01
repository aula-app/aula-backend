<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTenant extends Command
{
    protected $signature = 'tenant:create
                            {--name= : Tenant name (skips interactive prompt)}
                            {--code= : Instance code — 5 alphanumeric chars, or SINGLE (auto-generated if omitted)}
                            {--admin-username= : First admin username}
                            {--admin-name= : First admin full name (default: admin1)}
                            {--admin-email= : First admin email}
                            {--admin2-username= : Second admin username}
                            {--admin2-name= : Second admin full name (default: admin2)}
                            {--admin2-email= : Second admin email}
                            {--admin-password= : Pre-set a bcrypt password for both admin users (skips email-based setup flow)}';

    protected $description = 'Create a new tenant with database, admin users, and JWT key';

    public function handle(): int
    {
        $jwtKey = Str::random(64);
        $apiBaseUrl = config('app.url');

        // Gather required options
        $tenantName = $this->option('name');
        $adminUsername = $this->option('admin-username');
        $adminEmail = $this->option('admin-email');
        $secondAdminUsername = $this->option('admin2-username');
        $secondAdminEmail = $this->option('admin2-email');

        // Gather optional options' values
        $instanceCode = $this->option('code');
        $adminFullName = $this->option('admin-name');
        $secondAdminFullName = $this->option('admin2-name');
        $adminPassword = $this->option('admin-password');

        // When all required options are supplied we run non-interactively
        if (! empty($tenantName)
            && ! empty($adminUsername)
            && ! empty($adminEmail)
            && ! empty($secondAdminUsername)
            && ! empty($secondAdminEmail)) {

            // Idempotency: in non-interactive mode, skip creation if the tenant already
            // exists with the requested code. This lets `docker compose up` be re-run
            // against a persistent volume without failing.
            if ($instanceCode !== null) {
                if (Tenant::firstWhere('instance_code', $this->option('code')) !== null) {
                    $this->info("Tenant with code '{$this->option('code')}' already exists, skipping.");

                    return self::SUCCESS;
                }
            }
            if (Tenant::firstWhere('name', $tenantName) !== null) {
                $this->error("Tenant name '{$tenantName}' already exists.");

                return self::FAILURE;
            }

            if ($adminUsername === $secondAdminUsername) {
                $this->error('Second admin username must differ from the first admin username.');

                return self::FAILURE;
            }

            if (! preg_match('/^[0-9a-zA-Z]{5}$|^SINGLE$/', $instanceCode)) {
                $this->error('Instance code must be exactly 5 alphanumeric characters, or SINGLE.');

                return self::FAILURE;
            }

            return $this->executeCreateTenant(
                $tenantName, $apiBaseUrl, $instanceCode, $jwtKey,
                $adminFullName, $adminUsername, $adminEmail, $adminPassword,
                $secondAdminFullName, $secondAdminUsername, $secondAdminEmail
            );
        }

        $this->info('=== Create New Tenant ===');
        $this->newLine();

        // Collect tenant information
        while (empty($tenantName)) {
            $this->warn('Tenant has to have a name, maybe a school name?');
            $tenantName = $this->ask('Tenant name');
            if (Tenant::firstWhere('name', $tenantName) !== null) {
                $this->warn('Tenant\'s name has to be unique. Try choosing another name.');
                $tenantName = null;

                continue;
            }
        }

        // Admin user information
        $this->info('--- Admin User ---');
        while (empty($adminUsername)) {
            $adminUsername = $this->ask('Admin username');
        }
        $adminFullName ??= $this->ask('Admin full name', 'admin1');
        while (empty($adminEmail)) {
            $adminEmail = $this->ask('Admin email');
        }

        // Second admin user information
        $this->newLine();
        $this->info('--- Second Admin User ---');
        while (empty($secondAdminUsername)) {
            $this->warn('Second admin has to have a username.');
            $secondAdminUsername = $this->ask('Second admin username');
            if ($adminUsername === $secondAdminUsername) {
                $this->warn('Second admin username has to be different from the first admin username.');
                $secondAdminUsername = null;

                continue;
            }
        }
        $secondAdminFullName ??= $this->ask('Second admin full name', 'admin2');
        while (empty($secondAdminEmail)) {
            $secondAdminEmail = $this->ask('Second admin email');
        }

        // Resolve instance code: use --code option, or generate interactively
        $instanceCode ??= $this->generateUniqueInstanceCode();

        // Show summary and ask for confirmation when running interactively
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

        $this->info('Creating tenant...');
        $this->executeCreateTenant(
            $tenantName, $apiBaseUrl, $instanceCode, $jwtKey,
            $adminFullName, $adminUsername, $adminEmail,
            $secondAdminFullName, $secondAdminUsername, $secondAdminEmail
        );
        $this->newLine();
        $this->info('Tenant created successfully!');

        return self::SUCCESS;
    }

    private function executeCreateTenant($tenantName, $apiBaseUrl, $instanceCode, string $jwtKey, string $adminFullName, $adminUsername, string $adminEmail, ?string $adminPassword, string $secondAdminFullName, $secondAdminUsername, $secondAdminEmail)
    {
        $adminSecret = Str::random(64);
        $secondAdminSecret = Str::random(64);

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
                'admin1_init_pass_url' => "{$apiBaseUrl}/password/{$adminSecret}?code={$instanceCode}",
                'admin2_init_pass_url' => "{$apiBaseUrl}/password/{$secondAdminSecret}?code={$instanceCode}",
            ]);

            $this->info("Tenant created with ID: {$tenant->id}");
            $this->info('Database created and migrations executed.');

        } catch (\Exception $e) {
            $this->error("Failed to create tenant: {$e->getMessage()}");

            return self::FAILURE;
        }

        // Create admin users in the tenant database
        $this->info('Creating admin users in tenant database...');

        try {
            $tenant->run(function () use (
                $tenantName, $instanceCode, $apiBaseUrl,
                $adminUsername, $adminFullName, $adminEmail, $adminSecret,
                $secondAdminUsername, $secondAdminFullName, $secondAdminEmail, $secondAdminSecret,
                $adminPassword,
            ) {
                $now = now();

                $this->insertInitialSystemData($tenantName);

                // When --admin-password is given, hash it and mark the account as fully registered
                // so both admins can log in immediately without the email-based setup flow.
                $pwHash = $adminPassword !== null ? password_hash($adminPassword, PASSWORD_BCRYPT) : '';
                $pwChanged = $adminPassword !== null ? 1 : 0;

                // Create first admin user
                $adminId = DB::table('au_users_basedata')->insertGetId([
                    'realname' => $adminFullName,
                    'displayname' => $adminFullName,
                    'username' => $adminUsername,
                    'email' => $adminEmail,
                    'pw' => $pwHash,
                    'hash_id' => Str::random(32),
                    'registration_status' => 2,
                    'status' => 1,
                    'userlevel' => 50,
                    'created' => $now,
                    'last_update' => $now,
                    'pw_changed' => $pwChanged,
                    'presence' => 1,
                    'roles' => '[]',
                ]);

                if ($adminPassword === null) {
                    DB::table('au_change_password')->insert([
                        'user_id' => $adminId,
                        'secret' => $adminSecret,
                        'created_at' => $now,
                    ]);
                }

                // Create second admin user
                $secondAdminId = DB::table('au_users_basedata')->insertGetId([
                    'realname' => $secondAdminFullName,
                    'displayname' => $secondAdminFullName,
                    'username' => $secondAdminUsername,
                    'email' => $secondAdminEmail,
                    'pw' => $pwHash,
                    'hash_id' => Str::random(32),
                    'registration_status' => 2,
                    'status' => 1,
                    'userlevel' => 50,
                    'created' => $now,
                    'last_update' => $now,
                    'pw_changed' => $pwChanged,
                    'presence' => 1,
                    'roles' => '[]',
                ]);

                if ($adminPassword === null) {
                    DB::table('au_change_password')->insert([
                        'user_id' => $secondAdminId,
                        'secret' => $secondAdminSecret,
                        'created_at' => $now,
                    ]);
                }

                $this->newLine();
                if ($adminPassword !== null) {
                    $this->info('✅ Admin accounts created with the provided password.');
                } else {
                    $this->info('=== Password Reset URLs ===');
                    $this->line("Admin ({$adminUsername}):");
                    $this->line("  {$apiBaseUrl}/password/{$adminSecret}?code={$instanceCode}");
                    $this->newLine();
                    $this->line("Second Admin ({$secondAdminUsername}):");
                    $this->line("  {$apiBaseUrl}/password/{$secondAdminSecret}?code={$instanceCode}");
                }
            });

        } catch (\Exception $e) {
            $this->error("Failed to create admin users: {$e->getMessage()}");

            return self::FAILURE;
        }

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

    /**
     * @param  string  $name  - tenant name (school name)
     */
    private function insertInitialSystemData(string $name): void
    {
        $now = now();
        $testrand = rand(100, 10000000);
        $appendix = microtime(true).$testrand;
        $hash_id = md5('Schule'.$appendix);

        DB::table('au_rooms')->insert([
            'room_name' => 'Schule',
            'description_internal' => null,
            'hash_id' => $hash_id,
            'status' => 1,
            'type' => 1,
        ]);

        DB::table('au_system_current_state')->insert([
            'online_mode' => 1,
            'created' => $now,
            'last_update' => $now,
            'updater_id' => 2,
        ]);

        DB::table('au_system_global_config')->insert([
            'name' => $name,
            'allow_registration' => 0,
        ]);
    }
}
