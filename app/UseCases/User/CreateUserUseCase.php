<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Data\User\Requests\StoreUserData;
use App\Data\User\DomainUserData;
use App\Enums\Gates;
use App\Enums\UserLevel;
use App\Models\LegacyUser;
use App\Services\UserService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateUserUseCase
{
    public function __construct(
        protected UserService $userService,
    ) {
    }

    public function execute(StoreUserData $userStoreData): DomainUserData
    {
        Gate::authorize(Gates::CreateUser);

        $legacyUser = new LegacyUser();

        $legacyUser->hash_id = Str::random(32);

        // uniqueness of username + email handled by StoreUserData validation
        $legacyUser->username = $userStoreData->userName;
        $legacyUser->email = $userStoreData->email;

        $legacyUser->displayname = $userStoreData->displayName;
        $legacyUser->realname = $userStoreData->realName;
        $legacyUser->userlevel = $userStoreData->userLevel ?? UserLevel::Guest;
        $legacyUser->about_me = $userStoreData->aboutMe ?? '';
        $legacyUser->status = $userStoreData->status->value;

        $legacyUser->temp_pw = $legacyUser->email
            ? $this->userService->generateTemporaryPassword()
            : '';

        $legacyUser->pw = \password_hash(
            $this->userService->generateFirstPassword(),
            PASSWORD_DEFAULT
        );
        $legacyUser->pw_changed = 0;

        // TODO functionality from legacy model User->addUser, including but not limited to:
        // - add user to standard room
        //    -> later
        // - change password
        //    -> persistence model
        $legacyUser->save();
        // for unmanaged createdAt/created timestamp
        $legacyUser->refresh();

        if ($legacyUser->email) {
            $this->userService->sendWelcomePassword(
                $legacyUser->email,
                $legacyUser
            );
        }

        return DomainUserData::from($legacyUser);
    }

    // rooms, users, rel_rooms_users
    // 1. Receive Room HashId
    // 2. Fetch User
    // 3. Update User.roles <- {room: hash_id, role: 20}
    // 4. Insert au_rel_rooms_users (room.id, user.id)
}
