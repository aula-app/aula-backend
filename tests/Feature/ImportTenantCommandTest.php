<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

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

    $archive = sys_get_temp_dir().'/test_import_'.uniqid().'.tar.gz';
    exec("tar -czf {$archive} -C {$dir} tenant.json tenant.sql");
    exec("rm -rf {$dir}");

    return $archive;
}

function createTenantForImportTest(array $attrs = []): Tenant
{
    // withoutEvents prevents stancl from running CreateDatabase (DDL that commits the transaction)
    return Tenant::withoutEvents(function () use ($attrs) {
        $tenant = new Tenant($attrs);
        $tenant->id = (string) Str::uuid();
        $tenant->save();

        return $tenant;
    });
}

function mockCreateDatabase(): void
{
    // Intercept all raw statements (DDL causes implicit commits that break DatabaseTransactions)
    DB::partialMock()->shouldReceive('statement')->andReturn(true);
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
    createTenantForImportTest([
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
});

it('fails when a tenant with the same name already exists', function () {
    createTenantForImportTest([
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
});

it('imports a tenant successfully', function () {
    $archive = makeTenantArchive();

    mockCreateDatabase();
    Process::fake(["'mysql'*" => Process::result()]);

    $this->artisan('tenant:import', ['file' => $archive, '--force' => true])->assertSuccessful();

    expect(Tenant::firstWhere('instance_code', 'IMP01'))->not->toBeNull();

    unlink($archive);
});

it('accepts SINGLE as a valid instance code', function () {
    $archive = makeTenantArchive(['instance_code' => 'SINGLE', 'name' => 'Single School']);

    mockCreateDatabase();
    Process::fake(["'mysql'*" => Process::result()]);

    $this->artisan('tenant:import', ['file' => $archive, '--force' => true])->assertSuccessful();

    unlink($archive);
});

it('overrides instance code and name via options', function () {
    $archive = makeTenantArchive();

    mockCreateDatabase();
    Process::fake(["'mysql'*" => Process::result()]);

    $this->artisan('tenant:import', [
        'file'    => $archive,
        '--code'  => 'OVR01',
        '--name'  => 'Overridden School',
        '--force' => true,
    ])->assertSuccessful();

    expect(Tenant::firstWhere('instance_code', 'OVR01'))->not->toBeNull();

    unlink($archive);
});

it('fails when mysql import fails', function () {
    $archive = makeTenantArchive(['instance_code' => 'FAIL1', 'name' => 'Fail School']);

    mockCreateDatabase();
    Process::fake(["'mysql'*" => Process::result('', 'Access denied', 1)]);

    $this->artisan('tenant:import', ['file' => $archive, '--force' => true])->assertFailed();

    unlink($archive);
});
