<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class ExportTenant extends Command
{
    protected $signature = 'tenant:export
                            {--code= : Instance code of the tenant to export}
                            {--id= : UUID of the tenant to export}
                            {--output= : Output file path (default: tenant_CODE_DATETIME.tar.gz in current directory)}';

    protected $description = 'Export a tenant\'s central DB row and database dump to a tar.gz archive';

    public function handle(): int
    {
        $tenant = $this->resolveTenant();
        if ($tenant === null) {
            return self::FAILURE;
        }

        $this->info("Exporting tenant: {$tenant->name} (code: {$tenant->instance_code})");

        $dbName = $tenant->tenancy_db_name ?? null;
        if ($dbName === null) {
            $this->error('Tenant database name not found (tenancy_db_name missing).');

            return self::FAILURE;
        }

        $outputFile = $this->option('output')
            ?? getcwd().'/tenant_'.$tenant->instance_code.'_'.now()->format('Ymd_His').'.tar.gz';

        $tmpDir = sys_get_temp_dir().'/tenant_export_'.uniqid();
        mkdir($tmpDir, 0700, true);

        try {
            file_put_contents(
                "{$tmpDir}/tenant.json",
                json_encode($tenant->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            $this->info("Dumping database: {$dbName}");

            $host = config('database.connections.mariadb.host');
            $port = (string) config('database.connections.mariadb.port');
            $user = config('database.connections.mariadb.username');
            $pass = (string) config('database.connections.mariadb.password');

            $result = Process::env(['MYSQL_PWD' => $pass])->run([
                'mysqldump',
                "--host={$host}",
                "--port={$port}",
                "--user={$user}",
                '--single-transaction',
                '--routines',
                '--triggers',
                '--result-file='.$tmpDir.'/tenant.sql',
                $dbName,
            ]);

            if (! $result->successful()) {
                $this->error('mysqldump failed:');
                $this->line($result->errorOutput());

                return self::FAILURE;
            }

            $grants = implode(', ', [
                'ALTER', 'ALTER ROUTINE', 'CREATE', 'CREATE ROUTINE', 'CREATE TEMPORARY TABLES', 'CREATE VIEW',
                'DELETE', 'DROP', 'EVENT', 'EXECUTE', 'INDEX', 'INSERT', 'LOCK TABLES', 'REFERENCES', 'SELECT',
                'SHOW VIEW', 'TRIGGER', 'UPDATE',
            ]);
            file_put_contents("{$tmpDir}/setup.sql", implode("\n", [
                "CREATE DATABASE `{{DB_NAME}}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;",
                "CREATE USER `{{DB_USER}}`@`%` IDENTIFIED BY '{{DB_PASS}}';",
                "GRANT {$grants} ON `{{DB_NAME}}`.* TO `{{DB_USER}}`@`%`;",
            ]));

            $result = Process::run([
                'tar', '-czf', $outputFile,
                '-C', $tmpDir,
                'tenant.json', 'tenant.sql', 'setup.sql',
            ]);

            if (! $result->successful()) {
                $this->error('Failed to create archive:');
                $this->line($result->errorOutput());

                return self::FAILURE;
            }

            $this->info("Export complete: {$outputFile}");

            return self::SUCCESS;
        } finally {
            Process::run(['rm', '-rf', $tmpDir]);
        }
    }

    private function resolveTenant(): ?Tenant
    {
        $code = $this->option('code');
        $id = $this->option('id');

        if ($code === null && $id === null) {
            $this->error('Provide --code or --id to identify the tenant.');

            return null;
        }

        $tenant = $code
            ? Tenant::firstWhere('instance_code', $code)
            : Tenant::find($id);

        if ($tenant === null) {
            $this->error('Tenant not found.');

            return null;
        }

        return $tenant;
    }
}
