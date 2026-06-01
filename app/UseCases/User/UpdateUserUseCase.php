<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Models\LegacyUser;
// use App\DTO\UserDTO;
use App\Domain\Models\User;

class UpdateUserUseCase
{
    public static function execute(string $id, User $user): User
    {
        /* DB::transaction */
        $legacyUser = LegacyUser::findOrFail($id);

        $legacyUser->displayname = $user->displayName;
        $legacyUser->realname = $user->realName;
        $legacyUser->username = $user->userName;
        $legacyUser->email = $user->email;
        // TODO patch vs put vs required
        $legacyUser->userlevel = $user->userLevel;
        $legacyUser->about_me = $user->aboutMe;
        $legacyUser->save();
        /* DB::transaction */
        return User::fromLegacy($legacyUser);
    }
}
