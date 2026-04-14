<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LegacyUser;
use App\Models\Tenant;
use App\Services\LegacyJwtService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SsoController extends Controller
{
    public function __construct(
        protected LegacyJwtService $jwtService
    ) {}

    /**
     * Initiate SSO login flow.
     *
     * Tenant context is already set by the aula-instance-code header.
     * We encode the instance_code into a signed state so the callback
     * can identify the tenant without the header.
     */
    public function initiate(): RedirectResponse
    {
        $tenant = tenant();
        $idpHint = $tenant->sso_provider ?? null;

        $state = $this->buildSignedState($tenant->instance_code);

        $driver = Socialite::driver('keycloak')
            ->stateless()
            ->with(['state' => $state]);

        if ($idpHint) {
            $driver->with(['state' => $state, 'kc_idp_hint' => $idpHint]);
        }

        return $driver->redirect();
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

        $socialiteUser = Socialite::driver('keycloak')->stateless()->user();

        $user = LegacyUser::where('email', $socialiteUser->getEmail())->first()
            ?? LegacyUser::where('sso_sub', $socialiteUser->getId())->first();

        if ($user === null) {
            $user = $this->provisionUser($socialiteUser);
        }

        if (! $user->isActive()) {
            return $this->frontendError('account_inactive');
        }

        $token = $this->jwtService->generateToken($user);

        return $this->frontendRedirect($token);
    }

    /**
     * Build a signed state payload containing the instance_code.
     * Format: base64(json) . '.' . hmac_signature
     */
    protected function buildSignedState(string $instanceCode): string
    {
        $payload = base64_encode(json_encode([
            'instance_code' => $instanceCode,
            'nonce' => Str::random(16),
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
     * Create a new user from the SSO claims.
     */
    protected function provisionUser(mixed $socialiteUser): LegacyUser
    {
        $user = new LegacyUser;
        $user->email = $socialiteUser->getEmail();
        $user->sso_sub = $socialiteUser->getId();
        $user->username = $socialiteUser->getNickname() ?? $socialiteUser->getEmail();
        $user->displayname = $socialiteUser->getName() ?? $user->username;
        $user->userlevel = 20; // default: User
        $user->status = 1;
        $user->save();

        return $user;
    }

    protected function frontendRedirect(string $token): RedirectResponse
    {
        $frontendUrl = rtrim(config('app.frontend_url', '/'), '/');

        return redirect()->away("{$frontendUrl}?sso_token={$token}");
    }

    protected function frontendError(string $code): RedirectResponse
    {
        $frontendUrl = rtrim(config('app.frontend_url', '/'), '/');

        return redirect()->away("{$frontendUrl}?sso_error={$code}");
    }
}
