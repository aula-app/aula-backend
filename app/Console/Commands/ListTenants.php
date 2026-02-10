<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ListTenants extends Command
{
    protected $signature = 'tenant:list';

    protected $description = 'List all tenants with instance codes and admin users';

    public function handle(): int
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->info('No tenants found.');

            return self::SUCCESS;
        }

        $this->info("Found {$tenants->count()} tenant(s).");
        $this->newLine();

        foreach ($tenants as $tenant) {
            $this->info("=== {$tenant->name} ===");
            $this->table(
                ['Field', 'Value'],
                [
                    ['ID', $tenant->id],
                    ['Instance Code', $tenant->instance_code],
                    ['API Base URL', $tenant->api_base_url],
                    ['Created', $tenant->created_at?->format('Y-m-d H:i:s') ?? '-'],
                ]
            );

            // Fetch admin users from the tenant database
            $this->line('  Admin users (au_users_basedata):');

            try {
                $admins = $tenant->run(function () {
                    return DB::table('au_users_basedata')
                        ->where('userlevel', '>=', 50)
                        ->orderBy('userlevel', 'desc')
                        ->orderBy('id')
                        ->get(['id', 'username', 'realname', 'email', 'userlevel', 'status']);
                });

                if ($admins->isEmpty()) {
                    $this->line('  No admin users found.');
                } else {
                    $rows = $admins->map(function ($admin) {
                        $level = match ($admin->userlevel) {
                            60 => 'Tech Admin',
                            50 => 'Admin',
                            default => "Level {$admin->userlevel}",
                        };

                        $status = match ($admin->status) {
                            0 => 'Inactive',
                            1 => 'Active',
                            2 => 'Suspended',
                            3 => 'Archived',
                            default => "Unknown ({$admin->status})",
                        };

                        return [
                            $admin->id,
                            $admin->username,
                            $admin->realname,
                            $admin->email,
                            $level,
                            $status,
                        ];
                    })->toArray();

                    $this->table(
                        ['ID', 'Username', 'Full Name', 'Email', 'Role', 'Status'],
                        $rows
                    );
                }
            } catch (\Exception $e) {
                $this->warn("  Could not fetch admin users: {$e->getMessage()}");
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
