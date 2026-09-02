<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserGdprInfoController;

Route::apiResource('users', UserController::class)
    ->except(['update']);
Route::put('users/{user}', [UserController::class, 'update'])
    ->name('users.update');

Route::get('users/{user}/export', [UserController::class, 'export'])
    ->name('users.export');
