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

        if (! file_exists("{$tmpDir}/tenant.json") || ! file_exists("{$tmpDir}/tenant.sql") || ! file_exists("{$tmpDir}/setup.sql")) {
            $this->error('Archive is missing tenant.json, tenant.sql, or setup.sql.');

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

        $sourceDbName = $exported['_source']['db_name'] ?? null;
        $sourceDbUser = $exported['_source']['db_username'] ?? null;
        if ($sourceDbName === null || $sourceDbUser === null) {
            $this->error('tenant.json is missing _source.db_name or _source.db_username.');

            return self::FAILURE;
        }

        $this->info("Creating database and user: {$dbName} / {$dbUser}");

        $this->executeSetupSql($tmpDir, $sourceDbName, $sourceDbUser, $dbName, $dbUser, $dbPass);

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

    private function executeSetupSql(
        string $tmpDir,
        string $sourceDbName,
        string $sourceDbUser,
        string $dbName,
        string $dbUser,
        string $dbPass,
    ): void {
        $sql = file_get_contents("{$tmpDir}/setup.sql");

        // The setup.sql was produced from the source server's mariadb-dump --system=users
        // output (plus a CREATE DATABASE line keyed to the source DB), so it references
        // the source DB name and user. Rewrite those to the new tenant's identifiers and
        // swap the existing IDENTIFIED clause (hashed password / auth plugin) for a fresh
        // plaintext password that mariadb will hash on CREATE USER.
        $sql = str_replace(
            [$sourceDbName, $sourceDbUser],
            [$dbName, $dbUser],
            $sql
        );
        $sql = preg_replace(
            "/IDENTIFIED\\s+(?:BY\\s+PASSWORD|VIA\\s+\\w+(?:\\s+USING)?|BY)\\s+'[^']*'/i",
            sprintf("IDENTIFIED BY '%s'", addslashes($dbPass)),
            $sql
        );

        foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $statement) {
            DB::statement($statement);
        }
    }
}
