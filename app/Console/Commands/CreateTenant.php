<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantCreationService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateTenant extends Command
{
    protected $signature = 'tenant:create';

    protected $description = 'Create a new tenant with database, admin users, and JWT key';

    public function __construct(private readonly TenantCreationService $tenantCreationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('=== Create New Tenant ===');
        $this->newLine();

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

        $this->info('--- Admin User ---');
        do {
            $admin1Username = $this->ask('Admin username');
        } while (empty($admin1Username));
        $admin1FullName = $this->ask('Admin full name', 'admin1');
        do {
            $admin1Email = $this->ask('Admin email');
        } while (empty($admin1Email));

        $this->newLine();
        $this->info('--- Second Admin User ---');
        do {
            $admin2Username = $this->ask('Second admin username');
            if (empty($admin2Username)) {
                $this->warn('Second admin has to have a username.');
                continue;
            }
            if ($admin1Username === $admin2Username) {
                $this->warn('Second admin username has to be different from the first admin username.');
                continue;
            }
            break;
        } while (true);
        $admin2FullName = $this->ask('Second admin full name', 'admin2');
        do {
            $admin2Email = $this->ask('Second admin email');
        } while (empty($admin2Email));

        $instanceCode = $this->tenantCreationService->generateUniqueInstanceCode();

        $this->newLine();
        $this->info('=== Summary ===');
        $this->table(
            ['Field', 'Value'],
            [
                ['Tenant Name', $tenantName],
                ['Instance Code', $instanceCode],
                ['API Base URL', config('app.url')],
                ['Admin Username', $admin1Username],
                ['Admin Full Name', $admin1FullName],
                ['Admin Email', $admin1Email],
                ['Second Admin Username', $admin2Username],
                ['Second Admin Full Name', $admin2FullName],
                ['Second Admin Email', $admin2Email],
            ]
        );

        if (! $this->confirm('Do you want to proceed with creating this tenant?', true)) {
            $this->warn('Tenant creation cancelled.');
            return self::SUCCESS;
        }

        $this->info('Creating tenant...');

        try {
            $tenant = $this->tenantCreationService->create(
                name: $tenantName,
                admin1Username: $admin1Username,
                admin1FullName: $admin1FullName,
                admin1Email: $admin1Email,
                admin2Username: $admin2Username,
                admin2FullName: $admin2FullName,
                admin2Email: $admin2Email,
            );

            $this->newLine();
            $this->info("Tenant '{$tenant->name}' created successfully (ID: {$tenant->id})");
            $this->newLine();
            $this->info('=== Password Reset URLs ===');
            $this->line("Admin ({$admin1Username}): {$tenant->admin1_init_pass_url}");
            $this->newLine();
            $this->line("Second Admin ({$admin2Username}): {$tenant->admin2_init_pass_url}");

        } catch (\Exception $e) {
            $this->error("Failed to create tenant: {$e->getMessage()}");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
