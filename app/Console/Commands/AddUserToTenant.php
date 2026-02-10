<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AddUserToTenant extends Command
{
    protected $signature = 'tenant:add-user';

    protected $description = 'Add a user to a tenant instance';

    private const ROLES = [
        10 => 'Guest',
        20 => 'User',
        30 => 'Moderator',
        31 => 'Moderator+',
        40 => 'Super Moderator',
        41 => 'Super Moderator+',
        44 => 'Principal',
        45 => 'Principal+',
        50 => 'Admin',
        60 => 'Tech Admin',
    ];

    public function handle(): int
    {
        // Select tenant
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->error('No tenants found. Create one first with tenant:create.');

            return self::FAILURE;
        }

        $tenantChoices = $tenants->mapWithKeys(function (Tenant $tenant) {
            return [$tenant->instance_code => "{$tenant->name} ({$tenant->instance_code})"];
        })->toArray();

        $selectedCode = $this->choice(
            'Select tenant',
            array_values($tenantChoices),
        );

        // Extract instance code from the choice label
        preg_match('/\(([^)]+)\)$/', $selectedCode, $matches);
        $instanceCode = $matches[1];

        $tenant = Tenant::where('instance_code', $instanceCode)->first();

        if (! $tenant) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $this->info("Adding user to tenant: {$tenant->name} ({$tenant->instance_code})");
        $this->newLine();

        // Collect user information
        $username = $this->ask('Username');
        $fullName = $this->ask('Full name');
        $email = $this->ask('Email');

        if (empty($username) || empty($fullName) || empty($email)) {
            $this->error('Username, full name, and email are required.');

            return self::FAILURE;
        }

        // Select role
        $roleChoices = array_map(
            fn ($level, $name) => "{$name} ({$level})",
            array_keys(self::ROLES),
            array_values(self::ROLES)
        );

        $selectedRole = $this->choice('Select role', $roleChoices, 8); // Default: Admin (50)

        preg_match('/\((\d+)\)$/', $selectedRole, $matches);
        $userlevel = (int) $matches[1];
        $roleName = self::ROLES[$userlevel];

        // Ask for password
        $password = $this->secret('Password');

        if (empty($password)) {
            $this->error('Password is required.');

            return self::FAILURE;
        }

        $passwordConfirm = $this->secret('Confirm password');

        if ($password !== $passwordConfirm) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        // Summary
        $this->newLine();
        $this->info('=== Summary ===');
        $this->table(
            ['Field', 'Value'],
            [
                ['Tenant', "{$tenant->name} ({$tenant->instance_code})"],
                ['Username', $username],
                ['Full Name', $fullName],
                ['Email', $email],
                ['Role', "{$roleName} ({$userlevel})"],
            ]
        );

        if (! $this->confirm('Create this user?', true)) {
            $this->warn('User creation cancelled.');

            return self::SUCCESS;
        }

        // Create user in tenant database
        try {
            $tenant->run(function () use ($username, $fullName, $email, $password, $userlevel) {
                $now = now();

                // Check if username already exists
                $existing = DB::table('au_users_basedata')
                    ->where('username', $username)
                    ->exists();

                if ($existing) {
                    throw new \RuntimeException("Username '{$username}' already exists in this tenant.");
                }

                DB::table('au_users_basedata')->insert([
                    'realname' => $fullName,
                    'displayname' => $fullName,
                    'username' => $username,
                    'email' => $email,
                    'pw' => password_hash($password, PASSWORD_BCRYPT),
                    'hash_id' => Str::random(32),
                    'registration_status' => 2,
                    'status' => 1,
                    'userlevel' => $userlevel,
                    'created' => $now,
                    'last_update' => $now,
                    'pw_changed' => 1,
                    'presence' => 1,
                    'roles' => '[]',
                ]);
            });
        } catch (\Exception $e) {
            $this->error("Failed to create user: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("User '{$username}' created successfully as {$roleName}.");

        return self::SUCCESS;
    }
}
