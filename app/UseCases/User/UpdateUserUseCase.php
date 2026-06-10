<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Data\UserModelData;
use App\Data\UserUpdateData;
use App\Models\LegacyUser;

class UpdateUserUseCase
{
    public function execute(string $hashId, UserUpdateData $userUpdateData): UserModelData
    {
        /* TODO: DB::transaction */
        $legacyUser = LegacyUser::where('hash_id', $hashId)->firstOrFail();

        $legacyUser->displayname = $userUpdateData->displayName;
        $legacyUser->realname = $userUpdateData->realName;
        $legacyUser->username = $userUpdateData->userName;
        $legacyUser->email = $userUpdateData->email;
        $legacyUser->userlevel = $userUpdateData->userLevel;
        $legacyUser->about_me = $userUpdateData->aboutMe;
        $legacyUser->save();
        /* / DB::transaction */
        return UserModelData::from($legacyUser);
    }
}
