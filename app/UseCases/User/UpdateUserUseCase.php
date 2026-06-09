<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Data\UserData;
use App\Data\UserUpdateData;
use App\Models\LegacyUser;

class UpdateUserUseCase
{
    public function execute(string $hashId, UserUpdateData $updateUserData): UserData
    {
        /* TODO: DB::transaction */
        $legacyUser = LegacyUser::where('hash_id', $hashId)->firstOrFail();

        $legacyUser->displayname = $updateUserData->displayName;
        $legacyUser->realname = $updateUserData->realName;
        $legacyUser->username = $updateUserData->userName;
        $legacyUser->email = $updateUserData->email;
        $legacyUser->userlevel = $updateUserData->userLevel;
        $legacyUser->about_me = $updateUserData->aboutMe;
        $legacyUser->save();
        /* / DB::transaction */
        return UserData::from($legacyUser);
    }
}
