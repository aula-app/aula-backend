<?php

declare(strict_types=1);

namespace App\Providers;

use App\Data\User\Requests\UpdateUserData;
use App\Enums\UserStatus;
use App\Models\LegacyUser;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Enums\Gates;

class AuthzServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('admin', function (LegacyUser $user) {
            return $user->isAdmin();
        });

        // Runs for *every* gate check in the application, not just the API v2
        // ones defined here. The Filament manager panel authenticates
        // AulaManagerUser on the `web` guard and reaches these callbacks
        // directly (filament/helpers.php invades Gate::callBeforeCallbacks),
        // so a LegacyUser type hint 500s every /manager route. Narrow inside
        // the closure instead, and fall through for anything else.
        Gate::before(function (mixed $user, string $ability): bool|null {
            if (!$user instanceof LegacyUser) {
                return null;
            }

            // @TODO: is this the right place for this check
            if ($user->status !== UserStatus::Active) {
                return false;
            }

            return $user->isAdmin() ? true : null;
        });

        Gate::define('user-self', function (LegacyUser $user, string $publicId) {
            return $user->hash_id === $publicId;
        });

        Gate::define(Gates::ListUsers, fn () => false);

        Gate::define(Gates::ShowUser, function (LegacyUser $user, string $publicId) {
            return $user->hash_id === $publicId;
        });

        Gate::define(Gates::CreateUser, fn () => false);

        // Takes the loaded row rather than a public id, because the rule is not
        // only "who" but "which fields": a user may edit their own record, but
        // never the fields that grant access. Admins bypass via Gate::before
        // and so never reach this closure.
        //
        // PUT makes the client send every field, so an unprivileged caller has
        // to echo back the current userlevel/status. Only an actual change is
        // an escalation attempt.
        Gate::define(Gates::UpdateUser, function (
            LegacyUser $updater,
            LegacyUser $subject,
            UpdateUserData $userUpdateData,
        ) {
            return $updater->hash_id === $subject->hash_id
                && $userUpdateData->userLevel === $subject->userlevel
                && $userUpdateData->status === $subject->status;
        });

        Gate::define(Gates::DeleteUser, fn () => false);

        // Admins only, via Gate::before: the proposal settles which account
        // each directory identity lands on.
        Gate::define(Gates::ListMergeProposals, fn () => false);
    }
}
