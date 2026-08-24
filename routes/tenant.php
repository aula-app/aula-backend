<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\SsoController;
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
    ->group(base_path('routes/tenant/api/v2/legacy-auth.php'));

// See also \App\Providers\PassportServiceProvider.php for
// some more routes that are added there in function boot()
// OAuth routes are defined there

// SSO routes
Route::name('sso.')
    ->middleware([
        'api',
        InitializeTenancyByRequestData::class,
    ])
    ->prefix('/api/v2/auth')
    ->group(function () {
        Route::get('/sso/initiate', [SsoController::class, 'initiate'])->name('initiate');

        Route::middleware('auth:api')->group(function () {
            Route::post('/sso/logout', [SsoController::class, 'logout'])->name('sso.logout');
            Route::post('/sso/link', [SsoController::class, 'link'])->name('sso.link');
        });
    });

// Passport-authenticated routes (existing)
Route::name('aula.')
    ->middleware([
        /* 'api', // 'api' is including parameter substitution */
        /* \Illuminate\Session\Middleware\StartSession::class, */
        /* \Illuminate\View\Middleware\ShareErrorsFromSession::class, */
        InitializeTenancyByRequestData::class,
        'auth:api', // our 'api' guard should be configured to use 'passport'
    ])
    ->prefix('/api/v2')
    ->group(base_path('routes/tenant/api/v2/aula.php'));
