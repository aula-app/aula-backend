<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Enums\UserLevel;
use App\Enums\UserStatus;
use App\Models\LegacyUser;
use App\Domain\Models\User;

class CreateUserUseCase
{

    /**
     * Creates a (legacy) user
     * @param User $user
     * @return User
     * @psalm-suppress UndefinedMagicPropertyAssignment
     */
    public static function execute(User $user): User
    {
        $legacyUser = LegacyUser::create();
        $legacyUser->displayname = $user->displayName;
        $legacyUser->realname = $user->realName;
        $legacyUser->username = $user->userName;
        $legacyUser->email = $user->email;
        $legacyUser->userlevel = $user->userLevel ?? UserLevel::Guest;
        $legacyUser->about_me = $user->aboutMe ?? '';
        $legacyUser->status = $user->status->value ?? UserStatus::Inactive->value;
        // TODO functionality from legacy model User->addUser, including but not limited to:
        // - check for username unique
        //    -> here or at DB index (if index, check compat with v1)
        // - generate password
        //    -> in the usecase
        // - add user to standard room
        //    -> later
        // - send email
        //    -> usecase calls helper/interface for email
        // - change password
        //    -> persistence model
        $legacyUser->save();
        return User::fromLegacy($legacyUser);
    }

    // rooms, users, rel_rooms_users
    // 1. Receive Room HashId
    // 2. Fetch User
    // 3. Update User.roles <- {room: hash_id, role: 20}
    // 4. Insert au_rel_rooms_users (room.id, user.id)
}
