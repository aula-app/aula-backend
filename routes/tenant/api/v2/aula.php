<?php

use App\Http\Controllers\RoomMembershipController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoomController;

Route::apiResource('users', UserController::class)
    ->except(['update']);
/** n.b. {user} and {room} have patterns
 * @see App\Providers\AppServiceProvider::boot
 */
Route::put('users/{user}', [UserController::class, 'update'])
    ->name('users.update');

Route::apiResource('rooms', RoomController::class)
    ->except(['update']);
Route::put('rooms/{room}', [RoomController::class, 'update'])
    ->name('rooms.update');

Route::patch('rooms/{room}/membership', [RoomController::class, 'patchMembership'])
    ->name('rooms.patch.membership');
// TODO: check if response with roomLevel embedded makes sense
Route::get('rooms/{room}/membership', [RoomController::class, 'indexMembership'])
    ->name('rooms.index.membership');

// TODO proably remove; left here for comparison of the PATCH add/remove method above
//   then also rename route above
Route::get('rooms/{room}/members', [RoomMembershipController::class, 'index']);
Route::get('rooms/{room}/members/{user}', [RoomMembershipController::class, 'show']);
Route::put('rooms/{room}/members/{user}', [RoomMembershipController::class, 'store']);
Route::delete('rooms/{room}/members/{user}', [RoomMembershipController::class, 'destroy']);
