<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Models\LegacyUser;
use App\DTO\UserDTO;

class UpdateUserUseCase
{
    public static function execute(string $id, UserDTO $userDTO): LegacyUser
    {
        /* DB::transaction */
        $legacyUser = LegacyUser::findOrFail($id);

        $legacyUser->displayname = $userDTO->displayname;
        $legacyUser->realname = $userDTO->realname;
        $legacyUser->username = $userDTO->username;
        $legacyUser->email = $userDTO->email;
        // TODO patch vs put vs required
        $legacyUser->userlevel = $userDTO->userlevel;
        $legacyUser->about_me = $userDTO->about_me;
        $legacyUser->save();
        /* DB::transaction */
        return $legacyUser;
    }
}
