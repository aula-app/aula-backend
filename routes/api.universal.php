<?php

use App\Http\Controllers\Auth\SsoController;
use App\Http\Controllers\Idp\WebhookController;
use App\Http\Middleware\VerifyIdpWebhookSignature;
use Illuminate\Support\Facades\Route;

Route::group(attributes: [], routes: [
    base_path('routes/api/public.php'),
]);

// SSO callback: a universal route, so no tenant header is needed. The tenant
// comes from the signed state parameter.
Route::get('/api/v2/auth/sso/callback', [SsoController::class, 'callback'])
    ->middleware(['api'])
    ->name('sso.callback');

// OIDC third-party initiated login, OpenID Connect Core 1.0 §4
// (https://openid.net/specs/openid-connect-core-1_0.html#ThirdPartyInitiatedLogin).
// The Eduplaces marketplace launcher calls this when aula is opened from inside
// Eduplaces. callback() then maps the upstream id_token's `school` claim to
// `tenants.idp_school_id`.
Route::get('/api/v2/auth/sso/idp-initiated', [SsoController::class, 'idpInitiated'])
    ->middleware(['api'])
    ->name('sso.idp_initiated');

// Identity-provider directory webhooks. The provider segment selects the
// adapter that verifies the signature and normalises the payload.
//
// Universal route: user and group events need not carry a school identifier, so
// the tenant is unknown at routing time and TenantResolver resolves it on the
// queue instead.
Route::post('/api/v2/webhooks/idp/{provider}', [WebhookController::class, 'handle'])
    ->middleware(['api', VerifyIdpWebhookSignature::class])
    ->name('webhooks.idp');
