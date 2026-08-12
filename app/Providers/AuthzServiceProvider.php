<?php

namespace App\Providers;

use App\Data\User\Requests\UpdateUserData;
use App\Models\LegacyUser;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Enums\Gates;

class AuthzServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // TODO(v1 divergence): isAdmin() covers Admin *and* TechAdmin, but
        // legacy's Permissions.php grants "admin" in ~100 rule entries and
        // "tech_admin" in only ~20 -- userlevel is not a hierarchy there. As
        // long as Gate::before below bypasses everything for both, TechAdmin
        // gains abilities in v2 that it does not have in v1. Needs a product
        // decision before this covers more than the User resource.
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
            LegacyUser $user,
            LegacyUser $subject,
            UpdateUserData $userUpdateData,
        ) {
            return $user->hash_id === $subject->hash_id
                && $userUpdateData->userLevel === $subject->userlevel
                && $userUpdateData->status === $subject->status;
        });

        Gate::define(Gates::DeleteUser, fn () => false);

        // TODO
        Gate::define(Gates::ListRooms,  fn () => false);
        Gate::define(Gates::CreateRoom, fn () => false);
        Gate::define(Gates::DeleteRoom, fn () => false);
        Gate::define(Gates::ShowRoom,   fn () => false);

        Gate::define(Gates::PatchRoomMembership, fn () => false);

        Gate::define(Gates::ListRoomMember,   fn () => false);
        Gate::define(Gates::CreateRoomMember, fn () => false);
        Gate::define(Gates::DeleteRoomMember, fn () => false);
        Gate::define(Gates::ShowRoomMember,   fn () => false);
    }
}
