<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Data\User\DomainUserData;
use App\Data\User\Requests\StoreUserData;
use App\Enums\UserLevel;
use App\Models\LegacyUser;
use Illuminate\Support\Str;

class CreateUserUseCase
{
    public function execute(StoreUserData $userStoreData): DomainUserData
    {
        $legacyUser = new LegacyUser();
        $legacyUser->hash_id = Str::random(32);
        $legacyUser->displayname = $userStoreData->displayName;
        $legacyUser->realname = $userStoreData->realName;
        $legacyUser->username = $userStoreData->userName;
        $legacyUser->email = $userStoreData->email;
        $legacyUser->userlevel = $userStoreData->userLevel ?? UserLevel::Guest;
        $legacyUser->about_me = $userStoreData->aboutMe ?? '';
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
        // for unmanaged createdAt/created timestamp
        $legacyUser->refresh();
        return DomainUserData::from($legacyUser);
    }

    // rooms, users, rel_rooms_users
    // 1. Receive Room HashId
    // 2. Fetch User
    // 3. Update User.roles <- {room: hash_id, role: 20}
    // 4. Insert au_rel_rooms_users (room.id, user.id)
}
