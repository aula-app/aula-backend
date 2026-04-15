<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LegacyUser;
use App\Models\Tenant;
use App\Services\LegacyJwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    /**
     * Initiate SSO login flow.
     *
     * Returns a JSON response with the Keycloak redirect URL.
     * The frontend is responsible for navigating to it so that the
     * aula-instance-code header can be sent on the AJAX call.
     */
    public function initiate(): JsonResponse
    {
        $tenant = tenant();
        $idpHint = $tenant->sso_provider ?? null;

        $state = $this->buildSignedState($tenant->instance_code);

        $params = ['state' => $state];
        if ($idpHint) {
            $params['kc_idp_hint'] = $idpHint;
        }

        $url = Socialite::driver('keycloak')
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
        $username = $socialiteUser->getNickname() ?? $socialiteUser->getEmail();

        $user = new LegacyUser;
        $user->email = $socialiteUser->getEmail();
        $user->sso_sub = $socialiteUser->getId();
        $user->sso_provider = tenant()->sso_provider ?? null;
        $user->username = $username;
        $user->displayname = $socialiteUser->getName() ?? $username;
        $user->hash_id = md5($username . microtime(true) . rand(100, 10000000));
        $user->userlevel = 20; // default: User
        $user->status = 1;
        $user->save();

        $this->addToStandardRoom($user);

        return $user;
    }

    /**
     * Add a newly provisioned user to the standard room (type=1, the school room).
     * Mirrors legacy User::addUserToStandardRoom() logic.
     */
    protected function addToStandardRoom(LegacyUser $user): void
    {
        $room = DB::table('au_rooms')->where('type', 1)->first(['id', 'hash_id']);

        if ($room === null) {
            return;
        }

        // Insert into the room membership table
        DB::table('au_rel_rooms_users')->insertOrIgnore([
            'room_id'     => $room->id,
            'user_id'     => $user->id,
            'status'      => 1,
            'created'     => now(),
            'last_update' => now(),
            'updater_id'  => 0,
        ]);

        // Update the user's roles JSON to include the role for this room
        $roles = json_decode($user->roles ?? '[]', true) ?? [];
        $roles = array_values(array_filter($roles, fn($r) => ($r['room'] ?? null) !== $room->hash_id));
        $roles[] = ['role' => 20, 'room' => $room->hash_id];

        DB::table('au_users_basedata')
            ->where('id', $user->id)
            ->update(['roles' => json_encode($roles), 'last_update' => now()]);
    }

    protected function frontendRedirect(string $token): RedirectResponse
    {
        $frontendUrl = rtrim(config('app.frontend_url', '/'), '/');

        // Reuses the existing /oauth-login/:jwt_token route in the frontend
        return redirect("{$frontendUrl}/oauth-login/{$token}");
    }

    protected function frontendError(string $code): RedirectResponse
    {
        $frontendUrl = rtrim(config('app.frontend_url', '/'), '/');

        return redirect("{$frontendUrl}/login?sso_error={$code}");
    }
}
