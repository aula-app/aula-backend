<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['universal'])->group(function () {
    Route::get('/web/users', function () {
        /* dd(\App\Models\User::first()->email); */
        return response()->json(\App\Models\User::all()->toArray(), 200);
    });
});
