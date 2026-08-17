<?php

namespace App\Http\Controllers\Auth;

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
                'error' => 'access_denied',
                'error_description'   => 'Tenant requires using Single Sign-On functionality',
            ], 400);
        }

        $psrRequest = $this->psrHttpFactory->createRequest($request);
        $psrResponse = $this->psrHttpFactory->createResponse(response()->make());
        return $this->issueToken($psrRequest, $psrResponse);
    }
}
