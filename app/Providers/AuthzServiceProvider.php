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

        Gate::define('update-users', function (LegacyUser $user, string $publicId) {
            return $user->hash_id === $publicId;
        });

        Gate::define('destroy-users', function () {
            return false;
        });

    }
}
