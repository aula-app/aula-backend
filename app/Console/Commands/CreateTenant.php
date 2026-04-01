<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantsService;
use App\UseCases\CreateTenantUseCase;
use Illuminate\Console\Command;

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

    public function __construct(
        private readonly TenantsService $tenantsService,
        private readonly CreateTenantUseCase $createTenantUseCase,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Gather required options
        $tenantName = $this->option('name');
        $admin1Username = $this->option('admin-username');
        $admin1Email = $this->option('admin-email');
        $admin2Username = $this->option('admin2-username');
        $admin2Email = $this->option('admin2-email');

        // Gather optional options' values
        $instanceCode = $this->option('code');
        $admin1FullName = $this->option('admin-name');
        $admin2FullName = $this->option('admin2-name');
        $adminPassword = $this->option('admin-password');

        // When all required options are supplied we run non-interactively
        if (! empty($tenantName)
            && ! empty($admin1Username)
            && ! empty($admin1Email)
            && ! empty($admin2Username)
            && ! empty($admin2Email)) {

            // Idempotency: in non-interactive mode, skip creation if the tenant already
            // exists with the requested code. This lets `docker compose up` be re-run
            // against a persistent volume without failing.
            if ($instanceCode !== null) {
                if (Tenant::firstWhere('instance_code', $instanceCode) !== null) {
                    $this->info("Tenant with code '{$instanceCode}' already exists, skipping.");

                    return self::SUCCESS;
                }
            }

            if (Tenant::firstWhere('name', $tenantName) !== null) {
                $this->error("Tenant name '{$tenantName}' already exists.");

                return self::FAILURE;
            }

            if ($admin1Username === $admin2Username) {
                $this->error('Second admin username must differ from the first admin username.');

                return self::FAILURE;
            }

            if ($instanceCode !== null && ! preg_match('/^[0-9a-zA-Z]{5}$|^SINGLE$/', $instanceCode)) {
                $this->error('Instance code must be exactly 5 alphanumeric characters, or SINGLE.');

                return self::FAILURE;
            }

            $instanceCode ??= $this->tenantsService->generateUniqueInstanceCode();
            $admin1FullName ??= 'admin1';
            $admin2FullName ??= 'admin2';

            return $this->createTenant(
                $tenantName, $instanceCode,
                $admin1Username, $admin1FullName, $admin1Email,
                $admin2Username, $admin2FullName, $admin2Email,
                $adminPassword,
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
        while (empty($admin1Username)) {
            $admin1Username = $this->ask('Admin username');
        }
        $admin1FullName ??= $this->ask('Admin full name', 'admin1');
        while (empty($admin1Email)) {
            $admin1Email = $this->ask('Admin email');
        }

        // Second admin user information
        $this->newLine();
        $this->info('--- Second Admin User ---');
        while (empty($admin2Username)) {
            $this->warn('Second admin has to have a username.');
            $admin2Username = $this->ask('Second admin username');
            if ($admin1Username === $admin2Username) {
                $this->warn('Second admin username has to be different from the first admin username.');
                $admin2Username = null;

                continue;
            }
        }
        $admin2FullName ??= $this->ask('Second admin full name', 'admin2');
        while (empty($admin2Email)) {
            $admin2Email = $this->ask('Second admin email');
        }

        // Resolve instance code: use --code option, or generate interactively
        if ($instanceCode === null) {
            do {
                $instanceCode = $this->tenantsService->generateUniqueInstanceCode();
                $this->newLine();
                $this->info("Generated instance code: <comment>{$instanceCode}</comment>");
            } while (! $this->confirm('Do you confirm this instance code?', true));
        }

        // Show summary and ask for confirmation when running interactively
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

        return $this->createTenant(
            $tenantName, $instanceCode,
            $admin1Username, $admin1FullName, $admin1Email,
            $admin2Username, $admin2FullName, $admin2Email,
            $adminPassword,
        );
    }

    private function createTenant(
        string $tenantName,
        string $instanceCode,
        string $admin1Username,
        string $admin1FullName,
        string $admin1Email,
        string $admin2Username,
        string $admin2FullName,
        string $admin2Email,
        ?string $adminPassword,
    ): int {
        $this->info('Creating tenant...');

        try {
            $tenant = $this->createTenantUseCase->execute(
                name: $tenantName,
                instanceCode: $instanceCode,
                admin1Username: $admin1Username,
                admin1FullName: $admin1FullName,
                admin1Email: $admin1Email,
                admin2Username: $admin2Username,
                admin2FullName: $admin2FullName,
                admin2Email: $admin2Email,
                adminPassword: $adminPassword,
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
