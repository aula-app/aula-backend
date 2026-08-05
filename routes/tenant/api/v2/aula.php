<?php

use App\Http\Controllers\IdeaController;
use App\Http\Controllers\RoomUserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\UserGdprInfoController;

// TODO: GET /api/v2/users/ without header is 500ing with TenantCouldNotBeIdentifiedByRequestDataException, should probably 400/404?
Route::apiResource('users', UserController::class)
    ->except(['update']);
/** n.b. {user} and {room} have patterns
 * @see App\Providers\AppServiceProvider::boot
 */
Route::put('users/{user}', [UserController::class, 'update'])
    ->name('users.update');

Route::get('user-gdpr-info/{user}', [UserGdprInfoController::class, 'show'])
    ->name('user-gdpr-info.show');

Route::apiResource('rooms', RoomController::class)
    ->except(['update']);
Route::put('rooms/{room}', [RoomController::class, 'update'])
    ->name('rooms.update');

Route::get('rooms/{room}/users', [RoomUserController::class, 'index']);
Route::get('rooms/{room}/users/{user}', [RoomUserController::class, 'show']);
Route::put('rooms/{room}/users/{user}', [RoomUserController::class, 'store']);
Route::delete('rooms/{room}/users/{user}', [RoomUserController::class, 'destroy']);

Route::get('ideas', [IdeaController::class, 'index']);
Route::get('ideas/mine/{room?}/{phase?}', [IdeaController::class, 'indexMine']);
Route::get('ideas/inmyrooms/{phase?}', [IdeaController::class, 'indexInMyRooms']);
