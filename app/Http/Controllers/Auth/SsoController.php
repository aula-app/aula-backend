<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LegacyUser;
use App\Services\LegacyJwtService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Keycloak\Provider as KeycloakProvider;

class SsoController extends Controller
{
    public function __construct(
        protected LegacyJwtService $jwtService
    ) {}

    /**
     * Initiate SSO login flow.
     * Redirects the browser to Keycloak with the correct kc_idp_hint for the tenant.
     */
    public function initiate(): RedirectResponse
    {
        $tenant = tenant();
        $idpHint = $tenant->sso_provider ?? null;

        $redirect = Socialite::driver('keycloak');

        if ($idpHint) {
            $redirect->with(['kc_idp_hint' => $idpHint]);
        }

        return $redirect->redirect();
    }

    /**
     * Handle the SSO callback from Keycloak.
     * Exchanges the code for a token, looks up or creates the user, issues a JWT.
     */
    public function callback(Request $request): RedirectResponse
    {
        $socialiteUser = Socialite::driver('keycloak')->user();

        $email = $socialiteUser->getEmail();
        $externalId = $socialiteUser->getId();

        $user = LegacyUser::where('email', $email)->first()
            ?? LegacyUser::where('sso_sub', $externalId)->first();

        if ($user === null) {
            $user = $this->provisionUser($socialiteUser);
        }

        if (! $user->isActive()) {
            return $this->frontendRedirect('error', 'account_inactive');
        }

        $token = $this->jwtService->generateToken($user);

        return $this->frontendRedirect('token', $token);
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

    /**
     * Redirect to the frontend with a result parameter.
     */
    protected function frontendRedirect(string $key, string $value): RedirectResponse
    {
        $frontendUrl = config('app.frontend_url', '/');

        return redirect()->away("{$frontendUrl}?sso_{$key}={$value}");
    }
}
