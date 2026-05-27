<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Enums\UserLevel;
use App\Models\LegacyUser;
use App\DTO\UserDTO;

class UpdateUserUseCase
{
    public static function execute(LegacyUser $legacyUser, UserDTO $userDTO): LegacyUser
    {
        $legacyUser->displayname = $userDTO->displayname;
        $legacyUser->realname = $userDTO->realname;
        $legacyUser->username = $userDTO->username;
        $legacyUser->email = $userDTO->email;
        // TODO patch vs put vs required
        $legacyUser->userlevel = $userDTO->userlevel;
        $legacyUser->about_me = $userDTO->about_me;
        $legacyUser->save();
        return $legacyUser;
    }
}

