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

// OIDC third-party initiated login (RFC §4). IdPs like Eduplaces hit this
// when a user launches the app from their marketplace. No tenant context
// yet — we resolve it on the callback by mapping `sub` → school → tenant.
Route::get('/api/v2/auth/sso/idp-initiated', [SsoController::class, 'idpInitiated'])
    ->middleware(['api'])
    ->name('sso.idp_initiated');
