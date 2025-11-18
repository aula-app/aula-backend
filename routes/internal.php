<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Support\Facades\Route;

Route::name('internal.')
    ->middleware(['api', 'universal'])
    ->prefix('/internal/')
    ->group(function () {
        PreventRequestsDuringMaintenance::except('/internal');

        Route::get('/up', function () {
            return response()->json(['up' => 'yes'], 200);
        });
        Route::get('/health', function () {
            return response()->json(['TODO' => 'gather health information'], 200);
        });
    });
