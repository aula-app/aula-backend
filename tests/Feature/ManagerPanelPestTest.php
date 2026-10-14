<?php

declare(strict_types=1);

use App\Models\Manager\AulaManagerUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

// Created once for the whole file; bcrypt runs a single time.
$manager = null;

beforeEach(function () use (&$manager) {
    $manager ??= AulaManagerUser::create([
        'name'     => 'Panel Test Admin',
        'email'    => 'panel-test@test.local',
        'password' => bcrypt('password'),
    ]);
});

it('shows the manager login page', function () {
    $this->get('/manager/login')->assertOk();
});

it('redirects unauthenticated users away from protected routes', function () {
    $this->get('/manager')->assertRedirectContains('/manager/login');
    $this->get('/manager/tenants')->assertRedirectContains('/manager/login');
    $this->get('/manager/tenants/create')->assertRedirectContains('/manager/login');
    $this->get('/manager/statistics')->assertRedirectContains('/manager/login');
});

it('allows an authenticated manager user to access all protected routes', function () use (&$manager) {
    $this->actingAs($manager, 'web');

    $this->get('/manager')->assertOk();
    $this->get('/manager/tenants')->assertOk();
    $this->get('/manager/tenants/create')->assertOk();
    $this->get('/manager/statistics')->assertOk();
});
