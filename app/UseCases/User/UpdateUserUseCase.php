<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Data\User\DomainUserData;
use App\Data\User\Requests\UpdateUserData;
use App\Models\LegacyUser;

class UpdateUserUseCase
{
    public function execute(string $hashId, UpdateUserData $userUpdateData): DomainUserData
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
        // for unmanaged last_update/updatedAt timestamp
        $legacyUser->refresh();
        return DomainUserData::from($legacyUser);
    }
}
