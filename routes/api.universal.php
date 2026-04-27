<?php

use App\Http\Controllers\Auth\SsoController;
use Illuminate\Support\Facades\Route;

Route::group(attributes: [], routes: [
    base_path('routes/api/public.php'),
]);

// SSO callback — universal route, no tenant header needed.
// Tenant is identified from the signed state parameter.
Route::get('/api/v2/auth/sso/callback', [SsoController::class, 'callback'])
    ->middleware(['api'])
    ->name('sso.callback');
