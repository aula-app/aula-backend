<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Support\Facades\Route;

Route::name('public.')
    ->middleware(['api', 'universal'])
    ->prefix('/public/')
    ->group(function () {
        PreventRequestsDuringMaintenance::except('/public');

        // shallow health check
        Route::get('/up', function () {
            return response()->json(['up' => 'yes'], 200);
        });

        // deep health check, making sure all dependencies are also healthly
        Route::get('/health', function () {
            return response()->json(['TODO' => 'gather health information'], 200);
        });

        Route::get('/versions', function () {
            return response()->json([
                'aula-backend' => [
                    // injected by docker build argument DOCKER_TAG
                    'running' => env('APP_VERSION', 'unknown'),
                    'latest' => 'TODO',
                ],
                'aula-frontend' => [
                    // minimum FE version that is free of Backward Compatibility Breaking Changes
                    // FE should refuse to work if its version is lower
                    // @TODO: set this to the version of FE that implements the killswitch
                    //   ref: https://github.com/aula-app/aula-frontend/issues/761
                    'minimum' => 'v1.4.4',
                    // recommended FE version that supports all new features
                    'recommended' => 'v1.6.1',
                ],
            ], 200);
        });
    });
