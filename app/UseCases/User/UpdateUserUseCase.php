<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Data\User\DomainUserData;
use App\Data\User\Requests\UpdateUserData;
use App\Enums\Gates;
use App\Models\LegacyUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;

class UpdateUserUseCase
{
    public function execute(string $hashId, UpdateUserData $userUpdateData): DomainUserData
    {
        /* TODO: DB::transaction */
        // Loaded before the check: the rule depends on the row's current
        // userlevel/status, not just on the id.
        $legacyUser = LegacyUser::where('hash_id', $hashId)->sole();

        Gate::authorize(Gates::UpdateUser, [$legacyUser, $userUpdateData]);

        // check for collisions of email and username
        if (LegacyUser::whereNot('hash_id', $hashId)
            ->where(function (Builder $query) use ($userUpdateData) {
                $query->where('username', $userUpdateData->userName);
                if ($userUpdateData->email) {
                    $query->orWhere('email', $userUpdateData->email);
                }
            })
            ->exists()) {
            // TODO i18n?
            // TODO what to do here for security: leaking, timing, enumeration?
            abort(400, 'Pick different e-mail and/or username');
        }

        $legacyUser->displayname = $userUpdateData->displayName;
        $legacyUser->realname = $userUpdateData->realName;
        $legacyUser->username = $userUpdateData->userName;
        $legacyUser->email = $userUpdateData->email;
        $legacyUser->userlevel = $userUpdateData->userLevel;
        $legacyUser->status = $userUpdateData->status;
        $legacyUser->about_me = $userUpdateData->aboutMe;

        $legacyUser->updater_id = auth()->id();

        $legacyUser->save();
        /* / DB::transaction */
        // for unmanaged last_update/updatedAt timestamp
        $legacyUser->refresh();
        return DomainUserData::from($legacyUser);
    }
}
