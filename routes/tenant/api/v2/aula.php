<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

// TODO: GET /api/v2/users/ without header is 500ing with TenantCouldNotBeIdentifiedByRequestDataException, should probably 400/404?
Route::apiResource('users', UserController::class)->except(['update']);
Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
