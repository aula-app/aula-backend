<?php

// TODO delete after discussion

namespace App\Policies;

use App\Data\User\Requests\StoreUserData;
use App\Data\User\Requests\UpdateUserData;
use App\Models\LegacyUser;
use App\Enums\UserLevel;

class UserPolicy
{
    public function before(LegacyUser $user, string $ability): bool|null
    {
        $isAdmin = \in_array($user->userlevel, [
            UserLevel::Admin,
            UserLevel::TechAdmin,
        ]);
        return $isAdmin ? true : null;
    }


    public function show(LegacyUser $user, string $publicId): bool
    {
        return $user->hash_id === $publicId;
    }

    public function update(LegacyUser $user, UpdateUserData $updatee): bool
    {
        return $user->hash_id === $updatee->publicId;
    }

    public function index(LegacyUser $user): bool
    {
        return false;
    }

    public function store(LegacyUser $user, StoreUserData $storeUserData): bool
    {
        return false;
    }

    public function destroy(LegacyUser $user, string $publicId): bool
    {
        return false;
    }
}
