<?php

use Illuminate\Support\Facades\Route;

/**
 * These routes are prefixed with `/api/v2/` and use middlewares: ['api', InitializeTenancyByRequestData, 'auth:api'].
 * See /routes/tenant.php.
 */
Route::get('/users/arm', function () {
    return response()->json(['users' => [['name' => 'The Arm']]]);
});

Route::get('/users/', function () {
    /* dd(DB::connection()->getDatabaseName()); */
    /* dd(\App\Models\User::where('name', '=', 'foo')->email); */
    return response()->json([
        /* 'tenant' => tenant('id'), */
        /* 'me' => Auth::user(), */
        'users' => \App\Models\User::all()->toArray(),
    ], 200);

    return 'This is your multi-tenant application. The id of the current tenant is '.tenant('id')."\n";
});
