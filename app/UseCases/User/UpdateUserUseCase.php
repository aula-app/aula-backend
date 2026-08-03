<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Data\User\DomainUserData;
use App\Data\User\Requests\UpdateUserData;
use App\Enums\Gates;
use App\Models\LegacyUser;
use Illuminate\Support\Facades\Gate;

class UpdateUserUseCase
{
    public function execute(string $hashId, UpdateUserData $userUpdateData): DomainUserData
    {
        /* TODO: DB::transaction */
        // Loaded before the check: the rule depends on the row's current
        // userlevel/status, not just on the id.
        $legacyUser = LegacyUser::where('hash_id', $hashId)->firstOrFail();

        Gate::authorize(Gates::UpdateUser, [$legacyUser, $userUpdateData]);

        $legacyUser->displayname = $userUpdateData->displayName;
        $legacyUser->realname = $userUpdateData->realName;
        $legacyUser->username = $userUpdateData->userName;
        $legacyUser->email = $userUpdateData->email;
        $legacyUser->userlevel = $userUpdateData->userLevel;
        $legacyUser->status = $userUpdateData->status;
        $legacyUser->about_me = $userUpdateData->aboutMe;
        $legacyUser->save();
        /* / DB::transaction */
        // for unmanaged last_update/updatedAt timestamp
        $legacyUser->refresh();
        return DomainUserData::from($legacyUser);
    }
}
