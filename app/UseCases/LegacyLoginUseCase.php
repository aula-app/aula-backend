<?php

declare(strict_types=1);

namespace App\UseCases;

use App\Models\LegacyUser;
use App\Services\LegacyJwtService;
use App\Data\PostLoginData;

class LegacyLoginUseCase
{
    public function __construct(
        protected LegacyJwtService $jwtService,
    ) {
    }

    public function execute(PostLoginData $postLoginData): array
    {
        $user = LegacyUser::where('username', $postLoginData->username)->first();

        if ($user === null) {
            // TODO replace all these with HTTP statuses and/or typed
            return [
                'success' => false,
                'error'   => 'bad_credentials',
            ];
        }

        // SSO-linked users must authenticate via the IdP. A local password is bypass
        // surface — refuse the login so the local secret can never substitute for the
        // IdP session.
        if ($user->sso_sub !== null) {
            return [
                'success' => false,
                'error'   => 'use_sso',
            ];
        }

        // Check if user is active
        if (!$user->isActive()) {
            return [
                'success'     => true,
                'user_status' => $user->status,
                'user_id'     => $user->id,
                // not implemented
                'data'        => $this->getReactivationDate($user),
                'count'       => 1,
            ];
        }

        // Verify password
        if (!$user->checkPassword($postLoginData->password)) {
            return [
                'success' => false,
                'error'   => 'bad_credentials',
            ];
        }

        // Clear refresh token flag if set
        if ($user->needsRefresh()) {
            $user->clearRefreshToken();
        }

        // Generate JWT token
        $token = $this->jwtService->generateToken($user);

        return [
            'success' => true,
            'JWT' => $token,
        ];
    }

    /**
     * Get reactivation date for suspended users.
     * This matches the legacy getReactivationDate method.
     */
    protected function getReactivationDate(LegacyUser $user): ?string
    {
        if ($user->status !== UserStatus::Suspended->value) {
            return null;
        }

        // Check if there's a reactivation date stored
        // This would need to be implemented based on how reactivation dates
        // are stored in the legacy system (possibly in a separate table)
        return null;
    }
}
