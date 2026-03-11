<?php

declare(strict_types=1);

use App\Models\Manager\AulaManagerUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;

// DatabaseTransactions wraps each test in a rolled-back transaction,
// so no data persists and no tenant databases are touched.
uses(DatabaseTransactions::class);

// ---------------------------------------------------------------------------
// Unauthenticated access
// ---------------------------------------------------------------------------

it('shows the manager login page', function () {
    $this->get('/manager/login')->assertOk();
});

it('redirects unauthenticated users away from the dashboard', function () {
    $this->get('/manager')
        ->assertRedirectContains('/manager/login');
});

it('redirects unauthenticated users away from the tenants list', function () {
    $this->get('/manager/tenants')
        ->assertRedirectContains('/manager/login');
});

it('redirects unauthenticated users away from the statistics page', function () {
    $this->get('/manager/statistics')
        ->assertRedirectContains('/manager/login');
});

it('redirects unauthenticated users away from the create tenant page', function () {
    $this->get('/manager/tenants/create')
        ->assertRedirectContains('/manager/login');
});

// ---------------------------------------------------------------------------
// Authenticated access
// ---------------------------------------------------------------------------

function managerUser(): AulaManagerUser
{
    return AulaManagerUser::create([
        'name' => 'Panel Test Admin',
        'email' => 'panel-test-'.uniqid().'@test.local',
        'password' => bcrypt('password'),
    ]);
}

it('allows an authenticated manager user to access the dashboard', function () {
    $this->actingAs(managerUser(), 'web')
        ->get('/manager')
        ->assertOk();
});

it('allows an authenticated manager user to access the tenants list', function () {
    $this->actingAs(managerUser(), 'web')
        ->get('/manager/tenants')
        ->assertOk();
});

it('allows an authenticated manager user to access the create tenant page', function () {
    $this->actingAs(managerUser(), 'web')
        ->get('/manager/tenants/create')
        ->assertOk();
});

it('allows an authenticated manager user to access the statistics page', function () {
    $this->actingAs(managerUser(), 'web')
        ->get('/manager/statistics')
        ->assertOk();
});
