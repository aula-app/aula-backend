<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserLevel;
use App\Http\Controllers\Controller;
use App\Jobs\ImportSchoolForTenant;
use App\Models\LegacyUser;
use App\Models\Tenant;
use App\Services\Idp\Contracts\IdentityDirectory;
use App\Services\Idp\DirectoryException;
use App\Services\Idp\Dto\IdpUser;
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
     * Sentinel instance_code in the signed state. callback() then resolves the
     * tenant from the id_token `school` claim instead of from the state.
     */
    private const string IDP_INITIATED_EDUPLACES = '__IDP_INITIATED_EDUPLACES__';

    /**
     * Value of `?client=` on the initiate endpoints, and of `client` in the
     * signed state, marking a flow as started inside a native app.
     */
    private const string CLIENT_APP = 'app';

    /**
     * Whether the flow started in a native app, read from the signed state at
     * the top of callback().
     *
     * Held on the instance because every exit runs through frontendRedirect()
     * or frontendError(), called from a dozen places that carry no client flag.
     */
    private bool $nativeClient = false;

    /**
     * Upstream IdP id_tokens fetched during this request, keyed by provider
     * alias. Each miss costs a round trip to Keycloak's broker token endpoint.
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
     * Whether this tenant has sso_enabled, for the login page.
     *
     * Unauthenticated: the response says no more than a refused initiate()
     * would.
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
     * Return the Keycloak authorization URL for a tenant-scoped login.
     *
     * instance_code travels in the signed state because callback() is a
     * universal route and sees no tenant header. `?client=app` makes callback()
     * end on the app's deep-link scheme.
     */
    public function initiate(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        if (! $tenant->sso_enabled) {
            return response()->json(['error' => 'sso_disabled'], 403);
        }

        $idpHint = $tenant->sso_provider ?? null;

        Log::info('SSO: starting a login', [
            'tenant' => $tenant->instance_code,
            'idp_hint' => $idpHint,
        ]);

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
            // Carries the user pre-selection from idpInitiated() when the
            // frontend re-enters initiate().
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
     * Move idp_migration_status from IDP_MIGRATION_FLAGGED to
     * IDP_MIGRATION_CONNECTED once an admin has linked and idp_school_id is set.
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
     * Start linking an admin's own aula account to the identity provider.
     *
     * Sets tenants.idp_school_id from the admin's id_token, which SchoolImport
     * and MergeProposalBuilder both need. The bearer token proves the aula
     * account; the callback still goes through storeLinkIntent(), which
     * re-checks it.
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
            // Capture the provider identity of the admin making this request,
            // not a Keycloak session left in the browser by an earlier login.
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
     * OIDC third-party initiated login from the Eduplaces launcher.
     *
     * The launch carries `iss` and optionally `login_hint`, but no tenant
     * identifier, so the state is set to IDP_INITIATED_EDUPLACES and callback()
     * maps the id_token `school` claim to tenants.idp_school_id.
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
     * Handle the Keycloak callback.
     *
     * A universal route, so no tenant middleware runs: the signed state carries
     * the instance_code and is what makes the request non-forgeable.
     */
    public function callback(Request $request): RedirectResponse
    {
        // Laravel caches the controller instance on the Route, so it can
        // outlive one request under a long-running worker.
        $this->idpIdTokens = [];

        $state = (string) $request->query('state', '');

        // Read before verifySignedState() so the failure paths below also land
        // in the app that started the login. An unverifiable state yields false
        // and sends the browser to the website, which is the safe way to be
        // wrong: a forged state cannot aim callback() anywhere new.
        $this->nativeClient = $this->stateWantsNativeClient($state);

        $instanceCode = $this->verifySignedState($state);
        if ($instanceCode === null) {
            return $this->frontendError('invalid_state');
        }

        // Keycloak redirects back with `error` instead of a code when the login
        // is cancelled or refused upstream. There is nothing for Socialite to
        // exchange, so report it rather than let the exchange throw.
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

        // link_user_id is set by connectIdentity(): link to that account instead
        // of resolving one.
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
     * Run the Keycloak token exchange and resolve the Tenant.
     *
     * The tenant-scoped flow resolves before the exchange; the IdP-initiated
     * flow can only resolve after it, from the id_token `school` claim.
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

            // Re-checked after initiate(): a state signed while sso_enabled was
            // true stays valid, and callback() can be reached without calling
            // initiate() at all. The IdP-initiated branch runs the same check
            // once resolveTenantFromEduplacesClaim() has returned.
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

        // Psalm cannot correlate the two $idpInitiated checks to see that
        // exactly one branch assigns $tenant.
        /** @var Tenant $tenant */
        return [$tenant, $socialiteUser, $instanceCode];
    }

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
     * The raw id_token, or a redirect carrying id_token_invalid for a missing
     * or unverifiable token and email_not_verified for a tenant with
     * sso_require_email_verified set.
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
     * Resolve the LegacyUser for a verified identity, in order: sso_sub match,
     * bootstrapIdpTenant(), offerAccountClaim(), adoptDirectoryProvisionedUser(),
     * then email match.
     *
     * Returns a redirect carrying account_inactive, sub_collision or
     * account_link_required instead of a user.
     */
    protected function resolveCallbackUser(LaravelSocialiteUser $laravelSocialiteUser, Tenant $callbackTenant, string $instanceCode): LegacyUser|RedirectResponse
    {
        $sub = $laravelSocialiteUser->getId();
        $email = $laravelSocialiteUser->getEmail();

        $user = $this->ssoUserService->findBySub($sub);

        // rejectForeignSchool() compares against tenants.idp_school_id, which
        // the bootstrap login below is what sets.
        $provesSchool = $user !== null;

        if ($user === null) {
            // No LegacyUser holds an sso_sub yet, so this login takes over the
            // seeded tenant admin and triggers the roster import.
            $user = $this->bootstrapIdpTenant($laravelSocialiteUser, $callbackTenant, $instanceCode);

            // bootstrapIdpTenant() declined, so this login is checked against
            // tenants.idp_school_id like any other.
            $provesSchool = $user === null;
        }

        if ($provesSchool) {
            $foreign = $this->rejectForeignSchool($laravelSocialiteUser, $callbackTenant, $instanceCode);

            if ($foreign !== null) {
                return $foreign;
            }
        }

        if ($user === null && $callbackTenant->isMigratingToIdp()) {
            // On a migrating tenant the row for this identity may be one
            // SchoolImport created, with no password, while the pre-existing
            // local account sits unmatched beside it.
            $claim = $this->offerAccountClaim($laravelSocialiteUser, $callbackTenant, $instanceCode);

            if ($claim !== null) {
                return $claim;
            }
        }

        if ($user === null) {
            // A row SchoolImport or a webhook created before this first login.
            $user = $this->adoptDirectoryProvisionedUser($laravelSocialiteUser, $callbackTenant, $instanceCode);
        }

        if ($user === null && $this->usesIdpDirectory($callbackTenant)) {
            // provisionUser() below writes preferred_username and no realname.
            // Falls through to it when the directory cannot answer.
            $user = $this->reprovisionFromDirectory($laravelSocialiteUser, $callbackTenant, $instanceCode);
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
     * Bind an SSO identity to the account named by a link intent.
     *
     * The bearer JWT proves the aula account and sso_link_token proves the
     * provider identity; both must name the same user_id.
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
     * A Keycloak RP-initiated logout URL for a tenant with sso_force_logout set,
     * null otherwise so the frontend logs out locally.
     *
     * Ending the upstream IdP session is Keycloak's job: the identity provider's
     * "Logout URL" is configured as the IdP's end_session_endpoint, so Keycloak
     * chains the logout itself with a static post_logout_redirect_uri, its own
     * broker logout_response endpoint, that the IdP can whitelist.
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

        // Keycloak needs a live session to propagate the logout to the IdP, so
        // revokeKeycloakSession() runs only when no front-channel URL could be
        // built.
        if ($logoutUrl === null) {
            $this->revokeKeycloakSession($user?->sso_refresh_token);
        }

        return response()->json(['logout_url' => $logoutUrl]);
    }

    // =========================================================
    // Protected helpers
    // =========================================================

    /**
     * base64(json payload).'.'.hmac_sha256(payload).
     *
     * @param  int|null  $linkUserId  callback() links the provider identity to
     *                                this account instead of resolving one.
     *                                Safe to carry here because link() re-checks
     *                                it against the bearer token.
     * @param  bool  $nativeApp  callback() ends on the app's deep-link scheme.
     *                           The state is the only value that survives the
     *                           round trip through Keycloak.
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

    protected function verifySignedState(string $state): ?string
    {
        $code = $this->decodeSignedState($state)['instance_code'] ?? null;

        return is_string($code) ? $code : null;
    }

    protected function stateLinkUserId(string $state): ?int
    {
        $id = $this->decodeSignedState($state)['link_user_id'] ?? null;

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    protected function stateWantsNativeClient(string $state): bool
    {
        return ($this->decodeSignedState($state)['client'] ?? null) === self::CLIENT_APP;
    }

    protected function wantsNativeClient(Request $request): bool
    {
        return $request->query('client') === self::CLIENT_APP;
    }

    /**
     * The decoded state payload, or [] when it is missing, malformed or not
     * signed with app.key, so a rejected signature and an absent key read the
     * same to every caller.
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
     * Resolve the Tenant for an IdP-initiated login by matching the id_token
     * `school` claim against tenants.idp_school_id.
     *
     * @return Tenant|RedirectResponse a redirect carrying idp_school_missing,
     *                                 school_not_provisioned or sso_disabled on
     *                                 failure
     */
    protected function resolveTenantFromEduplacesClaim(LaravelSocialiteUser $socialiteUser): Tenant|RedirectResponse
    {
        // No tenant is resolved yet, so the claim is read under the name each
        // provider in IdpProviders configures for it.
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

        // The loop assigns only a non-empty string.
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
     * Claims of the upstream provider's id_token, read through Keycloak's
     * broker token endpoint.
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
     * Read a claim under the name this tenant's sso_provider configures for it.
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
     * Write the provider's own user id to users.idp_user_id.
     *
     * sso_sub holds the Keycloak subject, which Keycloak mints when it brokers a
     * provider. SchoolImport rows and webhook events carry the provider's user
     * id instead, so without this column nothing matches them to a user.
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

        // users.idp_user_id is unique. A second row holding this id means two
        // aula accounts claim one directory user.
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
     * Finish connectIdentity(): set tenants.idp_school_id from the claim and
     * hand back a link intent for the frontend to complete against the bearer
     * token. No JWT is issued, the admin already holds one.
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

    protected function frontendRedirectToSettings(string $linkToken, string $instanceCode): RedirectResponse
    {
        return redirect()->away($this->clientUrl('settings/idp-sync', [
            'sso_link' => $linkToken,
            'code' => $instanceCode,
        ]));
    }

    /**
     * Claim the seeded tenant admin for the first SSO login and import the
     * school.
     *
     * The admin row tenant creation seeded becomes this login's account rather
     * than a second admin beside it. Runs only while no LegacyUser has an
     * sso_sub, so it happens once per tenant.
     */
    protected function bootstrapIdpTenant(LaravelSocialiteUser $socialiteUser, Tenant $tenant, string $instanceCode): ?LegacyUser
    {
        if (! $this->usesIdpDirectory($tenant)) {
            return null;
        }

        if (LegacyUser::whereNotNull('sso_sub')->exists()) {
            return null;
        }

        // A tenant already using aula is connected through connectIdentity()
        // instead, where an admin proves the account. Bootstrapping would hand
        // that admin account to the first login to arrive.
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

        // Second guard, for a tenant nobody flagged with idp_migration_status.
        // Any user beyond admin1_username and admin2_username means the school
        // is in use. A set password is not the signal, since tenant creation can
        // pre-set one.
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

        // Set before dispatch, so idp_import_status is never absent while
        // ImportSchoolForTenant waits in the queue.
        $tenant->update([
            'idp_import_status' => SchoolImport::STATUS_PENDING,
            'idp_import_error' => null,
            'idp_import_started_at' => now(),
            'idp_import_finished_at' => null,
        ]);

        // Queued, not inline: a large school must not have to fit inside the
        // login request, and the frontend polls ImportStatusController.
        ImportSchoolForTenant::dispatch((string) $tenant->id);

        return $admin->fresh();
    }

    /**
     * Refuse a login whose `school` claim is not this tenant's idp_school_id.
     *
     * A tenant with idp_school_id still null is refused too: setting it is
     * bootstrapIdpTenant()'s job, which by here has already declined.
     *
     * Kept apart from learnSchoolId(), which writes the school it reads when the
     * tenant holds none. The shared part is claimedSchoolId().
     *
     * @return RedirectResponse|null null when the login may proceed
     */
    protected function rejectForeignSchool(LaravelSocialiteUser $socialiteUser, Tenant $tenant, string $instanceCode): ?RedirectResponse
    {
        // A tenant that syncs from no directory has no idp_school_id to check.
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

        $claims = $this->idpClaims($socialiteUser, $tenant);
        $schoolId = $this->claimedSchoolId($tenant, $claims, $instanceCode);

        if ($schoolId === null) {
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

    /**
     * The `school` claim, or null when the id_token carries none.
     *
     * Shared by learnSchoolId() and rejectForeignSchool() so the claim is read
     * and logged in one place, and says nothing about what either does with it.
     *
     * @param  array<string, mixed>|null  $claims
     */
    protected function claimedSchoolId(Tenant $tenant, ?array $claims, string $instanceCode): ?string
    {
        $schoolId = $this->idpClaim($claims, $tenant, 'school');

        if (! is_string($schoolId) || $schoolId === '') {
            Log::warning('SSO: login carried no school claim', [
                'tenant' => $instanceCode,
                'claim_keys' => is_array($claims) ? array_keys($claims) : null,
            ]);

            return null;
        }

        return $schoolId;
    }

    /**
     * Set tenants.idp_school_id from the `school` claim, so no operator has to
     * look the school UUID up and configure it by hand.
     *
     * A missing claim and a school another tenant already holds are reported
     * apart: the first is a provider configuration problem, the second is
     * usually a login to the wrong instance_code.
     *
     * @param  array<string, mixed>|null  $claims
     * @return string|null an error code, or null when idp_school_id is set
     */
    protected function learnSchoolId(Tenant $tenant, ?array $claims, string $instanceCode): ?string
    {
        $schoolId = $this->claimedSchoolId($tenant, $claims, $instanceCode);

        if ($schoolId === null) {
            return 'idp_school_missing';
        }

        if ($tenant->idp_school_id === $schoolId) {
            return null;
        }

        // tenants.idp_school_id is unique: one school, one tenant. Without this
        // check a login to the wrong instance_code moves a school across
        // tenants or hits the unique index.
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
     * Whether any LegacyUser exists beyond admin1_username and admin2_username.
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
     * The account matched on admin1_username, falling back to the lowest-id
     * account at UserLevel::Admin or above.
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
     * Move idp_user_id onto the account whose password was just proved and
     * delete the row SchoolImport had holding it.
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
            // A row with a password or an sso_sub is a real account, not an
            // import shell. Two accounts claiming one identity needs a human.
            Log::warning('SSO: refusing to move a provider identity off a real account', [
                'idp_user_id' => $personId,
                'existing_user_id' => $holder->id,
            ]);

            return response()->json(['success' => false, 'error' => 'idp_identity_taken'], 409);
        }

        DB::transaction(function () use ($fresh, $intent, $personId, $holder): void {
            if ($holder !== null) {
                // A SchoolImport row carries no content by construction, so
                // idp_user_id moves to the proved account and the row goes.
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
     * Ask a login on a migrating tenant whether it already has an aula account,
     * before any JWT is issued.
     *
     * Two rows can hold one person here: one SchoolImport created, carrying
     * idp_user_id and no password, and one pre-existing local account
     * MergeProposalBuilder left unmatched. A password proves which is which.
     * Issuing a JWT first would make dismissing the question the cheapest path
     * and leave the duplicate in place.
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

        // A row with an sso_sub or a password is a real account, so
        // adoptDirectoryProvisionedUser() and the email match handle it.
        if ($candidate !== null && ($candidate->sso_sub !== null || ! empty($candidate->pw))) {
            return null;
        }

        // No row at all is the common case, not an exception: before
        // MergeProposalApplier runs nothing carries idp_user_id, which is
        // exactly when every account is still a password one. Provisioning here
        // is what would create the duplicate.
        $token = $this->storeAccountClaimIntent($personId, $candidate?->id, $socialiteUser, $tenant);

        Log::info('SSO: asking a migrating school whether this person already has an account', [
            'tenant' => $instanceCode,
            'idp_user_id' => $personId,
        ]);

        return $this->frontendError('account_link_required', ['sso_link' => $token, 'claimable' => 1]);
    }

    /**
     * A link intent with no user_id, completed by any caller of link() that
     * proves an aula password, which is the assertion being made: that provider
     * identity belongs to this account. Possession of the account is still what
     * gets proved.
     *
     * $shellUserId is null when no row carries $personId yet, the normal state
     * before MergeProposalApplier runs.
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
     * The provider's token response, which only SocialiteOAuth2User carries.
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
     * Complete a claim raised by offerAccountClaim() for a login with no aula
     * account.
     *
     * Authenticated by sso_link_token alone: a new pupil has just proved an
     * identity at the provider and has no aula password to offer, and would
     * otherwise loop on the link prompt.
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
            // No row held this idp_user_id, so provision the one SchoolImport
            // would have made, rooms and role included.
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
     * provisionFromDirectory() for a login, stamping sso_sub on the row
     * SchoolImport::importUser() creates.
     *
     * Null when the `user` claim is absent or the directory holds no such id.
     */
    protected function reprovisionFromDirectory(LaravelSocialiteUser $socialiteUser, Tenant $tenant, string $instanceCode): ?LegacyUser
    {
        $personId = $this->idpClaim($this->idpClaims($socialiteUser, $tenant), $tenant, 'user');

        if ($personId === null) {
            return null;
        }

        $user = $this->provisionFromDirectory($personId);

        if ($user === null) {
            Log::warning('SSO: directory holds no user for this login', [
                'tenant' => $instanceCode,
                'idp_user_id' => $personId,
            ]);

            return null;
        }

        // email stays null, as it is on every row importUser() creates.
        $user->sso_sub = $socialiteUser->getId();
        $user->save();

        Log::info('SSO: rebuilt a deleted account from the directory', [
            'tenant' => $instanceCode,
            'user_id' => $user->id,
            'idp_user_id' => $personId,
        ]);

        return $user;
    }

    /**
     * Build the account for a directory user that aula holds no row for,
     * through SchoolImport::importUser() so the row matches what the roster
     * import would have created.
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

        if ($person === null) {
            return null;
        }

        return $this->schoolImport->importUser($tenant, $provider, $this->withRealName($directory, $person));
    }

    /**
     * Merge the group member record, the only payload carrying `name`.
     *
     * personOrUser() reads `/people` and `/users`, which return `pseudonym`
     * only, so uniqueUsername() and realname would both take the pseudonym.
     */
    protected function withRealName(IdentityDirectory $directory, IdpUser $person): IdpUser
    {
        if ($person->realName() !== null) {
            return $person;
        }

        foreach ($person->groupIds() as $groupId) {
            try {
                $group = $directory->group($groupId);
            } catch (DirectoryException $e) {
                Log::warning('SSO: cannot read a group for its member names', [
                    'idp_group_id' => $groupId,
                    'reason' => $e->reason,
                ]);

                continue;
            }

            foreach ($group?->members ?? [] as $member) {
                if ($member->id === $person->id && $member->realName() !== null) {
                    return $person->mergedWith($member);
                }
            }
        }

        return $person;
    }

    /**
     * Return the account already carrying this idp_user_id, stamping sso_sub on
     * it.
     *
     * idp_user_id is written by SchoolImport, by MergeProposalApplier, by
     * completeAccountClaim() or by connectIdentity(), each one an explicit
     * assertion that the provider identity belongs to the account, so the login
     * honours it rather than asking for proof again. A row that already has an
     * sso_sub is left alone: re-binding it would move the account to a different
     * identity at the provider.
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
            // The directory carries no address, so the callback is the first
            // source of one.
            $candidate->email = $socialiteUser->getEmail();
        }

        $candidate->save();

        return $candidate;
    }

    /**
     * Gates the Keycloak broker call, so a tenant on another IdP skips a lookup
     * that cannot succeed.
     */
    protected function usesIdpDirectory(Tenant $tenant): bool
    {
        return $tenant->usesIdpDirectory();
    }

    protected function stateSecret(): string
    {
        return config('app.key');
    }

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
     * The upstream IdP's id_token, read through Keycloak's broker token API.
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
     * Decode a JWT payload without verifying the signature. Null when the token
     * is missing, malformed, or the payload is not valid JSON.
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
        // The oauth-login page reads `code` into localStorage, which an
        // IdP-initiated launch needs because it starts with no instance context.
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
     * The website and the native apps are one frontend reached two ways, sharing
     * every route and differing only in origin, so both are built here.
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
     * Cache an account-link intent and return its opaque token, carrying
     * everything link() needs to stamp the row once the aula password is proved.
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
            // Carried so an account linked by password proof is matchable by
            // webhooks too, not only one provisioned in callback().
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
