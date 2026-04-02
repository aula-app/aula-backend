<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Services\TenantsService;
use App\UseCases\CreateTenantUseCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

// Helpers

function fakeTenant(array $attrs = []): Tenant
{
    return new Tenant(array_merge([
        'id'                  => 99,
        'name'                => 'Test School',
        'instance_code'       => 'ABC12',
        'admin1_username'     => 'admin1',
        'admin2_username'     => 'admin2',
        'admin1_init_pass_url' => 'https://example.com/password/s1?code=ABC12',
        'admin2_init_pass_url' => 'https://example.com/password/s2?code=ABC12',
    ], $attrs));
}

function mockUseCase(Tenant $tenant): CreateTenantUseCase
{
    $mock = Mockery::mock(CreateTenantUseCase::class);
    $mock->shouldReceive('execute')->once()->andReturn($tenant);

    app()->instance(CreateTenantUseCase::class, $mock);

    return $mock;
}

// Non-interactive mode

it('creates tenant non-interactively when all required options are supplied', function () {
    mockUseCase(fakeTenant());

    $this->artisan('tenant:create', [
        '--name'            => 'Test School',
        '--code'            => 'ABC12',
        '--admin-username'  => 'admin1',
        '--admin-email'     => 'admin1@test.com',
        '--admin2-username' => 'admin2',
        '--admin2-email'    => 'admin2@test.com',
    ])->assertSuccessful();
});

it('passes adminPassword to the use case', function () {
    $mock = Mockery::mock(CreateTenantUseCase::class);
    $mock->shouldReceive('execute')
        ->once()
        ->withArgs(fn (
            string $name, string $code,
            string $a1u, string $a1n, string $a1e,
            string $a2u, string $a2n, string $a2e,
            ?string $pw
        ) => $pw === 'secret123')
        ->andReturn(fakeTenant(['admin1_init_pass_url' => null, 'admin2_init_pass_url' => null]));

    app()->instance(CreateTenantUseCase::class, $mock);

    $this->artisan('tenant:create', [
        '--name'            => 'Test School',
        '--code'            => 'ABC12',
        '--admin-username'  => 'admin1',
        '--admin-email'     => 'admin1@test.com',
        '--admin2-username' => 'admin2',
        '--admin2-email'    => 'admin2@test.com',
        '--admin-password'  => 'secret123',
    ])->assertSuccessful();
});

it('auto-generates instance code when --code is omitted in non-interactive mode', function () {
    $tenantsService = Mockery::mock(TenantsService::class);
    $tenantsService->shouldReceive('generateUniqueInstanceCode')->once()->andReturn('XYZ99');
    app()->instance(TenantsService::class, $tenantsService);

    mockUseCase(fakeTenant(['instance_code' => 'XYZ99']));

    $this->artisan('tenant:create', [
        '--name'            => 'Test School',
        '--admin-username'  => 'admin1',
        '--admin-email'     => 'admin1@test.com',
        '--admin2-username' => 'admin2',
        '--admin2-email'    => 'admin2@test.com',
    ])->assertSuccessful();
});

// Idempotency

it('skips creation and exits successfully when tenant with same code already exists', function () {
    Tenant::create([
        'name'            => 'Existing School',
        'instance_code'   => 'EXIST',
        'jwt_key'         => 'key',
        'api_base_url'    => 'https://example.com',
        'admin1_username' => 'admin1',
        'admin1_email'    => 'admin1@test.com',
        'admin1_name'     => 'Admin One',
        'admin2_username' => 'admin2',
        'admin2_email'    => 'admin2@test.com',
        'admin2_name'     => 'Admin Two',
    ]);

    // Use case must NOT be called
    $mock = Mockery::mock(CreateTenantUseCase::class);
    $mock->shouldNotReceive('execute');
    app()->instance(CreateTenantUseCase::class, $mock);

    $this->artisan('tenant:create', [
        '--name'            => 'Any Name',
        '--code'            => 'EXIST',
        '--admin-username'  => 'admin1',
        '--admin-email'     => 'admin1@test.com',
        '--admin2-username' => 'admin2',
        '--admin2-email'    => 'admin2@test.com',
    ])->assertSuccessful();
});

// Validation failures

it('fails when tenant name already exists in non-interactive mode', function () {
    Tenant::create([
        'name'            => 'Duplicate School',
        'instance_code'   => 'DUP01',
        'jwt_key'         => 'key',
        'api_base_url'    => 'https://example.com',
        'admin1_username' => 'admin1',
        'admin1_email'    => 'admin1@test.com',
        'admin1_name'     => 'Admin One',
        'admin2_username' => 'admin2',
        'admin2_email'    => 'admin2@test.com',
        'admin2_name'     => 'Admin Two',
    ]);

    $mock = Mockery::mock(CreateTenantUseCase::class);
    $mock->shouldNotReceive('execute');
    app()->instance(CreateTenantUseCase::class, $mock);

    $this->artisan('tenant:create', [
        '--name'            => 'Duplicate School',
        '--code'            => 'NEW01',
        '--admin-username'  => 'admin1',
        '--admin-email'     => 'admin1@test.com',
        '--admin2-username' => 'admin2',
        '--admin2-email'    => 'admin2@test.com',
    ])->assertFailed();
});

it('fails when both admin usernames are the same', function () {
    $mock = Mockery::mock(CreateTenantUseCase::class);
    $mock->shouldNotReceive('execute');
    app()->instance(CreateTenantUseCase::class, $mock);

    $this->artisan('tenant:create', [
        '--name'            => 'Test School',
        '--code'            => 'ABC12',
        '--admin-username'  => 'admin',
        '--admin-email'     => 'admin1@test.com',
        '--admin2-username' => 'admin',
        '--admin2-email'    => 'admin2@test.com',
    ])->assertFailed();
});

it('fails when instance code format is invalid', function () {
    $mock = Mockery::mock(CreateTenantUseCase::class);
    $mock->shouldNotReceive('execute');
    app()->instance(CreateTenantUseCase::class, $mock);

    $this->artisan('tenant:create', [
        '--name'            => 'Test School',
        '--code'            => 'BAD!',
        '--admin-username'  => 'admin1',
        '--admin-email'     => 'admin1@test.com',
        '--admin2-username' => 'admin2',
        '--admin2-email'    => 'admin2@test.com',
    ])->assertFailed();
});

it('accepts SINGLE as a valid instance code', function () {
    mockUseCase(fakeTenant(['instance_code' => 'SINGLE']));

    $this->artisan('tenant:create', [
        '--name'            => 'Test School',
        '--code'            => 'SINGLE',
        '--admin-username'  => 'admin1',
        '--admin-email'     => 'admin1@test.com',
        '--admin2-username' => 'admin2',
        '--admin2-email'    => 'admin2@test.com',
    ])->assertSuccessful();
});
