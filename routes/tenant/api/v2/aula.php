<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\UserGdprInfoController;

// TODO: GET /api/v2/users/ without header is 500ing with TenantCouldNotBeIdentifiedByRequestDataException, should probably 400/404?
Route::apiResource('users', UserController::class)
    ->except(['update']);
Route::put('users/{user}', [UserController::class, 'update'])
    ->name('users.update');

Route::get('user-gdpr-info/{user}', [UserGdprInfoController::class, 'show'])
    ->name('user-gdpr-info.show');

Route::apiResource('rooms', RoomController::class)
    ->except(['update']);
Route::put('rooms/{room}', [RoomController::class, 'update'])
    ->name('rooms.update');
