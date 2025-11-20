<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Support\Facades\Route;

Route::name('manager.')
    ->middleware(['api', 'universal', 'auth:api_manager'])
    ->prefix('/manager/')
    ->group(function () {
        PreventRequestsDuringMaintenance::except('/manager');

        // @TODO: to create tenants one needs to be manager admin, not just manager user
        //   ['can:is-admin']
        Route::post(
            '/tenants',
            function () {
                return response()->json(['TODO' => 'manager stuff'], 200);
                /* [App\Http\Controllers\TenantController::class, 'create'] */
            }
        );
    });
