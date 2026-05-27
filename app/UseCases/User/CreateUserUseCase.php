<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Enums\UserLevel;
use App\Models\LegacyUser;
use App\DTO\UserDTO;

class CreateUserUseCase
{
    public static function execute(UserDTO $userDTO): LegacyUser
    {
        // 1. Create LegacyUser
        // 2. RoomService->addUserToStandardRoom
        $legacyUser = LegacyUser::create();
        $legacyUser->displayname = $userDTO->displayname;
        $legacyUser->realname = $userDTO->realname;
        $legacyUser->username = $userDTO->username;
        $legacyUser->email = $userDTO->email;
        $legacyUser->userlevel = $userDTO->userlevel ?? UserLevel::Guest;
        $legacyUser->about_me = $userDTO->about_me ?? '';
        // TODO functionality from legacy model User->addUser, including but not limited to:
        // - check for username unique
        // - generate password
        // - add user to standard room
        // - send email
        $legacyUser->save();
        return new UserDTO($legacyUser);
    }

    // rooms, users, rel_rooms_users
    // 1. Receive Room HashId
    // 2. Fetch User
    // 3. Update User.roles <- {room: hash_id, role: 20}
    // 4. Insert au_rel_rooms_users (room.id, user.id)
}
