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

// OIDC third-party initiated login — OpenID Connect Core 1.0 §4
// (https://openid.net/specs/openid-connect-core-1_0.html#ThirdPartyInitiatedLogin).
// Eduplaces' marketplace launcher hits this when a user opens the aula app from
// inside Eduplaces. The callback resolves the aula tenant by mapping the upstream
// id_token's `school` claim to `tenants.eduplaces_school_id`.
Route::get('/api/v2/auth/sso/idp-initiated', [SsoController::class, 'idpInitiated'])
    ->middleware(['api'])
    ->name('sso.idp_initiated');
