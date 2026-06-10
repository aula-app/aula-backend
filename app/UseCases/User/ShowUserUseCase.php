<?php

namespace App\UseCases\User;

use App\Data\UserData;
use App\Models\LegacyUser;

class ShowUserUseCase
{
    public function execute(string $hashId): UserData
    {
        $legacyUser = LegacyUser::where('hash_id', $hashId)->firstOrFail();
        return UserData::from($legacyUser);
    }
}
