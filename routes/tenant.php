<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
*/
Route::name('aula.')
    ->prefix('/api/v2/')
    ->middleware([
        'api', // 'api' is including parameter substitution
        /* \Illuminate\Session\Middleware\StartSession::class, */
        /* \Illuminate\View\Middleware\ShareErrorsFromSession::class, */
        Middleware\InitializeTenancyByRequestData::class,
        /* Middleware\ScopeSessions::class, */
        'auth:api', // our 'api' guard should be configured to use 'passport'
    ])->group(function () {
        Route::get('/', function () {
            /* dd(DB::connection()->getDatabaseName()); */
            /* dd(\App\Models\User::where('name', '=', 'foo')->email); */
            return response()->json([
                /* 'tenant' => tenant('id'), */
                /* 'me' => Auth::user(), */
                'users' => \App\Models\User::all()->toArray(),
            ], 200);

            return 'This is your multi-tenant application. The id of the current tenant is '.tenant('id')."\n";
        });
    });
