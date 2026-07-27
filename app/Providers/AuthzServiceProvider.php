<?php

namespace App\Providers;

use App\Models\LegacyUser;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

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

        Gate::define('index-users', function () {
            return false;
        });

        Gate::define('show-users', function (LegacyUser $user, string $publicId) {
            return $user->hash_id === $publicId;
        });

        Gate::define('store-users', function () {
            return false;
        });

        Gate::define('update-users', function (LegacyUser $user, string $publicId) {
            return $user->hash_id === $publicId;
        });

        Gate::define('destroy-users', function () {
            return false;
        });

    }
}
