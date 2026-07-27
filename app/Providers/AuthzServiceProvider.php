<?php

namespace App\Providers;

use App\Data\User\Requests\UpdateUserData;
use App\Enums\UserLevel;
use App\Models\LegacyUser;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthzServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('admin', function (LegacyUser $user) {
            return \in_array($user->userlevel, [
                UserLevel::Admin,
                UserLevel::TechAdmin,
            ]);
        });

        Gate::before(function (LegacyUser $user): bool|null {
            return $user->isAdmin() ? true : null;
        });

        Gate::define('user-self', function (LegacyUser $user, string $publicId) {
            return $user->hash_id === $publicId;
        });

        Gate::define('index-users', function () {
            return false;
        });

        Gate::define('show-users', function (LegacyUser $user, string $publicId) {
            return $user->hash_id === $publicId;
        });

        Gate::define('store-users', function () {
            return false;
        });

        // Takes the loaded row rather than a public id, because the rule is not
        // only "who" but "which fields": a user may edit their own record, but
        // never the fields that grant access. Admins bypass via Gate::before
        // and so never reach this closure.
        //
        // PUT makes the client send every field, so an unprivileged caller has
        // to echo back the current userlevel/status. Only an actual change is
        // an escalation attempt.
        Gate::define('update-users', function (
            LegacyUser $user,
            LegacyUser $subject,
            UpdateUserData $userUpdateData,
        ) {
            return $user->hash_id === $subject->hash_id
                && $userUpdateData->userLevel === $subject->userlevel
                && $userUpdateData->status === $subject->status;
        });

        Gate::define('destroy-users', function () {
            return false;
        });

    }
}
