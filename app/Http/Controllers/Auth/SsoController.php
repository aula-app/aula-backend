<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserLevel;
use App\Http\Controllers\Controller;
use App\Jobs\ImportSchoolForTenant;
use App\Models\LegacyUser;
use App\Models\Tenant;
use App\Services\Idp\DirectoryException;
use App\Services\Idp\IdpProviders;
use App\Services\Idp\SchoolImport;
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
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as LaravelSocialiteUser;
use SocialiteProviders\Manager\OAuth2\User as SocialiteOAuth2User;

class SsoController extends Controller
{
    private const int LINK_INTENT_TTL_MINUTES = 10;

    /**
     * Sentinel value in the signed state that marks the callback as an
     * IdP-initiated launch from Eduplaces. The callback resolves the tenant
     * from the upstream id_token's `school` claim instead of from an
     * instance_code carried in the state.
     */
    private const string IDP_INITIATED_EDUPLACES = '__IDP_INITIATED_EDUPLACES__';

    /**
     * Value of `?client=` on the initiate endpoints, and of `client` in the
     * signed state, that marks a flow as having started inside a native app.
     */
    private const string CLIENT_APP = 'app';

    /**
     * Whether the flow being handled started in a native app, decided from the
     * signed state at the top of the callback.
     *
     * Held on the instance because every exit from the callback runs through
     * frontendRedirect()/frontendError(), which are called from a dozen places
     * that have no business threading a client flag through their signatures.
     */
    private bool $nativeClient = false;

    /**
     * Upstream IdP id_tokens fetched during this request, keyed by provider
     * alias. Tenant resolution, session issuing and the provider user-id
     * stamping all want the same token, and each read costs a round-trip to
     * Keycloak's broker endpoint.
     *
     * @var array<string, string|null>
     */
    private array $idpIdTokens = [];

    public function __construct(
        protected LegacyJwtService $jwtService,
        protected SsoUserService $ssoUserService,
        protected IdTokenVerifier $idTokenVerifier,
        protected SchoolImport $schoolImport,
        protected IdpProviders $idpProviders,
    ) {}

    // =========================================================
    // Public endpoints
    // =========================================================

