<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

function createTenantForExport(array $attrs = []): Tenant
{
    // withoutEvents prevents stancl from running CreateDatabase (DDL that commits the transaction)
    return Tenant::withoutEvents(function () use ($attrs) {
        $tenant = new Tenant(array_merge([
            'name' => 'Export School',
            'instance_code' => 'EXP01',
            'jwt_key' => 'testkey',
            'api_base_url' => 'https://example.com',
            'admin1_name' => 'Admin One',
            'admin1_username' => 'admin1',
            'admin1_email' => 'admin1@test.com',
            'admin2_name' => 'Admin Two',
            'admin2_username' => 'admin2',
            'admin2_email' => 'admin2@test.com',
            'tenancy_db_name' => 'tenant_test_export',
        ], $attrs));
        $tenant->id = (string) Str::uuid();
        $tenant->save();

        return $tenant;
    });
}

it('fails when neither --code nor --id is provided', function () {
    $this->artisan('tenant:export')->assertFailed();
});

it('fails when tenant is not found by code', function () {
    $this->artisan('tenant:export', ['--code' => 'XXXXX'])->assertFailed();
});

it('fails when tenant is not found by id', function () {
    $this->artisan('tenant:export', ['--id' => '00000000-0000-0000-0000-000000000000'])->assertFailed();
});

it('exports successfully by code', function () {
    createTenantForExport();

    Process::fake([
        "'mysqldump'*" => Process::result(),
        "'mariadb-dump'*" => Process::result("CREATE USER `aula_EXP01`@`%` IDENTIFIED BY PASSWORD '*ABC';\nGRANT SELECT ON `tenant_test_export`.* TO `aula_EXP01`@`%`;"),
        "'tar'*" => Process::result(),
        "'rm'*" => Process::result(),
    ]);

    $this->artisan('tenant:export', [
        '--code' => 'EXP01',
        '--output' => '/tmp/test_export_code.tar.gz',
    ])->assertSuccessful();
});

it('exports successfully by id', function () {
    $tenant = createTenantForExport();

    Process::fake([
        "'mysqldump'*" => Process::result(),
        "'mariadb-dump'*" => Process::result("CREATE USER `aula_EXP01`@`%` IDENTIFIED BY PASSWORD '*ABC';\nGRANT SELECT ON `tenant_test_export`.* TO `aula_EXP01`@`%`;"),
        "'tar'*" => Process::result(),
        "'rm'*" => Process::result(),
    ]);

    $this->artisan('tenant:export', [
        '--id' => $tenant->id,
        '--output' => '/tmp/test_export_id.tar.gz',
    ])->assertSuccessful();
});

it('fails when mysqldump fails', function () {
    createTenantForExport();

    Process::fake([
        "'mysqldump'*" => Process::result('', 'Connection refused', 1),
        "'rm'*" => Process::result(),
    ]);

    $this->artisan('tenant:export', ['--code' => 'EXP01'])->assertFailed();
});

it('fails when mariadb-dump --system=users fails', function () {
    createTenantForExport();

    Process::fake([
        "'mysqldump'*" => Process::result(),
        "'mariadb-dump'*" => Process::result('', 'Access denied for --system=users', 1),
        "'rm'*" => Process::result(),
    ]);

    $this->artisan('tenant:export', ['--code' => 'EXP01'])->assertFailed();
});

it('fails when tar fails', function () {
    createTenantForExport();

    Process::fake([
        "'mysqldump'*" => Process::result(),
        "'mariadb-dump'*" => Process::result("CREATE USER `aula_EXP01`@`%` IDENTIFIED BY PASSWORD '*ABC';"),
        "'tar'*" => Process::result('', 'No space left on device', 1),
        "'rm'*" => Process::result(),
    ]);

    $this->artisan('tenant:export', ['--code' => 'EXP01'])->assertFailed();
});
