<?php

namespace App\UseCases\User;

use App\Models\LegacyUser;

class ShowUserUseCase
{
    public static function execute(string $id): LegacyUser
    {
        return LegacyUser::findOrFail($id);
    }
}
