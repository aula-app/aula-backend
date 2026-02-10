<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LegacyUser;
use App\Services\LegacyJwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegacyLoginController extends Controller
{
    public function __construct(
        protected LegacyJwtService $jwtService
    ) {}

    /**
     * Handle a login request.
     * Matches the legacy login.php behavior.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $username = $request->input('username');
        $password = $request->input('password');

        // Find user by username
        $user = LegacyUser::where('username', $username)->first();

        if ($user === null) {
            return response()->json([
                'success' => false,
                'error_code' => 2,
            ]);
        }

        // Check if user is active
        if (!$user->isActive()) {
            return response()->json([
                'success' => true,
                'error_code' => 2,
                'user_status' => $user->status,
                'user_id' => $user->id,
                'data' => $this->getReactivationDate($user),
                'count' => 1,
            ]);
        }

        // Verify password
        if (!$user->checkPassword($password)) {
            return response()->json([
                'success' => false,
                'error_code' => 2,
            ]);
        }

        // Clear refresh token flag if set
        if ($user->needsRefresh()) {
            $user->clearRefreshToken();
        }

        // Generate JWT token
        $token = $this->jwtService->generateToken($user);

        return response()->json([
            'success' => true,
            'JWT' => $token,
        ]);
    }

    /**
     * Get reactivation date for suspended users.
     * This matches the legacy getReactivationDate method.
     */
    protected function getReactivationDate(LegacyUser $user): ?string
    {
        if ($user->status !== LegacyUser::STATUS_SUSPENDED) {
            return null;
        }

        // Check if there's a reactivation date stored
        // This would need to be implemented based on how reactivation dates
        // are stored in the legacy system (possibly in a separate table)
        return null;
    }
}