    /**
     * Whether this school offers SSO at all.
     *
     * Unauthenticated because the login page is what asks: offering a button
     * that initiate() will only refuse is worse than not offering one. Says
     * nothing a failed login attempt would not already reveal.
     */
    public function status(): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        return response()->json([
            'enabled' => (bool) $tenant->sso_enabled,
            'provider' => $tenant->sso_provider,
        ]);
    }

    /**
     * Initiate SSO login flow.
     *
     * Returns a JSON response with the Keycloak redirect URL.
     * The frontend navigates to it; the instance_code is carried in a signed
     * state parameter so the callback can identify the tenant without the header.
     *
     * Native clients pass `?client=app` so the callback knows to end on the
     * app's deep-link scheme rather than on the website.
     */
    public function initiate(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        if (! $tenant->sso_enabled) {
            return response()->json(['error' => 'sso_disabled'], 403);
        }

        $idpHint = $tenant->sso_provider ?? null;

        $state = $this->buildSignedState(
            $tenant->instance_code,
            nativeApp: $this->wantsNativeClient($request),
        );

        $params = ['state' => $state];
        if ($idpHint) {
            $params['kc_idp_hint'] = $idpHint;
        }
        if ($request->boolean('force_login')) {
            $params['prompt'] = 'login';
        }
        if (($loginHint = (string) $request->query('login_hint', '')) !== '') {
            // Preserves the user pre-selection blob when the frontend re-enters
            // /sso/initiate after a third-party initiated login (e.g. Eduplaces
            // marketplace launcher → /sso/idp-initiated → frontend → here).
            $params['login_hint'] = $loginHint;
        }

        /** @var AbstractProvider $driver */
        $driver = Socialite::driver('keycloak');

        $url = $driver
            ->stateless()
            ->with($params)
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    /**
     * A migrating school becomes `connected` once its admin has linked and the
     * school id is known — the point from which an import can be prepared.
     */
    protected function advanceMigrationAfterConnect(LegacyUser $user): void
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        if ($tenant === null
            || $tenant->idp_migration_status !== Tenant::IDP_MIGRATION_FLAGGED
            || $tenant->idp_school_id === null
            || ($user->userlevel?->value ?? 0) < UserLevel::Admin->value) {
            return;
        }

        $tenant->update(['idp_migration_status' => Tenant::IDP_MIGRATION_CONNECTED]);

        Log::info('SSO: school connected to its identity provider', [
            'tenant' => $tenant->instance_code,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Start connecting an admin's own aula account to the identity provider.
     *
     * The first step of migrating a school that already uses aula: the admin
     * proves who they are on the provider, which both links their account and
     * establishes which school this tenant is. Everything after it — the
     * import, the review — depends on that school id being known, and knowing
     * it by proof rather than by someone picking from a list.
     *
     * Possession of the aula account is already proved: this route is behind
     * the bearer token. The callback still goes through the ordinary link
     * intent, which re-checks it.
     */
    public function connectIdentity(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        /** @var LegacyUser $user */
        $user = $request->attributes->get('authenticated_user');

        if (($user->userlevel?->value ?? 0) < UserLevel::Admin->value) {
            return response()->json(['error' => 'admin_required'], 403);
        }

        if (! $tenant->usesIdpDirectory()) {
            return response()->json(['error' => 'no_idp_configured'], 422);
        }

        $params = [
            'state' => $this->buildSignedState(
                $tenant->instance_code,
                (int) $user->id,
                $this->wantsNativeClient($request),
            ),
            // Force a fresh authentication: the point is to capture *this*
            // person's provider identity, not to reuse a session that might
            // belong to whoever used the browser last.
            'prompt' => 'login',
        ];

        if ($tenant->sso_provider) {
            $params['kc_idp_hint'] = $tenant->sso_provider;
        }

        /** @var AbstractProvider $driver */
        $driver = Socialite::driver('keycloak');

        return response()->json([
            'url' => $driver->stateless()->with($params)->redirect()->getTargetUrl(),
        ]);
    }

    /**
     * Handle an IdP-initiated SSO launch (OIDC third-party initiated login).
     *
     * Eduplaces' marketplace launcher hits this endpoint with `iss` and
     * (optionally) `login_hint`. The spec does not carry a tenant identifier,
     * but Eduplaces emits a `school` claim in the upstream id_token. We use
     * a sentinel state value to mark the callback as IdP-initiated; the
     * callback then resolves the aula tenant by mapping `school` →
     * `tenants.idp_school_id`.
     */
    public function idpInitiated(Request $request): RedirectResponse|JsonResponse
    {
        $iss = (string) $request->query('iss', '');
        $allowedIssuers = (array) config('services.eduplaces.allowed_issuers', []);

        if ($iss === '' || ! in_array($iss, $allowedIssuers, true)) {
            return response()->json(['error' => 'invalid_issuer'], 400);
        }

        $idpAlias = (string) config('services.eduplaces.idp_alias', 'eduplaces');
        if ($idpAlias === '') {
            return response()->json(['error' => 'idp_alias_missing'], 500);
        }

        $state = $this->buildSignedState(self::IDP_INITIATED_EDUPLACES);

        $params = [
            'state' => $state,
            'kc_idp_hint' => $idpAlias,
        ];
        if (($loginHint = (string) $request->query('login_hint', '')) !== '') {
            $params['login_hint'] = $loginHint;
        }

        /** @var AbstractProvider $driver */
        $driver = Socialite::driver('keycloak');

        $url = $driver
            ->stateless()
            ->with($params)
            ->redirect()
            ->getTargetUrl();

        return redirect()->away($url);
    }

    /**
     * Handle the SSO callback from Keycloak.
     *
     * This is a universal route — no tenant middleware runs here.
     * We verify the signed state to prevent CSRF and to identify the tenant.
     */
    public function callback(Request $request): RedirectResponse
    {
        // Laravel caches the controller instance on the Route, so this object
        // can outlive a single request under a long-running worker. Anything
        // memoised from a previous login has to go before it could be mistaken
        // for this one's identity.
        $this->idpIdTokens = [];

        $state = (string) $request->query('state', '');

        // Read before the state is checked for validity below, so that even the
        // failure paths land back in the app that started the login. An
        // unverifiable state yields false and sends the user to the website,
        // which is the safe way to be wrong: a forged state cannot aim the
        // callback anywhere it could not already reach.
        $this->nativeClient = $this->stateWantsNativeClient($state);

        $instanceCode = $this->verifySignedState($state);
        if ($instanceCode === null) {
            return $this->frontendError('invalid_state');
        }

        // Keycloak (or the upstream IdP) can redirect back with an OAuth error
        // instead of an authorization code — most commonly `access_denied` when the
        // user cancels the login at the identity provider. There is no code to
        // exchange, so surface it to the frontend rather than letting the token
        // exchange in Socialite blow up.
        $oauthError = $request->query('error');
        if ($oauthError !== null && $oauthError !== '') {
            Log::info('SSO: identity provider returned an OAuth error', [
                'tenant' => $instanceCode,
                'error' => $oauthError,
                'error_description' => $request->query('error_description'),
            ]);

            return $this->frontendError(
                $oauthError === 'access_denied' ? 'login_cancelled' : 'sso_provider_error',
            );
        }

        $session = $this->completeOauthAndResolveTenant($instanceCode);
        if ($session instanceof RedirectResponse) {
            return $session;
        }

        [$tenant, $socialiteUser, $instanceCode] = $session;

        tenancy()->initialize($tenant);

        $idToken = $this->verifyCallbackIdToken($socialiteUser, $tenant, $instanceCode);
        if ($idToken instanceof RedirectResponse) {
            return $idToken;
        }

        /** @var Tenant $callbackTenant */
        $callbackTenant = tenant();

        // An admin connecting their own account: link rather than resolve.
        $linkUserId = $this->stateLinkUserId($state);

        if ($linkUserId !== null) {
            return $this->completeIdentityConnection($linkUserId, $socialiteUser, $callbackTenant, $instanceCode);
        }

        $user = $this->resolveCallbackUser($socialiteUser, $callbackTenant, $instanceCode);
        if ($user instanceof RedirectResponse) {
            return $user;
        }

        return $this->issueSsoSession($user, $socialiteUser, $idToken, $callbackTenant);
    }

    /**
     * Complete the Keycloak OAuth round-trip and resolve the aula tenant.
     *
     * For the tenant-scoped flow we fail fast on an unknown tenant before the
     * OAuth round-trip. The IdP-initiated flow can only resolve its tenant
     * afterwards, from the upstream id_token's `school` claim.
     *
     * @return array{0: Tenant, 1: SocialiteOAuth2User, 2: string}|RedirectResponse
     */
    protected function completeOauthAndResolveTenant(string $instanceCode): array|RedirectResponse
    {
        $idpInitiated = $instanceCode === self::IDP_INITIATED_EDUPLACES;

        if (! $idpInitiated) {
            $tenant = Tenant::where('instance_code', $instanceCode)->first();

            if ($tenant === null) {
                return $this->frontendError('unknown_tenant');
            }

            // Checked here as well as in initiate(), because a state signed
            // while SSO was still on stays valid, and nothing stops a caller
            // reaching the callback without going through initiate() at all.
            // The IdP-initiated branch runs the same check once it has resolved
            // its tenant from the school claim.
            if (! $tenant->sso_enabled) {
                Log::warning('SSO: login attempted on a tenant with SSO disabled', [
                    'tenant' => $instanceCode,
                ]);

                return $this->frontendError('sso_disabled');
            }
        }

        /** @var AbstractProvider $driver */
        $driver = Socialite::driver('keycloak');
        /** @var SocialiteOAuth2User $socialiteUser */
        $socialiteUser = $driver->stateless()->user();

        if ($idpInitiated) {
            $resolveResult = $this->resolveTenantFromEduplacesClaim($socialiteUser);

            if ($resolveResult instanceof RedirectResponse) {
                return $resolveResult;
            }

            $tenant = $resolveResult;
            $instanceCode = $tenant->instance_code;
        }

        // Exactly one branch above resolves $tenant, but Psalm cannot correlate
        // the two $idpInitiated checks to prove it.
        /** @var Tenant $tenant */
        return [$tenant, $socialiteUser, $instanceCode];
    }

    /**
     * Persist the SSO tokens on the user, issue an aula JWT and redirect to the
     * frontend OAuth landing page.
     */
    protected function issueSsoSession(LegacyUser $user, LaravelSocialiteUser $laravelSocialiteUser, string $idToken, Tenant $callbackTenant): RedirectResponse
    {
        $user->sso_id_token = $idToken;
        $user->sso_refresh_token = $laravelSocialiteUser->refreshToken;
        $this->recordIdpUserId($user, $laravelSocialiteUser, $callbackTenant);

        $user->save();

        $token = $this->jwtService->generateToken($user);

        return $this->frontendRedirect($token, $callbackTenant->instance_code);
    }

    /**
     * Extract and verify the upstream id_token for a completed OAuth callback.
     *
     * Returns the raw id_token string on success, or a RedirectResponse carrying
     * the appropriate frontend error when the token is missing, fails signature
     * verification, or fails the tenant's email-verification policy.
     */
    protected function verifyCallbackIdToken(SocialiteOAuth2User $socialiteUser, Tenant $tenant, string $instanceCode): string|RedirectResponse
    {
        /** @var SocialiteOAuth2User $socialiteUser */
        $idToken = $socialiteUser->accessTokenResponseBody['id_token'] ?? null;

        if ($idToken === null) {
            Log::warning('SSO: rejecting login because Socialite returned no id_token', [
                'tenant' => $instanceCode,
                'sub' => $socialiteUser->getId(),
            ]);

            return $this->frontendError('id_token_invalid');
        }

        try {
            $verifiedClaims = $this->idTokenVerifier->verify($idToken);
        } catch (IdTokenVerificationException $e) {
            Log::warning('SSO: rejecting login because id_token verification failed', [
                'tenant' => $instanceCode,
                'sub' => $socialiteUser->getId(),
                'reason' => $e->reason,
            ]);

            return $this->frontendError('id_token_invalid');
        }

        if ($tenant->sso_require_email_verified && ($verifiedClaims['email_verified'] ?? null) !== true) {
            Log::warning('SSO: rejecting login because email_verified claim is not true', [
                'tenant' => $instanceCode,
                'sub' => $socialiteUser->getId(),
            ]);

            return $this->frontendError('email_not_verified');
        }

        return $idToken;
    }

    /**
     * Resolve the aula user for a verified SSO identity: match by sso_sub, fall
     * back to email (provisioning a new user or requiring an explicit account
     * link), and ensure the resulting account is active.
     *
     * Returns the active LegacyUser, or a RedirectResponse carrying the frontend
     * error/flow signal (account_inactive, sub_collision, account_link_required).
     */
    protected function resolveCallbackUser(LaravelSocialiteUser $laravelSocialiteUser, Tenant $callbackTenant, string $instanceCode): LegacyUser|RedirectResponse
    {
        $sub = $laravelSocialiteUser->getId();
        $email = $laravelSocialiteUser->getEmail();

        $user = $this->ssoUserService->findBySub($sub);
        $bootstrapped = null;

        if ($user === null) {
            // Nobody has ever signed in here, so this login owns the school: it
            // takes over the admin seeded at tenant creation and pulls in the
            // directory roster before anyone else arrives.
            $bootstrapped = $this->bootstrapIdpTenant($laravelSocialiteUser, $callbackTenant, $instanceCode);
            $user = $bootstrapped;
        }

        // A login that just bootstrapped is the one that decided which school
        // this tenant is, so there is nothing to hold it against. Every other
        // login has to prove it belongs to the school already on the tenant.
        if ($bootstrapped === null) {
            $foreign = $this->rejectForeignSchool($laravelSocialiteUser, $callbackTenant, $instanceCode);

            if ($foreign !== null) {
                return $foreign;
            }
        }

        if ($user === null && $callbackTenant->isMigratingToIdp()) {
            // A school mid-migration holds accounts that predate the provider.
            // The row waiting for this identity may be an empty one the import
            // made, while the person's real account sits unmatched beside it —
            // so ask before handing them the empty one.
            $claim = $this->offerAccountClaim($laravelSocialiteUser, $callbackTenant, $instanceCode);

            if ($claim !== null) {
                return $claim;
            }
        }

        if ($user === null) {
            // Imported by the school import, or announced by a webhook, before
            // this person ever signed in. Claim the row, do not duplicate it.
            $user = $this->adoptDirectoryProvisionedUser($laravelSocialiteUser, $callbackTenant, $instanceCode);
        }

        if ($user === null) {
            $emailMatch = $this->ssoUserService->findByEmail($email);

            if ($emailMatch === null) {
                $user = $this->ssoUserService->provisionUser($laravelSocialiteUser);
            } else {
                if (! $emailMatch->isActive()) {
                    return $this->frontendError('account_inactive');
                }

                if ($emailMatch->sso_sub !== null) {
                    Log::warning('SSO: email matches a user already bound to a different sso_sub', [
                        'tenant' => $instanceCode,
                        'incoming_sub' => $sub,
                        'existing_sub' => $emailMatch->sso_sub,
                        'matched_user_id' => $emailMatch->id,
                    ]);

                    return $this->frontendError('sub_collision');
                }

                $linkToken = $this->storeLinkIntent($emailMatch, $laravelSocialiteUser, $callbackTenant);

                return $this->frontendError('account_link_required', ['sso_link' => $linkToken]);
            }
        } else {
            $strayEmailMatch = $this->ssoUserService->findByEmail($email);
            if ($strayEmailMatch && $strayEmailMatch->id !== $user->id) {
                Log::warning('SSO: email and sso_sub match different users — prioritising sso_sub match.', [
                    'email' => $email,
                    'sub' => $sub,
                    'sso_sub_user' => $user->id,
                    'email_user' => $strayEmailMatch->id,
                ]);
            }
        }

        if (! $user->isActive()) {
            return $this->frontendError('account_inactive');
        }

        return $user;
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
        $token = $request->input('sso_link_token');

        $intent = Cache::get($this->linkIntentCacheKey($token));

        if (! is_array($intent)) {
            return response()->json(['success' => false, 'error' => 'link_intent_not_found'], 404);
        }

        if (($intent['claimable'] ?? false) === true) {
            return $this->completeAccountClaim($intent, $authUser, $token);
        }

        if (($intent['user_id'] ?? null) !== $authUser->id) {
            Log::warning('SSO: link rejected — bearer JWT user does not match link intent', [
                'authenticated_user' => $authUser->id,
                'intent_user' => $intent['user_id'] ?? null,
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
            $fresh->sso_sub = $intent['sso_sub'];
            $fresh->sso_id_token = $intent['sso_id_token'] ?? null;
            $fresh->sso_refresh_token = $intent['sso_refresh_token'] ?? null;
            if (is_string($intent['idp_user_id'] ?? null) && $intent['idp_user_id'] !== '') {
                $fresh->idp_user_id = $intent['idp_user_id'];
            }

            $fresh->save();
        });

        Cache::forget($this->linkIntentCacheKey($token));

        $this->advanceMigrationAfterConnect($fresh);

        return response()->json(['success' => true]);
    }

    /**
     * SSO logout endpoint.
     *
     * When the tenant has sso_force_logout enabled, returns a Keycloak
     * logout URL that the frontend must navigate to in order to end the
     * user's Keycloak session (RP-initiated logout).
     *
     * Logging the user out of the upstream IdP is Keycloak's job, not ours:
     * configuring the IdP's end_session_endpoint as the identity provider's
     * "Logout URL" makes Keycloak chain the logout itself, using a static
     * post_logout_redirect_uri (its own broker logout_response endpoint) that
     * the IdP can whitelist. Chaining it here instead produced a redirect URI
     * carrying a per-logout id_token_hint, which no IdP can ever whitelist.
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

        /** @var LegacyUser|null $user */
        $user = $request->attributes->get('authenticated_user');

        $frontendUrl = rtrim(config('app.frontend_url', '/'), '/');

        $logoutUrl = $this->buildKeycloakLogoutUrl($user?->sso_id_token, $frontendUrl);

        // The front-channel redirect is what triggers Keycloak's IdP logout
        // propagation, so it needs a live session to act on. Revoking the
        // session server-side first would leave Keycloak nothing to propagate,
        // which makes back-channel revocation a fallback for the case where we
        // cannot build that URL at all.
        if ($logoutUrl === null) {
            $this->revokeKeycloakSession($user?->sso_refresh_token);
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
    /**
     * @param  int|null  $linkUserId  when set, the callback links the provider
     *                                identity to this aula account instead of
     *                                resolving or creating one. Safe to carry
     *                                here because the payload is HMAC-signed by
     *                                us, and /sso/link re-checks it against the
     *                                bearer token anyway.
     * @param  bool  $nativeApp  when true, the callback ends on the app's
     *                           deep-link scheme instead of on the website. The
     *                           state is the only thing that survives the round
     *                           trip through Keycloak, so it has to carry this.
     */
    protected function buildSignedState(string $instanceCode, ?int $linkUserId = null, bool $nativeApp = false): string
    {
        $payload = base64_encode(json_encode(array_filter([
            'instance_code' => $instanceCode,
            'link_user_id' => $linkUserId,
            'client' => $nativeApp ? self::CLIENT_APP : null,
            'nonce' => Str::random(16),
        ], fn ($value): bool => $value !== null)));

        $signature = hash_hmac('sha256', $payload, $this->stateSecret());

        return $payload.'.'.$signature;
    }

    /**
     * Verify the signed state and return the instance_code, or null on failure.
     */
    protected function verifySignedState(string $state): ?string
    {
        $code = $this->decodeSignedState($state)['instance_code'] ?? null;

        return is_string($code) ? $code : null;
    }

    /**
     * The aula account a callback was told to link to, if any.
     */
    protected function stateLinkUserId(string $state): ?int
    {
        $id = $this->decodeSignedState($state)['link_user_id'] ?? null;

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    /**
     * Whether the login this state belongs to was started inside a native app.
     */
    protected function stateWantsNativeClient(string $state): bool
    {
        return ($this->decodeSignedState($state)['client'] ?? null) === self::CLIENT_APP;
    }

    /**
     * Whether a caller of one of the initiate endpoints is a native app.
     */
    protected function wantsNativeClient(Request $request): bool
    {
        return $request->query('client') === self::CLIENT_APP;
    }

    /**
     * Decode a state, or an empty array when it is missing, malformed or not
     * signed by us. Callers treat an absent key and a rejected signature the
     * same way, so nothing downstream can act on an unverified payload.
     *
     * @return array<string, mixed>
     */
    private function decodeSignedState(string $state): array
    {
        $parts = explode('.', $state, 2);

        if (count($parts) !== 2) {
            return [];
        }

        [$payload, $signature] = $parts;

        if (! hash_equals(hash_hmac('sha256', $payload, $this->stateSecret()), $signature)) {
            return [];
        }

        $data = json_decode((string) base64_decode($payload, true), true);

        return is_array($data) ? $data : [];
    }

    /**
     * Resolve the aula tenant for an IdP-initiated Eduplaces login by reading
     * the `school` claim from the upstream id_token. Tenants are mapped to
     * Eduplaces schools by the `idp_school_id` column.
     *
     * @return Tenant|RedirectResponse Tenant on success; a RedirectResponse with a
     *                                 frontend error code on failure.
     */
    protected function resolveTenantFromEduplacesClaim(LaravelSocialiteUser $socialiteUser): Tenant|RedirectResponse
    {
        // No tenant yet — that is what this resolves — so read the claim under
        // every configured provider's name until one matches a tenant.
        $payload = $this->decodeIdTokenPayload(
            $this->fetchIdpIdToken($socialiteUser->token, (string) config('services.eduplaces.idp_alias', 'eduplaces')),
        );
        $schoolId = null;

        foreach ($this->idpProviders->all() as $alias) {
            $name = (string) $this->idpProviders->config($alias, 'claims.school', 'school');
            $value = is_array($payload) ? ($payload[$name] ?? null) : null;

            if (is_string($value) && $value !== '') {
                $schoolId = $value;
                break;
            }
        }

        // The loop only ever assigns a non-empty string, so being a string is
        // the whole test.
        if (! is_string($schoolId)) {
            Log::warning('SSO: IdP-initiated login has no school claim', [
                'keycloak_sub' => $socialiteUser->getId(),
                'claim_keys' => is_array($payload) ? array_keys($payload) : null,
            ]);

            return $this->frontendError('idp_school_missing');
        }

        $tenant = Tenant::where('idp_school_id', $schoolId)->first();

        if ($tenant === null) {
            Log::warning('SSO: no aula tenant matches Eduplaces school', [
                'idp_school_id' => $schoolId,
            ]);

            return $this->frontendError('school_not_provisioned');
        }

        if (! $tenant->sso_enabled) {
            return $this->frontendError('sso_disabled');
        }

        return $tenant;
    }

    /**
     * Decoded claims of the upstream Eduplaces id_token, read through
     * Keycloak's broker token endpoint.
     *
     * @return array<string, mixed>|null
     */
    protected function idpClaims(LaravelSocialiteUser $socialiteUser, Tenant $tenant): ?array
    {
        return $this->decodeIdTokenPayload(
            $this->fetchIdpIdToken($socialiteUser->token, $tenant->sso_provider),
        );
    }

    /**
     * Read a claim by the name this tenant's provider uses for it.
     *
     * @param  array<string, mixed>|null  $claims
     */
    protected function idpClaim(?array $claims, Tenant $tenant, string $which): ?string
    {
        $provider = $tenant->sso_provider;

        if (! is_array($claims) || $provider === null) {
            return null;
        }

        $name = (string) $this->idpProviders->config($provider, "claims.{$which}", $which === 'user' ? 'sub' : $which);
        $value = $claims[$name] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Stamp the provider's user id onto the user row.
     *
     * `sso_sub` holds the Keycloak subject, which Keycloak mints itself when
     * it brokers a provider. Directory imports and webhooks reference the
     * provider's own user id
     * instead, so without this column no incoming event can be matched to a
     * user. The upstream `sub` is that id — providers document it as the
     * permanent identifier for a person.
     */
    protected function recordIdpUserId(LegacyUser $user, LaravelSocialiteUser $socialiteUser, Tenant $tenant): void
    {
        if (! $this->usesIdpDirectory($tenant)) {
            return;
        }

        $claims = $this->idpClaims($socialiteUser, $tenant);
        $personId = $this->idpClaim($claims, $tenant, 'user');

        if (! is_string($personId) || $personId === '') {
            Log::warning('SSO: login carried no upstream sub — webhooks cannot match this user', [
                'tenant' => $tenant->instance_code,
                'user_id' => $user->id,
            ]);

            return;
        }

        if ($user->idp_user_id === $personId) {
            return;
        }

        // The column is unique. Another row already holding this id means two
        // aula accounts claim one directory user, which needs a human.
        $conflict = LegacyUser::where('idp_user_id', $personId)
            ->where('id', '!=', $user->id)
            ->first();

        if ($conflict !== null) {
            Log::warning('SSO: provider user id already bound to a different user', [
                'tenant' => $tenant->instance_code,
                'idp_user_id' => $personId,
                'incoming_user_id' => $user->id,
                'existing_user_id' => $conflict->id,
            ]);

            return;
        }

        $user->idp_user_id = $personId;
    }

    /**
     * Finish an admin's account connection.
     *
     * Learns which school this tenant is from the claim, then hands back an
     * ordinary link intent so the frontend can complete it against the bearer
     * token. No session is issued here: the admin already has one.
     */
    protected function completeIdentityConnection(int $userId, LaravelSocialiteUser $socialiteUser, Tenant $tenant, string $instanceCode): RedirectResponse
    {
        $user = LegacyUser::find($userId);

        if ($user === null) {
            return $this->frontendError('link_user_not_found');
        }

        $claims = $this->idpClaims($socialiteUser, $tenant);
        $personId = $this->idpClaim($claims, $tenant, 'user');

        if ($personId === null) {
            return $this->frontendError('idp_user_missing');
        }

        $schoolError = $this->learnSchoolId($tenant, $claims, $instanceCode);

        if ($schoolError !== null) {
            return $this->frontendError($schoolError);
        }

        $conflict = LegacyUser::where('idp_user_id', $personId)
            ->where('id', '!=', $user->id)
            ->first();

        if ($conflict !== null) {
            Log::warning('SSO: provider identity already belongs to another aula account', [
                'tenant' => $instanceCode,
                'idp_user_id' => $personId,
                'existing_user_id' => $conflict->id,
            ]);

            return $this->frontendError('idp_identity_taken');
        }

        $token = $this->storeLinkIntent($user, $socialiteUser, $tenant);

        return $this->frontendRedirectToSettings($token, $tenant->instance_code);
    }

    /**
     * Send the admin back to where they started the connection, carrying the
     * one-shot token their browser needs to complete it.
     */
    protected function frontendRedirectToSettings(string $linkToken, string $instanceCode): RedirectResponse
    {
        return redirect()->away($this->clientUrl('settings/idp-sync', [
            'sso_link' => $linkToken,
            'code' => $instanceCode,
        ]));
    }

    /**
     * Bootstrap a directory-synced tenant on its very first SSO login.
     *
     * Whoever holds the instance code signs in before anyone else. Rather than
     * provision them a second account alongside the admin that tenant creation
     * seeded, that admin row *becomes* their account — one admin, not two — and
     * the whole school is imported behind it.
     *
     * Fires only while no user in the tenant has an sso_sub, so it happens
     * exactly once.
     *
     * The import runs inline: everyone logging in afterwards has to find their
     * account already there, which is only true once it has finished.
     */
    protected function bootstrapIdpTenant(LaravelSocialiteUser $socialiteUser, Tenant $tenant, string $instanceCode): ?LegacyUser
    {
        if (! $this->usesIdpDirectory($tenant)) {
            return null;
        }

        if (LegacyUser::whereNotNull('sso_sub')->exists()) {
            return null;
        }

        // A school that already used aula gets migrated deliberately, by an
        // admin who proves who they are. Bootstrapping it would hand the
        // school's real admin account to whoever signed in first.
        if ($tenant->isMigratingToIdp()) {
            return null;
        }

        $claims = $this->idpClaims($socialiteUser, $tenant);
        $personId = $this->idpClaim($claims, $tenant, 'user');

        if (! is_string($personId) || $personId === '') {
            Log::warning('SSO: cannot bootstrap a synced tenant without an upstream sub', [
                'tenant' => $instanceCode,
            ]);

            return null;
        }

        if ($this->learnSchoolId($tenant, $claims, $instanceCode) !== null) {
            return null;
        }

        $admin = $this->tenantAdmin($tenant);

        if ($admin === null) {
            Log::warning('SSO: no admin row for the first SSO login to take over', [
                'tenant' => $instanceCode,
            ]);

            return null;
        }

        // Belt and braces for a school nobody remembered to flag as migrating.
        // A tenant holding anyone beyond its seeded admins is already in use,
        // and claiming its admin account would be a takeover by whoever signed
        // in first. Password presence is not the signal — tenant creation can
        // pre-set one — but having other people is.
        if ($this->hasUsersBeyondSeededAdmins($tenant)) {
            Log::warning('SSO: refusing to bootstrap a tenant that already has users', [
                'tenant' => $instanceCode,
                'user_id' => $admin->id,
            ]);

            return null;
        }

        Log::info('SSO: first SSO login is taking over the tenant admin', [
            'tenant' => $instanceCode,
            'user_id' => $admin->id,
            'idp_user_id' => $personId,
        ]);

        $admin->sso_sub = $socialiteUser->getId();
        $admin->idp_user_id = $personId;
        $admin->save();

        // Marked before dispatch so there is no window in which the school
        // looks ready because its import has not been picked up yet.
        $tenant->update([
            'idp_import_status' => SchoolImport::STATUS_PENDING,
            'idp_import_error' => null,
            'idp_import_started_at' => now(),
            'idp_import_finished_at' => null,
        ]);

        // Queued, not inline: a large school must not have to fit inside the
        // login request, and the frontend needs to see the import in progress.
        ImportSchoolForTenant::dispatch((string) $tenant->id);

        return $admin->fresh();
    }

    /**
     * Learn which school this tenant is, from the login itself.
     *
     * The upstream id_token carries a `school` claim, so nobody has to look a
     * UUID up and configure it by hand: the first person through the door tells
     * us which school they came from, and that is what gets imported.
     *
     * The two ways this fails look identical from the outside and are not: a
     * login with no school claim at all is a configuration problem, while a
     * school another tenant already holds is usually the wrong instance code.
     * Reporting both as "missing" sends the operator looking in the wrong place.
     *
     * @param  array<string, mixed>|null  $claims
     * @return string|null an error code, or null when the school is established
     */
    /**
     * Refuse a login that comes from a different school than this tenant is.
     *
     * Only the very first login gets to say which school a tenant belongs to;
     * after that the binding is a fact, and a login from anywhere else is
     * someone signing into a school that is not theirs. Nothing checked this,
     * so any Keycloak-verified identity was provisioned an account on any
     * instance code it happened to be pointed at.
     *
     * A tenant with no school at all is refused too. It would be the first
     * login that establishes one, and by here that has already declined.
     *
     * @return RedirectResponse|null null when the login may proceed
     */
    protected function rejectForeignSchool(LaravelSocialiteUser $socialiteUser, Tenant $tenant, string $instanceCode): ?RedirectResponse
    {
        // Tenants that sync from no directory have no school to belong to.
        if (! $this->usesIdpDirectory($tenant)) {
            return null;
        }

        if ($tenant->idp_school_id === null) {
            Log::warning('SSO: refusing a login to a tenant with no school of its own', [
                'tenant' => $instanceCode,
                'keycloak_sub' => $socialiteUser->getId(),
            ]);

            return $this->frontendError('school_not_provisioned');
        }

        $schoolId = $this->idpClaim($this->idpClaims($socialiteUser, $tenant), $tenant, 'school');

        if ($schoolId === null) {
            Log::warning('SSO: refusing a login that carries no school claim', [
                'tenant' => $instanceCode,
                'keycloak_sub' => $socialiteUser->getId(),
            ]);

            return $this->frontendError('idp_school_missing');
        }

        if ($schoolId !== $tenant->idp_school_id) {
            Log::warning('SSO: refusing a login from a different school', [
                'tenant' => $instanceCode,
                'tenant_school_id' => $tenant->idp_school_id,
                'login_school_id' => $schoolId,
                'keycloak_sub' => $socialiteUser->getId(),
            ]);

            return $this->frontendError('school_mismatch');
        }

        return null;
    }

    protected function learnSchoolId(Tenant $tenant, ?array $claims, string $instanceCode): ?string
    {
        $schoolId = $this->idpClaim($claims, $tenant, 'school');

        if (! is_string($schoolId) || $schoolId === '') {
            Log::warning('SSO: first SSO login carried no school claim, nothing to import', [
                'tenant' => $instanceCode,
                'claim_keys' => is_array($claims) ? array_keys($claims) : null,
            ]);

            return 'idp_school_missing';
        }

        if ($tenant->idp_school_id === $schoolId) {
            return null;
        }

        // The column is unique: one school, one tenant. Someone signing into the
        // wrong instance code would otherwise silently move a school across
        // tenants, or blow up on the unique index.
        $taken = Tenant::where('idp_school_id', $schoolId)
            ->where('id', '!=', $tenant->id)
            ->exists();

        if ($taken) {
            Log::warning('SSO: school already belongs to another tenant', [
                'tenant' => $instanceCode,
                'idp_school_id' => $schoolId,
            ]);

            return 'idp_school_taken';
        }

        $tenant->update(['idp_school_id' => $schoolId]);

        Log::info('SSO: learned the school from the first login', [
            'tenant' => $instanceCode,
            'idp_school_id' => $schoolId,
        ]);

        return null;
    }

    /**
     * Whether anyone uses this school beyond the accounts tenant creation made.
     */
    protected function hasUsersBeyondSeededAdmins(Tenant $tenant): bool
    {
        $seeded = array_filter([$tenant->admin1_username, $tenant->admin2_username]);

        return LegacyUser::when(
            $seeded !== [],
            fn ($query) => $query->whereNotIn('username', $seeded),
        )->exists();
    }

    /**
     * The admin seeded by tenant creation: matched on `admin1_username`, falling
     * back to the longest-standing admin-level account.
     */
    protected function tenantAdmin(Tenant $tenant): ?LegacyUser
    {
        $byUsername = $tenant->admin1_username !== ''
            ? LegacyUser::where('username', $tenant->admin1_username)->first()
            : null;

        return $byUsername ?? LegacyUser::where('userlevel', '>=', UserLevel::Admin->value)
            ->orderBy('id')
            ->first();
    }

    /**
     * Attach a claimed provider identity to the account whose password was
     * just proved, and discard the empty row the import had waiting.
     *
     * @param  array<string, mixed>  $intent
     */
    protected function completeAccountClaim(array $intent, LegacyUser $authUser, string $token): JsonResponse
    {
        $personId = (string) ($intent['idp_user_id'] ?? '');
        $fresh = LegacyUser::find($authUser->id);

        if ($fresh === null) {
            return response()->json(['success' => false, 'error' => 'user_not_found'], 404);
        }

        if ($fresh->sso_sub !== null && $fresh->sso_sub !== $intent['sso_sub']) {
            return response()->json(['success' => false, 'error' => 'already_linked'], 409);
        }

        $holder = LegacyUser::where('idp_user_id', $personId)->where('id', '!=', $fresh->id)->first();

        if ($holder !== null && (! empty($holder->pw) || $holder->sso_sub !== null)) {
            // Somebody real already owns this identity. Two accounts claiming
            // one person needs a human, not a silent reassignment.
            Log::warning('SSO: refusing to move a provider identity off a real account', [
                'idp_user_id' => $personId,
                'existing_user_id' => $holder->id,
            ]);

            return response()->json(['success' => false, 'error' => 'idp_identity_taken'], 409);
        }

        DB::transaction(function () use ($fresh, $intent, $personId, $holder): void {
            if ($holder !== null) {
                // An import-made row has no content by construction, so the
                // identity moves to the real account and the empty one goes.
                DB::table('au_rel_rooms_users')->where('user_id', $holder->id)->delete();
                $holder->delete();
            }

            $fresh->sso_sub = $intent['sso_sub'];
            $fresh->idp_user_id = $personId;
            $fresh->sso_id_token = $intent['sso_id_token'] ?? null;
            $fresh->sso_refresh_token = $intent['sso_refresh_token'] ?? null;
            $fresh->save();
        });

        Cache::forget($this->linkIntentCacheKey($token));

        Log::info('SSO: a migrating user claimed their existing account', [
            'user_id' => $fresh->id,
            'idp_user_id' => $personId,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Ask someone mid-migration whether they already have an aula account.
     *
     * Name matching cannot reach everybody — a person the review could not
     * match has an empty row waiting for their provider identity and a real
     * account, with all their work, sitting unlinked. Adoption would silently
     * give them the empty one.
     *
     * So: no session yet. They either prove an existing password, or say they
     * are new. Logging them in first and asking afterwards would make
     * dismissing the question the easiest path, and produce the duplicate
     * quietly.
     *
     * Returns null when there is nothing to ask about.
     */
    protected function offerAccountClaim(LaravelSocialiteUser $socialiteUser, Tenant $tenant, string $instanceCode): ?RedirectResponse
    {
        $personId = $this->idpClaim($this->idpClaims($socialiteUser, $tenant), $tenant, 'user');

        if ($personId === null) {
            return null;
        }

        $candidate = $this->ssoUserService->findByIdpUserId($personId);

        // A real account already carrying this identity: its owner has signed
        // in before, or a merge asserted it. The ordinary paths handle both.
        if ($candidate !== null && ($candidate->sso_sub !== null || ! empty($candidate->pw))) {
            return null;
        }

        // No row at all is the commonest case, not an exception: before the
        // merge is applied nothing carries a provider id, and that is exactly
        // when everyone still has only their old password account. Provisioning
        // here is what produces the duplicate this question exists to prevent.
        $token = $this->storeAccountClaimIntent($personId, $candidate?->id, $socialiteUser, $tenant);

        Log::info('SSO: asking a migrating school whether this person already has an account', [
            'tenant' => $instanceCode,
            'idp_user_id' => $personId,
        ]);

        return $this->frontendError('account_link_required', ['sso_link' => $token, 'claimable' => 1]);
    }

    /**
     * An intent nobody owns yet.
     *
     * Unlike a link started from a known account, this one is claimed by
     * whoever proves an aula password — which is exactly the assertion being
     * made: "that provider identity is me". Possession of the account is still
     * what gets proved, so the trust model is unchanged.
     *
     * `$shellUserId` is null when nothing local holds this identity yet, which
     * is the normal state before a merge is applied.
     */
    protected function storeAccountClaimIntent(string $personId, ?int $shellUserId, LaravelSocialiteUser $socialiteUser, Tenant $tenant): string
    {
        $token = bin2hex(random_bytes(16));

        Cache::put($this->linkIntentCacheKey($token), [
            'claimable' => true,
            'idp_user_id' => $personId,
            'shell_user_id' => $shellUserId,
            'sso_sub' => $socialiteUser->getId(),
            'sso_id_token' => $this->accessTokenBody($socialiteUser)['id_token'] ?? null,
            'sso_refresh_token' => $socialiteUser->refreshToken,
            'instance_code' => $tenant->instance_code,
        ], now()->addMinutes(self::LINK_INTENT_TTL_MINUTES));

        return $token;
    }

    /**
     * The provider's token response, which only its own user object carries.
     *
     * @return array<array-key, mixed>
     */
    protected function accessTokenBody(LaravelSocialiteUser $socialiteUser): array
    {
        return $socialiteUser instanceof SocialiteOAuth2User && is_array($socialiteUser->accessTokenResponseBody)
            ? $socialiteUser->accessTokenResponseBody
            : [];
    }

    /**
     * Complete a claim for somebody who has no aula account yet.
     *
     * Authenticated by the one-shot token alone: they have just proved an
     * identity at the provider and have no aula credentials to offer. Without
     * this, a new pupil would loop on the link prompt forever.
     */
    public function declineAccountClaim(Request $request): JsonResponse
    {
        $request->validate(['sso_link_token' => 'required|string']);

        $token = (string) $request->input('sso_link_token');
        $intent = Cache::get($this->linkIntentCacheKey($token));

        if (! is_array($intent) || ($intent['claimable'] ?? false) !== true) {
            return response()->json(['success' => false, 'error' => 'link_intent_not_found'], 404);
        }

        $user = LegacyUser::find((int) ($intent['shell_user_id'] ?? 0))
            // Nothing was waiting for them, so they are new to the school as
            // well: give them the account the import would have made, rooms
            // and role included.
            ?? $this->provisionFromDirectory((string) ($intent['idp_user_id'] ?? ''));

        if ($user === null) {
            return response()->json(['success' => false, 'error' => 'user_not_found'], 404);
        }

        $user->sso_sub = $intent['sso_sub'];
        $user->sso_id_token = $intent['sso_id_token'] ?? null;
        $user->sso_refresh_token = $intent['sso_refresh_token'] ?? null;
        $user->save();

        Cache::forget($this->linkIntentCacheKey($token));

        return response()->json(['success' => true, 'JWT' => $this->jwtService->generateToken($user)]);
    }

    /**
     * Build the account for a person the directory knows and aula does not.
     *
     * Through the import's own path, so a person who arrives before the roster
     * does gets the same row the roster would have given them.
     */
    protected function provisionFromDirectory(string $personId): ?LegacyUser
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();
        $provider = $tenant === null ? null : $this->idpProviders->forTenant($tenant);

        if ($personId === '' || $tenant === null || $provider === null) {
            return null;
        }

        $directory = $this->idpProviders->directory($provider);

        try {
            $person = method_exists($directory, 'personOrUser')
                ? $directory->personOrUser($personId)
                : $directory->user($personId);
        } catch (DirectoryException $e) {
            Log::warning('SSO: cannot provision a newcomer, the directory is unreachable', [
                'tenant' => $tenant->instance_code,
                'idp_user_id' => $personId,
                'reason' => $e->reason,
            ]);

            return null;
        }

        return $person === null ? null : $this->schoolImport->importUser($tenant, $provider, $person);
    }

    /**
     * Hand this person the account that already carries their identity.
     *
     * Usually a shell the import or a webhook made before they ever signed in.
     * It can also be a real account with a password and everything they have
     * written, if a migrating school's admin confirmed the two are the same
     * person — and that confirmation is the whole point of the merge review, so
     * the login honours it rather than asking them to prove it again.
     *
     * `idp_user_id` is never guessed. It is written by the import, by an applied
     * merge, by a claim the person proved with their password, or by an admin
     * connecting their own account: each one a deliberate assertion that this
     * provider identity is this account. An `sso_sub` already set is the one
     * case left alone, since re-binding it would move the account to a
     * different person at the provider.
     */
    protected function adoptDirectoryProvisionedUser(LaravelSocialiteUser $socialiteUser, Tenant $tenant, string $instanceCode): ?LegacyUser
    {
        if (! $this->usesIdpDirectory($tenant)) {
            return null;
        }

        $claims = $this->idpClaims($socialiteUser, $tenant);
        $personId = $this->idpClaim($claims, $tenant, 'user');

        if (! is_string($personId) || $personId === '') {
            return null;
        }

        $candidate = $this->ssoUserService->findByIdpUserId($personId);

        if ($candidate === null || $candidate->sso_sub !== null) {
            return null;
        }

        Log::info('SSO: adopting a directory-provisioned account', [
            'tenant' => $instanceCode,
            'user_id' => $candidate->id,
            'idp_user_id' => $personId,
        ]);

        $candidate->sso_sub = $socialiteUser->getId();

        if ($socialiteUser->getEmail() !== null) {
            // First sight of an address for this person: the IDM never has one.
            $candidate->email = $socialiteUser->getEmail();
        }

        $candidate->save();

        return $candidate;
    }

    /**
     * Whether this tenant's users come from a directory. Gates the broker call
     * so tenants on other IdPs do not pay for a lookup that cannot succeed.
     */
    protected function usesIdpDirectory(Tenant $tenant): bool
    {
        return $tenant->usesIdpDirectory();
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

        $base = rtrim(config('services.keycloak.base_url'), '/');
        $realm = config('services.keycloak.realms', 'master');

        return "{$base}/realms/{$realm}/protocol/openid-connect/logout?".http_build_query([
            'id_token_hint' => $idToken,
            'post_logout_redirect_uri' => $redirectUri,
        ]);
    }

    protected function revokeKeycloakSession(?string $refreshToken): void
    {
        if (! $refreshToken) {
            return;
        }

        $base = rtrim(config('services.keycloak.base_url'), '/');
        $realm = config('services.keycloak.realms', 'master');

        Http::asForm()->post("{$base}/realms/{$realm}/protocol/openid-connect/logout", [
            'client_id' => config('services.keycloak.client_id'),
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

        if (array_key_exists($provider, $this->idpIdTokens)) {
            return $this->idpIdTokens[$provider];
        }

        $base = rtrim(config('services.keycloak.base_url'), '/');
        $realm = config('services.keycloak.realms', 'master');

        $response = Http::withToken($accessToken)
            ->get("{$base}/realms/{$realm}/broker/{$provider}/token");

        if (! $response->ok()) {
            return $this->idpIdTokens[$provider] = null;
        }

        return $this->idpIdTokens[$provider] = $response->json('id_token');
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

        $padded = str_pad($parts[1], strlen($parts[1]) + (4 - strlen($parts[1]) % 4) % 4, '=');
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

    protected function frontendRedirect(string $token, ?string $instanceCode = null): RedirectResponse
    {
        // Carry the resolved tenant back to the frontend so the OAuth landing
        // page can populate localStorage for IdP-initiated launches that
        // started without any instance context.
        $query = empty($instanceCode) ? [] : ['code' => $instanceCode];

        return redirect()->away($this->clientUrl("oauth-login/{$token}", $query));
    }

    protected function frontendError(string $code, array $extra = []): RedirectResponse
    {
        return redirect()->away($this->clientUrl('login', ['sso_error' => $code] + $extra));
    }

    /**
     * Where a finished callback sends the browser.
     *
     * The website and the native apps are the same frontend reached two ways,
     * so they share every route; only the origin differs. Building both from
     * one place keeps a new exit from the callback landing in the app as well.
     *
     * @param  array<string, mixed>  $query
     */
    protected function clientUrl(string $path, array $query = []): string
    {
        $base = $this->nativeClient
            // A custom scheme has no host, so the first path segment becomes
            // one: `de.aula.neu://oauth-login/<jwt>`. The app matches on the
            // scheme alone and reads the rest as a route.
            ? rtrim((string) config('app.mobile_url_scheme'), ':/').'://'
            : rtrim((string) config('app.frontend_url', '/'), '/').'/';

        $url = $base.ltrim($path, '/');

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    /**
     * Persist an account-link intent in the cache and return the opaque token.
     * The intent carries everything the link endpoint needs to stamp the row
     * once the user has proven legacy-account possession via password.
     */
    protected function storeLinkIntent(LegacyUser $emailMatch, SocialiteOAuth2User $socialiteUser, Tenant $tenant): string
    {
        $token = bin2hex(random_bytes(16));

        Cache::put($this->linkIntentCacheKey($token), [
            'user_id' => $emailMatch->id,
            'email' => $emailMatch->email,
            'sso_sub' => $socialiteUser->getId(),
            'sso_id_token' => $socialiteUser->accessTokenResponseBody['id_token'] ?? null,
            'sso_refresh_token' => $socialiteUser->refreshToken,
            // Carried through the link flow so an account that enrols via
            // password proof is matchable by webhooks too, not just one
            // provisioned straight from the callback.
            'idp_user_id' => $this->usesIdpDirectory($tenant)
                ? $this->idpClaim($this->idpClaims($socialiteUser, $tenant), $tenant, 'user')
                : null,
            'instance_code' => $tenant->instance_code,
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
