<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

// ===================
// Passport-authenticated routes with prefix('/api/v2')
// ===================

Route::apiResource('users', UserController::class)
    ->except(['update']);
Route::put('users/{user}', [UserController::class, 'update'])
    ->name('users.update');
