<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LegacyUser;
use App\Models\Tenant;
use App\Services\IdTokenVerification\IdTokenVerificationException;
use App\Services\IdTokenVerifier;
use App\Services\LegacyJwtService;
use App\Services\SsoUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SsoController extends Controller
{
    private const int LINK_INTENT_TTL_MINUTES = 10;

    public function __construct(
        protected LegacyJwtService $jwtService,
        protected SsoUserService $ssoUserService,
        protected IdTokenVerifier $idTokenVerifier,
    ) {
    }

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

        $idToken = $socialiteUser->accessTokenResponseBody['id_token'] ?? null;

        if ($idToken === null) {
            Log::warning('SSO: rejecting login because Socialite returned no id_token', [
                'tenant' => $instanceCode,
                'sub'    => $socialiteUser->getId(),
            ]);

            return $this->frontendError('id_token_invalid');
        }

        try {
            $verifiedClaims = $this->idTokenVerifier->verify($idToken);
        } catch (IdTokenVerificationException $e) {
            Log::warning('SSO: rejecting login because id_token verification failed', [
                'tenant' => $instanceCode,
                'sub'    => $socialiteUser->getId(),
                'reason' => $e->reason,
            ]);

            return $this->frontendError('id_token_invalid');
        }

        if ($tenant->sso_require_email_verified && ($verifiedClaims['email_verified'] ?? null) !== true) {
            Log::warning('SSO: rejecting login because email_verified claim is not true', [
                'tenant' => $instanceCode,
                'sub'    => $socialiteUser->getId(),
            ]);

            return $this->frontendError('email_not_verified');
        }

        /** @var Tenant $callbackTenant */
        $callbackTenant = tenant();
        $sub            = $socialiteUser->getId();
        $email          = $socialiteUser->getEmail();

        $user = $this->ssoUserService->findBySub($sub);

        if ($user === null) {
            $emailMatch = $this->ssoUserService->findByEmail($email);

            if ($emailMatch === null) {
                $user = $this->ssoUserService->provisionUser($socialiteUser);
            } else {
                if (! $emailMatch->isActive()) {
                    return $this->frontendError('account_inactive');
                }

                if ($emailMatch->sso_sub !== null) {
                    Log::warning('SSO: email matches a user already bound to a different sso_sub', [
                        'tenant'          => $instanceCode,
                        'incoming_sub'    => $sub,
                        'existing_sub'    => $emailMatch->sso_sub,
                        'matched_user_id' => $emailMatch->id,
                    ]);

                    return $this->frontendError('sub_collision');
                }

                $linkToken = $this->storeLinkIntent($emailMatch, $socialiteUser, $callbackTenant);

                return $this->frontendError('account_link_required', ['sso_link' => $linkToken]);
            }
        } else {
            $strayEmailMatch = $this->ssoUserService->findByEmail($email);
            if ($strayEmailMatch && $strayEmailMatch->id !== $user->id) {
                Log::warning('SSO: email and sso_sub match different users — prioritising sso_sub match.', [
                    'email'        => $email,
                    'sub'          => $sub,
                    'sso_sub_user' => $user->id,
                    'email_user'   => $strayEmailMatch->id,
                ]);
            }
        }

        if (! $user->isActive()) {
            return $this->frontendError('account_inactive');
        }

        $user->sso_id_token      = $idToken;
        $user->sso_refresh_token = $socialiteUser->refreshToken;
        $user->sso_idp_id_token  = $this->fetchIdpIdToken($socialiteUser->token, $callbackTenant->sso_provider);
        $user->save();

        $token = $this->jwtService->generateToken($user);

        return $this->frontendRedirect($token);
    }

    /**
     * Link an SSO identity to an authenticated legacy user.
     *
     * Auth: bearer JWT (legacy.jwt middleware). The bearer user proves possession
     * of the legacy account; the link-intent token proves possession of the IdP
     * identity. Both must point to the same user_id.
     */
    public function link(Request $request): JsonResponse
    {
        $request->validate([
            'sso_link_token' => 'required|string',
        ]);

        /** @var LegacyUser $authUser */
        $authUser = $request->attributes->get('authenticated_user');
        $token    = $request->input('sso_link_token');

        $intent = Cache::get($this->linkIntentCacheKey($token));

        if (! is_array($intent)) {
            return response()->json(['success' => false, 'error' => 'link_intent_not_found'], 404);
        }

        if (($intent['user_id'] ?? null) !== $authUser->id) {
            Log::warning('SSO: link rejected — bearer JWT user does not match link intent', [
                'authenticated_user' => $authUser->id,
                'intent_user'        => $intent['user_id'] ?? null,
            ]);

            return response()->json(['success' => false, 'error' => 'user_mismatch'], 403);
        }

        $fresh = LegacyUser::find($authUser->id);

        if ($fresh === null) {
            return response()->json(['success' => false, 'error' => 'user_not_found'], 404);
        }

        if ($fresh->sso_sub !== null && $fresh->sso_sub !== $intent['sso_sub']) {
            return response()->json(['success' => false, 'error' => 'already_linked'], 409);
        }

        DB::transaction(function () use ($fresh, $intent) {
            $fresh->sso_sub           = $intent['sso_sub'];
            $fresh->sso_provider      = $intent['sso_provider'] ?? null;
            $fresh->sso_id_token      = $intent['sso_id_token'] ?? null;
            $fresh->sso_refresh_token = $intent['sso_refresh_token'] ?? null;
            $fresh->sso_idp_id_token  = $intent['sso_idp_id_token'] ?? null;
            $fresh->save();
        });

        Cache::forget($this->linkIntentCacheKey($token));

        return response()->json(['success' => true]);
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
     * Decode an OIDC id_token (JWT) payload without verifying the signature.
     * Returns null when the token is missing, malformed, or the payload is not valid JSON.
     *
     * @psalm-pure
     */
    protected function decodeIdTokenPayload(?string $idToken): ?array
    {
        if ($idToken === null || $idToken === '') {
            return null;
        }

        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            return null;
        }

        $padded  = str_pad($parts[1], strlen($parts[1]) + (4 - strlen($parts[1]) % 4) % 4, '=');
        $decoded = base64_decode(strtr($padded, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }

        $payload = json_decode($decoded, true);
        if (! is_array($payload)) {
            return null;
        }

        return $payload;
    }

    /**
     * Build an IdP logout URL by discovering the OIDC end_session_endpoint.
     * Works for any OIDC-compliant provider (Keycloak realms, iServ, VIDIS, etc.)
     */
    protected function buildIdpLogoutUrl(?string $idpIdToken, string $redirectUri): ?string
    {
        $payload = $this->decodeIdTokenPayload($idpIdToken);
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

    protected function frontendError(string $code, array $extra = []): RedirectResponse
    {
        $frontendUrl = rtrim(config('app.frontend_url', '/'), '/');
        $query       = http_build_query(['sso_error' => $code] + $extra);

        return redirect("{$frontendUrl}/login?{$query}");
    }

    /**
     * Persist an account-link intent in the cache and return the opaque token.
     * The intent carries everything the link endpoint needs to stamp the row
     * once the user has proven legacy-account possession via password.
     *
     * @param  \SocialiteProviders\Manager\OAuth2\User  $socialiteUser
     */
    protected function storeLinkIntent(LegacyUser $emailMatch, \Laravel\Socialite\Two\User $socialiteUser, Tenant $tenant): string
    {
        $token = bin2hex(random_bytes(16));

        Cache::put($this->linkIntentCacheKey($token), [
            'user_id'           => $emailMatch->id,
            'email'             => $emailMatch->email,
            'sso_sub'           => $socialiteUser->getId(),
            'sso_provider'      => $tenant->sso_provider,
            'sso_id_token'      => $socialiteUser->accessTokenResponseBody['id_token'] ?? null,
            'sso_refresh_token' => $socialiteUser->refreshToken,
            'sso_idp_id_token'  => $this->fetchIdpIdToken($socialiteUser->token, $tenant->sso_provider),
            'instance_code'     => $tenant->instance_code,
        ], now()->addMinutes(self::LINK_INTENT_TTL_MINUTES));

        return $token;
    }

    /**
     * @psalm-pure
     */
    protected function linkIntentCacheKey(string $token): string
    {
        return "sso_link:{$token}";
    }
}
