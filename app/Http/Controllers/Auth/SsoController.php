<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LegacyUser;
use App\Models\Tenant;
use App\Services\LegacyJwtService;
use App\Services\SsoUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SsoController extends Controller
{
    public function __construct(
        protected LegacyJwtService $jwtService,
        protected SsoUserService $ssoUserService,
    ) {}

    // =========================================================
    // Public endpoints
    // =========================================================

    /**
     * Initiate SSO login flow.
     *
     * Returns a JSON response with the Keycloak redirect URL.
     * The frontend navigates to it; the instance_code is carried in a signed
     * state parameter so the callback can identify the tenant without the header.
     */
    public function initiate(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant  = tenant();
        $idpHint = $tenant->sso_provider ?? null;

        $state = $this->buildSignedState($tenant->instance_code);

        $params = ['state' => $state];
        if ($idpHint) {
            $params['kc_idp_hint'] = $idpHint;
        }
        if ($request->boolean('force_login')) {
            $params['prompt'] = 'login';
        }

        /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
        $driver = Socialite::driver('keycloak');

        $url = $driver
            ->stateless()
            ->with($params)
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    /**
     * Handle the SSO callback from Keycloak.
     *
     * This is a universal route — no tenant middleware runs here.
     * We verify the signed state to prevent CSRF and to identify the tenant.
     */
    public function callback(Request $request): RedirectResponse
    {
        $state = $request->query('state', '');

        $instanceCode = $this->verifySignedState($state);

        if ($instanceCode === null) {
            return $this->frontendError('invalid_state');
        }

        $tenant = Tenant::where('instance_code', $instanceCode)->first();

        if ($tenant === null) {
            return $this->frontendError('unknown_tenant');
        }

        tenancy()->initialize($tenant);

        /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
        $driver = Socialite::driver('keycloak');
        /** @var \SocialiteProviders\Manager\OAuth2\User $socialiteUser */
        $socialiteUser = $driver->stateless()->user();

        $user = $this->ssoUserService->resolveUser($socialiteUser->getEmail(), $socialiteUser->getId());

        if ($user === null) {
            $user = $this->ssoUserService->provisionUser($socialiteUser);
        }

        if (! $user->isActive()) {
            return $this->frontendError('account_inactive');
        }

        /** @var Tenant $callbackTenant */
        $callbackTenant          = tenant();
        $user->sso_id_token      = $socialiteUser->accessTokenResponseBody['id_token'] ?? null;
        $user->sso_refresh_token = $socialiteUser->refreshToken ?? null;
        $user->sso_idp_id_token  = $this->fetchIdpIdToken($socialiteUser->token, $callbackTenant->sso_provider);
        $user->save();

        $token = $this->jwtService->generateToken($user);

        return $this->frontendRedirect($token);
    }

    /**
     * SSO logout endpoint.
     *
     * When the tenant has sso_force_logout enabled, returns a Keycloak
     * logout URL that the frontend must navigate to in order to end the
     * user's Keycloak session (RP-initiated logout).
     *
     * When disabled, returns null so the frontend can proceed with a
     * normal local logout.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        if (! $tenant->sso_force_logout) {
            return response()->json(['logout_url' => null]);
        }

        /** @var \App\Models\LegacyUser $user */
        $user = $request->attributes->get('authenticated_user');

        $this->revokeKeycloakSession($user?->sso_refresh_token);

        $frontendUrl = rtrim(config('app.frontend_url', '/'), '/');

        $aulaLogoutUrl = $this->buildKeycloakLogoutUrl($user?->sso_id_token, $frontendUrl);

        $logoutUrl = $aulaLogoutUrl;
        if ($user?->sso_idp_id_token && $aulaLogoutUrl) {
            $idpLogoutUrl = $this->buildIdpLogoutUrl($user->sso_idp_id_token, $aulaLogoutUrl);
            if ($idpLogoutUrl) {
                $logoutUrl = $idpLogoutUrl;
            }
        }

        return response()->json(['logout_url' => $logoutUrl]);
    }

    // =========================================================
    // Protected helpers
    // =========================================================

    /**
     * Build a signed state payload containing the instance_code.
     * Format: base64(json) . '.' . hmac_signature
     */
    protected function buildSignedState(string $instanceCode): string
    {
        $payload = base64_encode(json_encode([
            'instance_code' => $instanceCode,
            'nonce'         => Str::random(16),
        ]));

        $signature = hash_hmac('sha256', $payload, $this->stateSecret());

        return $payload . '.' . $signature;
    }

    /**
     * Verify the signed state and return the instance_code, or null on failure.
     */
    protected function verifySignedState(string $state): ?string
    {
        $parts = explode('.', $state, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$payload, $signature] = $parts;

        $expected = hash_hmac('sha256', $payload, $this->stateSecret());

        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $data = json_decode(base64_decode($payload), true);

        return $data['instance_code'] ?? null;
    }

    protected function stateSecret(): string
    {
        return config('app.key');
    }

    /**
     * Build the Keycloak RP-initiated logout URL using the configured realm.
     */
    protected function buildKeycloakLogoutUrl(?string $idToken, string $redirectUri): ?string
    {
        if (! $idToken) {
            return null;
        }

        $base  = rtrim(config('services.keycloak.base_url'), '/');
        $realm = config('services.keycloak.realms', 'master');

        return "{$base}/realms/{$realm}/protocol/openid-connect/logout?" . http_build_query([
            'id_token_hint'            => $idToken,
            'post_logout_redirect_uri' => $redirectUri,
        ]);
    }

    protected function revokeKeycloakSession(?string $refreshToken): void
    {
        if (! $refreshToken) {
            return;
        }

        $base  = rtrim(config('services.keycloak.base_url'), '/');
        $realm = config('services.keycloak.realms', 'master');

        Http::asForm()->post("{$base}/realms/{$realm}/protocol/openid-connect/logout", [
            'client_id'     => config('services.keycloak.client_id'),
            'client_secret' => config('services.keycloak.client_secret'),
            'refresh_token' => $refreshToken,
        ]);
    }

    /**
     * Fetch the upstream IdP's id_token via Keycloak's broker token API.
     */
    protected function fetchIdpIdToken(?string $accessToken, ?string $provider): ?string
    {
        if (! $accessToken || ! $provider) {
            return null;
        }

        $base  = rtrim(config('services.keycloak.base_url'), '/');
        $realm = config('services.keycloak.realms', 'master');

        $response = Http::withToken($accessToken)
            ->get("{$base}/realms/{$realm}/broker/{$provider}/token");

        if (! $response->ok()) {
            return null;
        }

        return $response->json('id_token');
    }

    /**
     * Build an IdP logout URL by discovering the OIDC end_session_endpoint.
     * Works for any OIDC-compliant provider (Keycloak realms, iServ, VIDIS, etc.)
     */
    protected function buildIdpLogoutUrl(?string $idpIdToken, string $redirectUri): ?string
    {
        if (! $idpIdToken) {
            return null;
        }

        $parts = explode('.', $idpIdToken);
        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode(base64_decode(str_pad($parts[1], strlen($parts[1]) + (4 - strlen($parts[1]) % 4) % 4, '=')), true);
        $issuer  = rtrim($payload['iss'] ?? '', '/');

        if (! $issuer) {
            return null;
        }

        $discovery = Http::get("{$issuer}/.well-known/openid-configuration");
        if (! $discovery->ok()) {
            return null;
        }

        $endSessionEndpoint = $discovery->json('end_session_endpoint');
        if (! $endSessionEndpoint) {
            return null;
        }

        return $endSessionEndpoint . '?' . http_build_query([
            'post_logout_redirect_uri' => $redirectUri,
            'id_token_hint'            => $idpIdToken,
        ]);
    }

    protected function frontendRedirect(string $token): RedirectResponse
    {
        $frontendUrl = rtrim(config('app.frontend_url', '/'), '/');

        return redirect("{$frontendUrl}/oauth-login/{$token}");
    }

    protected function frontendError(string $code): RedirectResponse
    {
        $frontendUrl = rtrim(config('app.frontend_url', '/'), '/');

        return redirect("{$frontendUrl}/login?sso_error={$code}");
    }
}
