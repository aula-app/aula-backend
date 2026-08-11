<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\SsoController;
use App\Http\Controllers\Idp\ImportStatusController;
use App\Http\Controllers\Idp\MergeProposalController;
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

// SSO routes
Route::name('sso.')
    ->middleware([
        'api',
        InitializeTenancyByRequestData::class,
    ])
    ->prefix('/api/v2/auth')
    ->group(function () {
        // Completing a claim as somebody new: authenticated by the one-shot
        // token, since they have no aula account to authenticate with yet.
        Route::post('/sso/link/decline', [SsoController::class, 'declineAccountClaim'])
            ->name('sso.link_decline');

        Route::get('/sso/initiate', [SsoController::class, 'initiate'])->name('initiate');

        Route::middleware('legacy.jwt')->group(function () {
            Route::post('/sso/logout', [SsoController::class, 'logout'])->name('sso.logout');
            Route::post('/sso/link', [SsoController::class, 'link'])->name('sso.link');

            // Polled by the frontend after an SSO login so it can hold the user
            // on a setup screen until the school import has finished.
            // An admin connecting their own account is the first step of
            // migrating a school that already uses aula.
            Route::get('/idp/connect', [SsoController::class, 'connectIdentity'])
                ->name('idp.connect');

            // The review an admin works through before a school's directory
            // is imported over its existing accounts.
            Route::post('/idp/merge-proposal', [MergeProposalController::class, 'build'])
                ->name('idp.proposal.build');
            Route::get('/idp/merge-proposal', [MergeProposalController::class, 'index'])
                ->name('idp.proposal.index');
            Route::post('/idp/merge-proposal/decisions', [MergeProposalController::class, 'decide'])
                ->name('idp.proposal.decide');
            Route::post('/idp/merge-proposal/apply', [MergeProposalController::class, 'apply'])
                ->name('idp.proposal.apply');
            Route::get('/idp/migration-progress', [MergeProposalController::class, 'progress'])
                ->name('idp.migration_progress');

            Route::get('/idp/import-status', [ImportStatusController::class, 'show'])
                ->name('idp.import_status');
        });
    });

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
