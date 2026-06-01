<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Enums\UserLevel;
use App\Models\LegacyUser;
// use App\DTO\UserDTO;
use App\Domain\Models\User;

class CreateUserUseCase
{
    public static function execute(User $user): User
    {
        // 1. Create LegacyUser
        // 2. RoomService->addUserToStandardRoom
        $legacyUser = LegacyUser::create();
        $legacyUser->displayname = $user->displayname;
        $legacyUser->realname = $user->realname;
        $legacyUser->username = $user->username;
        $legacyUser->email = $user->email;
        $legacyUser->userlevel = $user->userlevel ?? UserLevel::Guest;
        $legacyUser->about_me = $user->about_me ?? '';
        // TODO functionality from legacy model User->addUser, including but not limited to:
        // - check for username unique
        // - generate password
        // - add user to standard room
        // - send email
        $legacyUser->save();
        return User::fromLegacy($legacyUser);
    }

    // rooms, users, rel_rooms_users
    // 1. Receive Room HashId
    // 2. Fetch User
    // 3. Update User.roles <- {room: hash_id, role: 20}
    // 4. Insert au_rel_rooms_users (room.id, user.id)
}
