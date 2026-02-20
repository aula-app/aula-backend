<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
*/

// Legacy JWT Authentication routes (public + protected)
Route::name('auth.')
    ->middleware([
        'api',
        InitializeTenancyByRequestData::class,
    ])
    ->prefix('/api/v2/legacy-auth')
    ->group(base_path('routes/tenant/api/v2/auth.php'));

// Passport-authenticated routes (existing)
Route::name('aula.')
    ->middleware([
        'api', // 'api' is including parameter substitution
        /* \Illuminate\Session\Middleware\StartSession::class, */
        /* \Illuminate\View\Middleware\ShareErrorsFromSession::class, */
        InitializeTenancyByRequestData::class,
        'auth:api', // our 'api' guard should be configured to use 'passport'
    ]) // , 'can:is-admin'
    ->prefix('/api/v2/')
    ->group(base_path('routes/tenant/api/v2/aula.php'));
