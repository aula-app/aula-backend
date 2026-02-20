<?php

use App\Http\Controllers\Auth\LegacyLoginController;
use App\Http\Controllers\Auth\RefreshTokenController;
use Illuminate\Support\Facades\Route;

/**
 * Authentication routes for legacy JWT compatibility.
 * These routes allow Laravel v2 to generate tokens valid on Legacy v1.
 *
 * Prefix: /api/v2/auth
 */

// Public routes (no authentication required)
Route::post('/login', [LegacyLoginController::class, 'login'])->name('login');

// Protected routes (require valid JWT, but allow refresh_token error)
Route::post('/refresh', [RefreshTokenController::class, 'refresh'])->name('refresh');

// Protected routes (require valid JWT)
Route::middleware('legacy.jwt')->group(function () {
    Route::get('/me', function () {
        $user = request()->attributes->get('authenticated_user');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'hash_id' => $user->hash_id,
                'username' => $user->username,
                'email' => $user->email,
                'displayname' => $user->displayname,
                'realname' => $user->realname,
                'userlevel' => $user->userlevel,
                'roles' => json_decode($user->roles ?? '[]'),
            ],
        ]);
    })->name('me');
});
