<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

function makeTenantArchive(array $overrides = []): string
{
    $data = array_merge([
        'id'              => '00000000-0000-0000-0000-000000000001',
        'name'            => 'Import School',
        'instance_code'   => 'IMP01',
        'jwt_key'         => 'testkey',
        'contact_info'    => null,
        'admin1_name'     => 'Admin One',
        'admin1_username' => 'admin1',
        'admin1_email'    => 'admin1@test.com',
        'admin2_name'     => 'Admin Two',
        'admin2_username' => 'admin2',
        'admin2_email'    => 'admin2@test.com',
    ], $overrides);

    $dir = sys_get_temp_dir().'/test_import_src_'.uniqid();
    mkdir($dir, 0700, true);
    file_put_contents("{$dir}/tenant.json", json_encode($data, JSON_UNESCAPED_UNICODE));
    file_put_contents("{$dir}/tenant.sql", '-- empty');
    file_put_contents("{$dir}/setup.sql", implode("\n", [
        "CREATE DATABASE `{{DB_NAME}}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;",
        "CREATE USER `{{DB_USER}}`@`%` IDENTIFIED BY '{{DB_PASS}}';",
        "GRANT SELECT ON `{{DB_NAME}}`.* TO `{{DB_USER}}`@`%`;",
    ]));

    $archive = sys_get_temp_dir().'/test_import_'.uniqid().'.tar.gz';
    exec("tar -czf {$archive} -C {$dir} tenant.json tenant.sql setup.sql");
    exec("rm -rf {$dir}");

    return $archive;
}

function createTenantForImportTest(array $attrs = []): Tenant
{
    return Tenant::withoutEvents(function () use ($attrs) {
        $tenant = new Tenant($attrs);
        $tenant->id = (string) Str::uuid();
        $tenant->save();

        return $tenant;
    });
}

function cleanupImportedTenant(string $instanceCode): void
{
    $tenant = Tenant::firstWhere('instance_code', $instanceCode);
    if (! $tenant) {
        return;
    }

    $dbName  = $tenant->getInternal('db_name');
    $dbUser  = $tenant->getInternal('db_username');

    if ($dbName) {
        DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
    }
    if ($dbUser) {
        DB::statement("DROP USER IF EXISTS `{$dbUser}`@`%`");
    }

    $tenant->withoutEvents(fn () => $tenant->forceDelete());
}

it('fails when the archive file does not exist', function () {
    $this->artisan('tenant:import', ['file' => '/tmp/nonexistent_archive.tar.gz'])->assertFailed();
});

it('fails when instance code format is invalid', function () {
    $archive = makeTenantArchive(['instance_code' => 'BAD!CODE']);

    $this->artisan('tenant:import', ['file' => $archive, '--force' => true])->assertFailed();

    unlink($archive);
});

it('fails when a tenant with the same instance code already exists', function () {
    $tenant = createTenantForImportTest([
        'name'            => 'Existing School',
        'instance_code'   => 'IMP01',
        'jwt_key'         => 'key',
        'api_base_url'    => 'https://example.com',
        'admin1_name'     => 'Admin One',
        'admin1_username' => 'admin1',
        'admin1_email'    => 'admin1@test.com',
        'admin2_name'     => 'Admin Two',
        'admin2_username' => 'admin2',
        'admin2_email'    => 'admin2@test.com',
    ]);

    $archive = makeTenantArchive();

    $this->artisan('tenant:import', ['file' => $archive, '--force' => true])->assertFailed();

    unlink($archive);
    $tenant->withoutEvents(fn () => $tenant->forceDelete());
});

it('fails when a tenant with the same name already exists', function () {
    $tenant = createTenantForImportTest([
        'name'            => 'Import School',
        'instance_code'   => 'OTH01',
        'jwt_key'         => 'key',
        'api_base_url'    => 'https://example.com',
        'admin1_name'     => 'Admin One',
        'admin1_username' => 'admin1',
        'admin1_email'    => 'admin1@test.com',
        'admin2_name'     => 'Admin Two',
        'admin2_username' => 'admin2',
        'admin2_email'    => 'admin2@test.com',
    ]);

    $archive = makeTenantArchive();

    $this->artisan('tenant:import', ['file' => $archive, '--force' => true])->assertFailed();

    unlink($archive);
    $tenant->withoutEvents(fn () => $tenant->forceDelete());
});

it('imports a tenant and creates the database and user', function () {
    $archive = makeTenantArchive();

    Process::fake(["'mysql'*" => Process::result()]);

    $this->artisan('tenant:import', ['file' => $archive, '--force' => true])->assertSuccessful();

    $tenant = Tenant::firstWhere('instance_code', 'IMP01');
    expect($tenant)->not->toBeNull();

    $dbName = $tenant->getInternal('db_name');
    $dbUser = $tenant->getInternal('db_username');
    $dbPass = $tenant->getInternal('db_password');

    expect($dbName)->not->toBeNull();
    expect($dbUser)->toBe('aula_IMP01');
    expect($dbPass)->not->toBeNull();

    $databases = DB::select("SHOW DATABASES LIKE '{$dbName}'");
    expect($databases)->not->toBeEmpty();

    $userExists = DB::select("SELECT COUNT(*) as cnt FROM mysql.user WHERE user = '{$dbUser}'")[0]->cnt;
    expect($userExists)->toBe(1);

    unlink($archive);
    cleanupImportedTenant('IMP01');
});

it('accepts SINGLE as a valid instance code', function () {
    $archive = makeTenantArchive(['instance_code' => 'SINGLE', 'name' => 'Single School']);

    Process::fake(["'mysql'*" => Process::result()]);

    $this->artisan('tenant:import', ['file' => $archive, '--force' => true])->assertSuccessful();

    expect(Tenant::firstWhere('instance_code', 'SINGLE'))->not->toBeNull();

    unlink($archive);
    cleanupImportedTenant('SINGLE');
});

it('overrides instance code and name via options', function () {
    $archive = makeTenantArchive();

    Process::fake(["'mysql'*" => Process::result()]);

    $this->artisan('tenant:import', [
        'file'    => $archive,
        '--code'  => 'OVR01',
        '--name'  => 'Overridden School',
        '--force' => true,
    ])->assertSuccessful();

    expect(Tenant::firstWhere('instance_code', 'OVR01'))->not->toBeNull();

    unlink($archive);
    cleanupImportedTenant('OVR01');
});

it('fails when mysql import fails', function () {
    $archive = makeTenantArchive(['instance_code' => 'FAIL1', 'name' => 'Fail School']);

    Process::fake(["'mysql'*" => Process::result('', 'Access denied', 1)]);

    $this->artisan('tenant:import', ['file' => $archive, '--force' => true])->assertFailed();

    unlink($archive);
    cleanupImportedTenant('FAIL1');
});
