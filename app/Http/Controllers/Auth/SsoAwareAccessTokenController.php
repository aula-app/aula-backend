<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Models\LegacyUser;
use Http\Discovery\Psr17Factory;
use Illuminate\Http\Request;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\ConvertsPsrResponses;
use League\OAuth2\Server\AuthorizationServer;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Response;

class SsoAwareAccessTokenController extends AccessTokenController
{
    use ConvertsPsrResponses;

    private PsrHttpFactory $psrHttpFactory;

    public function __construct(
        // used in parent class, needs to be injected here
        protected AuthorizationServer $server
    ) {
        $this->psrHttpFactory = new PsrHttpFactory(new Psr17Factory());
    }

    /**
     * Handle a login request.
     * Matches the legacy login.php behavior.
     */
    public function login(Request $request): Response
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $username = $request->input('username');
        $password = $request->input('password');

        // Tenants flagged sso_required reject issuing OAuth tokens directly for
        // everyone, regardless of whether the specific user has finished SSO linking yet.
        /** @var bool|null $isSsoRequired */
        $isSsoRequired = tenant('sso_required');
        if ($isSsoRequired) {
            return response()->json([
                'success' => false,
                'error'   => 'tenant_requires_sso',
            ], 400);
        }

        if ($request->grant_type === 'password') {

            // Find user by username
            /** @var LegacyUser|null $user */
            $user = LegacyUser::where('username', $username)->first();

            if ($user === null) {
                return response()->json([
                    'success' => false,
                    'error'   => 'bad_credentials',
                ], 400);
            }

            // SSO-linked users must authenticate via the IdP. A local password is bypass
            // surface — refuse the login so the local secret can never substitute for the
            // IdP session.
            if ($user->sso_sub !== null) {
                return response()->json([
                    'success' => false,
                    'error'   => 'use_sso',
                ], 400);
            }

            // Check if user is active
            if (!$user->isActive()) {
                return response()->json([
                    'error' => 'user_not_active',
                    'user_status' => $user->status,
                    /* 'reactivation_date' => $this->getReactivationDate($user), */
                ], 400);
            }
        } elseif ($request->grant_type === 'refresh_token') {
        }

        $psrRequest = $this->psrHttpFactory->createRequest($request);
        $psrResponse = $this->psrHttpFactory->createResponse(response()->make());
        return $this->issueToken($psrRequest, $psrResponse);
    }

    /**
     * Get reactivation date for suspended users.
     * This matches the legacy getReactivationDate method.
     */
    protected function getReactivationDate(LegacyUser $user): ?string
    {
        if ($user->status !== UserStatus::Suspended) {
            return null;
        }

        // Check if there's a reactivation date stored
        // This would need to be implemented based on how reactivation dates
        // are stored in the legacy system (possibly in a separate table)
        return null;
    }
}
