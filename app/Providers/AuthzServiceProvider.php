<?php

namespace App\Providers;

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
