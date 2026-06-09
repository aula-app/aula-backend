<?php

namespace App\UseCases\User;

use App\Data\UserData;
use App\Models\LegacyUser;

class ShowUserUseCase
{
    public static function execute(string $id): UserData
    {
        $legacyUser = LegacyUser::findOrFail($id);
        return UserData::from($legacyUser);
    }
}
