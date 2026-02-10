<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LegacyUser;
use App\Services\LegacyJwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefreshTokenController extends Controller
{
    public function __construct(
        protected LegacyJwtService $jwtService
    ) {}

    /**
     * Refresh the JWT token.
     * Matches the legacy refresh_token.php behavior.
     */
    public function refresh(Request $request): JsonResponse
    {
        // Extract token from Authorization header
        $authHeader = $request->header('Authorization');
        $token = $this->jwtService->extractBearerToken($authHeader);

        if ($token === null) {
            return response()->json([
                'success' => false,
                'error' => 'Authorization header missing or invalid',
            ], 401);
        }

        // Validate token signature (ignore refresh flag for refresh endpoint)
        $validation = $this->jwtService->validateToken($token);

        if (!$validation['success']) {
            return response()->json([
                'success' => false,
                'error' => $validation['error'],
            ], 401);
        }

        $payload = $validation['payload'];

        // Get user from database to get fresh data
        $user = LegacyUser::find($payload->user_id);

        if ($user === null) {
            return response()->json([
                'success' => false,
                'error' => 'user_not_found',
            ], 401);
        }

        // Verify user is active
        if (!$user->isActive()) {
            return response()->json([
                'success' => false,
                'error' => 'user_not_active',
            ], 401);
        }

        // Verify user hash matches
        if ($user->hash_id !== $payload->user_hash) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_token',
            ], 401);
        }

        // Clear the refresh token flag
        $user->clearRefreshToken();

        // Generate new token with fresh user data
        $newToken = $this->jwtService->generateToken($user);

        return response()->json([
            'success' => true,
            'JWT' => $newToken,
        ]);
    }
}
