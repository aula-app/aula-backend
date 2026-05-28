<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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

        $dbName = $tenant->database()->getName();
        if ($dbName === null || $dbName === '') {
            $this->error('Tenant database name not found.');

            return self::FAILURE;
        }

        $dbUser = $tenant->database()->getUsername() ?? "aula_{$tenant->instance_code}";

        $outputFile = $this->option('output')
            ?? getcwd().'/tenant_'.$tenant->instance_code.'_'.now()->format('Ymd_His').'.tar.gz';

        $tmpDir = sys_get_temp_dir().'/tenant_export_'.uniqid();
        mkdir($tmpDir, 0700, true);

        try {
            $tenantData = $tenant->toArray();
            $tenantData['_source'] = [
                'db_name' => $dbName,
                'db_username' => $dbUser,
            ];
            file_put_contents(
                "{$tmpDir}/tenant.json",
                json_encode($tenantData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
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

            $this->info("Dumping privileges for user: {$dbUser}");

            $result = Process::env(['MYSQL_PWD' => $pass])->run([
                'mariadb-dump',
                "--host={$host}",
                "--port={$port}",
                "--user={$user}",
                '--single-transaction',
                '--system=users',
            ]);

            if (! $result->successful()) {
                $this->error('mariadb-dump --system=users failed:');
                $this->line($result->errorOutput());

                return self::FAILURE;
            }

            // Keep only the lines that reference the tenant's DB user (CREATE USER,
            // GRANT, conditional ALTER USER / SET DEFAULT ROLE directives). Session
            // state directives from the dump (SET NAMES, OLD_TIME_ZONE save/restore,
            // EXECUTE IMMEDIATE on @current_role, etc.) are skipped: they rely on
            // session continuity we cannot preserve when replaying statements one by
            // one via DB::statement, and they are not needed to provision a single
            // user with grants on the target server.
            $userQ = preg_quote($dbUser, '/');
            $ourUserHost = "/[`']{$userQ}[`']@[`'](localhost|%|127\\.0\\.0\\.1|::1|172)[`']/";
            $lines = preg_grep($ourUserHost, explode("\n", $result->output()));

            $schema = DB::selectOne(
                'SELECT default_character_set_name AS charset, default_collation_name AS collation_name
                   FROM information_schema.schemata WHERE schema_name = ?',
                [$dbName]
            );
            $charset = $schema->charset ?? 'utf8mb4';
            $collation = $schema->collation_name ?? 'utf8mb4_unicode_ci';

            $setupSql = "CREATE DATABASE `{$dbName}` CHARACTER SET {$charset} COLLATE {$collation};\n"
                .implode("\n", $lines)."\n";
            file_put_contents("{$tmpDir}/setup.sql", $setupSql);

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
