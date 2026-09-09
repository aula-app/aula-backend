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
        // Authenticated by the one-shot sso_link_token alone: a caller with no
        // aula account has no other credential to offer.
        Route::post('/sso/link/decline', [SsoController::class, 'declineAccountClaim'])
            ->name('sso.link_decline');

        // Read before any login, so the login page can leave the SSO button out
        // of a tenant with sso_enabled false.
        Route::get('/sso/status', [SsoController::class, 'status'])->name('status');

        Route::get('/sso/initiate', [SsoController::class, 'initiate'])->name('initiate');

        Route::middleware('legacy.jwt')->group(function () {
            Route::post('/sso/logout', [SsoController::class, 'logout'])->name('sso.logout');
            Route::post('/sso/link', [SsoController::class, 'link'])->name('sso.link');

            // First step of migrating a tenant that already uses aula: the
            // admin connects an own account and sets tenants.idp_school_id.
            Route::get('/idp/connect', [SsoController::class, 'connectIdentity'])
                ->name('idp.connect');

            // The admin review of idp_merge_candidates, before the directory is
            // imported over a school's existing accounts.
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

            // Polled after an SSO login to hold the user on a setup screen
            // until the school import has finished.
            Route::get('/idp/import-status', [ImportStatusController::class, 'show'])
                ->name('idp.import_status');
        });
    });

// Passport-authenticated routes (existing)
Route::name('aula.')
    ->middleware([
        /* 'api', // 'api' is including parameter substitution */
        /* \Illuminate\Session\Middleware\StartSession::class, */
        /* \Illuminate\View\Middleware\ShareErrorsFromSession::class, */
        InitializeTenancyByRequestData::class,
        /* 'auth:api', // our 'api' guard should be configured to use 'passport' */
        // TODO: replace with passport?
        'legacy.jwt',
        'auth:apiv2',
    ])
    ->prefix('/api/v2')
    ->group(base_path('routes/tenant/api/v2/aula.php'));
