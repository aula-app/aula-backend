<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Data\UserData;
use App\Data\UserStoreData;
use Spatie\LaravelData\Optional;

use App\Enums\UserLevel;
use App\Enums\UserStatus;
use App\Models\LegacyUser;

class CreateUserUseCase
{

    /**
     * Creates a (legacy) user
     * @param UserStoreData $userStoreData
     * @return UserData
     * @psalm-suppress UndefinedMagicPropertyAssignment
     */
    public function execute(UserStoreData $userStoreData): UserData
    {
        $legacyUser = new LegacyUser();
        $legacyUser->displayname = $userStoreData->displayName;
        $legacyUser->realname = $userStoreData->realName;
        $legacyUser->username = $userStoreData->userName;
        $legacyUser->email = $userStoreData->email instanceof \Spatie\LaravelData\Optional ? null : $userStoreData->email;
        $legacyUser->userlevel = $userStoreData->userLevel instanceof \Spatie\LaravelData\Optional ? UserLevel::Guest : $userStoreData->userLevel;
        $legacyUser->about_me = $userStoreData->aboutMe instanceof \Spatie\LaravelData\Optional ? '' : $userStoreData->aboutMe;
        $legacyUser->status = $userStoreData->status->value;
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
        return UserData::from($legacyUser);
    }

    // rooms, users, rel_rooms_users
    // 1. Receive Room HashId
    // 2. Fetch User
    // 3. Update User.roles <- {room: hash_id, role: 20}
    // 4. Insert au_rel_rooms_users (room.id, user.id)
}
