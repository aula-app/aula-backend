<?php

namespace App\UseCases\User;

use App\Models\LegacyUser;
use App\Domain\Models\User;

class ShowUserUseCase
{
    public static function execute(string $id): User
    {
        $legacyUser = LegacyUser::findOrFail($id);
        return User::fromLegacy($legacyUser);
    }
}
