<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class ImportTenant extends Command
{
    protected $signature = 'tenant:import
                            {file : Path to the .tar.gz export file produced by tenant:export}
                            {--code= : Override the instance code from the export (must be 5 alphanumeric chars or SINGLE)}
                            {--name= : Override the tenant name from the export}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Import a tenant from a tar.gz archive produced by tenant:export';

    public function handle(): int
    {
        $file = $this->argument('file');

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $tmpDir = sys_get_temp_dir().'/tenant_import_'.uniqid();
        mkdir($tmpDir, 0700, true);

        try {
            return $this->doImport($file, $tmpDir);
        } finally {
            Process::run(['rm', '-rf', $tmpDir]);
        }
    }

    private function doImport(string $file, string $tmpDir): int
    {
        $result = Process::run(['tar', '-xzf', $file, '-C', $tmpDir]);
        if (! $result->successful()) {
            $this->error('Failed to extract archive:');
            $this->line($result->errorOutput());

            return self::FAILURE;
        }

        if (! file_exists("{$tmpDir}/tenant.json") || ! file_exists("{$tmpDir}/tenant.sql")) {
            $this->error('Archive is missing tenant.json or tenant.sql.');

            return self::FAILURE;
        }

        $exported = json_decode(file_get_contents("{$tmpDir}/tenant.json"), true);
        if ($exported === null) {
            $this->error('tenant.json is not valid JSON.');

            return self::FAILURE;
        }

        $instanceCode = $this->option('code') ?? $exported['instance_code'];
        $tenantName = $this->option('name') ?? $exported['name'];

        if (! preg_match('/^[0-9a-zA-Z]{5}$|^SINGLE$/', $instanceCode)) {
            $this->error('Instance code must be exactly 5 alphanumeric characters, or SINGLE.');

            return self::FAILURE;
        }

        if (Tenant::firstWhere('instance_code', $instanceCode) !== null) {
            $this->error("A tenant with instance code '{$instanceCode}' already exists on this server.");

            return self::FAILURE;
        }

        if (Tenant::firstWhere('name', $tenantName) !== null) {
            $this->error("A tenant named '{$tenantName}' already exists on this server. Use --name to override.");

            return self::FAILURE;
        }

        $this->info('=== Tenant to import ===');
        $this->table(['Field', 'Value'], [
            ['Name', $tenantName],
            ['Instance code', $instanceCode],
            ['Original code', $exported['instance_code']],
            ['Original ID', $exported['id']],
        ]);

        if (! $this->option('force') && ! $this->confirm('Proceed with import?', true)) {
            $this->warn('Import cancelled.');

            return self::SUCCESS;
        }

        $tenantId = Str::uuid()->toString();
        $dbName = 'tenant_'.$tenantId;
        $dbUser = 'aula_'.$instanceCode;
        $dbPass = Str::random(63);

        $this->info('Creating tenant record...');

        Tenant::withoutEvents(function () use ($exported, $tenantId, $instanceCode, $tenantName, $dbName, $dbUser, $dbPass) {
            $tenant = new Tenant;
            $tenant->id = $tenantId;
            $tenant->name = $tenantName;
            $tenant->api_base_url = config('app.url');
            $tenant->contact_info = $exported['contact_info'] ?? null;
            $tenant->instance_code = $instanceCode;
            $tenant->jwt_key = $exported['jwt_key'];
            $tenant->admin1_name = $exported['admin1_name'] ?? null;
            $tenant->admin1_username = $exported['admin1_username'] ?? null;
            $tenant->admin1_email = $exported['admin1_email'] ?? null;
            $tenant->admin1_init_pass_url = null;
            $tenant->admin2_name = $exported['admin2_name'] ?? null;
            $tenant->admin2_username = $exported['admin2_username'] ?? null;
            $tenant->admin2_email = $exported['admin2_email'] ?? null;
            $tenant->admin2_init_pass_url = null;
            $tenant->setInternal('db_name', $dbName);
            $tenant->setInternal('db_username', $dbUser);
            $tenant->setInternal('db_password', $dbPass);
            $tenant->save();
        });

        $this->info("Creating database: {$dbName}");

        DB::statement("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $grants = implode(', ', [
            'ALTER', 'ALTER ROUTINE', 'CREATE', 'CREATE ROUTINE', 'CREATE TEMPORARY TABLES', 'CREATE VIEW',
            'DELETE', 'DROP', 'EVENT', 'EXECUTE', 'INDEX', 'INSERT', 'LOCK TABLES', 'REFERENCES', 'SELECT',
            'SHOW VIEW', 'TRIGGER', 'UPDATE',
        ]);
        DB::statement("CREATE USER `{$dbUser}`@`%` IDENTIFIED BY '{$dbPass}'");
        DB::statement("GRANT {$grants} ON `{$dbName}`.* TO `{$dbUser}`@`%`");

        $this->info('Importing database dump...');

        $host = config('database.connections.mariadb.host');
        $port = (string) config('database.connections.mariadb.port');
        $user = config('database.connections.mariadb.username');
        $pass = (string) config('database.connections.mariadb.password');

        $result = Process::env(['MYSQL_PWD' => $pass])->input(
            file_get_contents("{$tmpDir}/tenant.sql")
        )->run([
            'mysql',
            "--host={$host}",
            "--port={$port}",
            "--user={$user}",
            $dbName,
        ]);

        if (! $result->successful()) {
            $this->error('mysql import failed:');
            $this->line($result->errorOutput());
            $this->warn('Tenant record and database were created but data import failed. Clean up manually.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Tenant '{$tenantName}' imported successfully.");
        $this->line("  ID:            {$tenantId}");
        $this->line("  Instance code: {$instanceCode}");
        $this->line("  Database:      {$dbName}");

        return self::SUCCESS;
    }
}
