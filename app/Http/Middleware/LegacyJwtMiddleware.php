<?php

namespace App\Http\Middleware;

use App\Models\LegacyUser;
use App\Services\LegacyJwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LegacyJwtMiddleware
{
    public function __construct(
        protected LegacyJwtService $jwtService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Handle CORS preflight requests
        if ($request->isMethod('OPTIONS')) {
            return response('', 200);
        }

        // Extract token from Authorization header
        $authHeader = $request->header('Authorization');
        $token = $this->jwtService->extractBearerToken($authHeader);

        if ($token === null) {
            return $this->errorResponse('Authorization header missing or invalid', 401);
        }

        // Validate token signature
        $validation = $this->jwtService->validateToken($token);

        if (!$validation['success']) {
            return $this->errorResponse($validation['error'], 401);
        }

        $payload = $validation['payload'];

        // Verify user exists in database
        $user = LegacyUser::find($payload->user_id);

        if ($user === null) {
            return $this->errorResponse('user_not_found', 401);
        }

        // Verify user is active
        if (!$user->isActive()) {
            return $this->errorResponse('user_not_active', 401);
        }

        // Verify user hash matches (additional security check)
        if ($user->hash_id !== $payload->user_hash) {
            return $this->errorResponse('invalid_token', 401);
        }

        // Check refresh token flag
        if ($user->needsRefresh()) {
            return $this->errorResponse('refresh_token', 401);
        }

        // Attach JWT payload and user info to request
        $request->attributes->set('jwt_payload', $payload);
        $request->attributes->set('user_id', $payload->user_id);
        $request->attributes->set('user_level', $payload->user_level);
        $request->attributes->set('user_hash', $payload->user_hash);
        $request->attributes->set('roles', $payload->roles ?? []);
        $request->attributes->set('authenticated_user', $user);

        return $next($request);
    }

    /**
     * Return a JSON error response matching legacy format.
     */
    protected function errorResponse(string $error, int $statusCode): Response
    {
        return response()->json([
            'success' => false,
            'error' => $error,
        ], $statusCode);
    }
}
