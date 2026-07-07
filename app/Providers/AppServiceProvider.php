<?php

namespace App\Providers;

use App\Enums\UserLevel;
use App\Models\LegacyUser;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::pattern('user', '[a-zA-Z0-9]{32}');

        Gate::define('admin', function (LegacyUser $user) {
            return \in_array($user->userlevel, [
                UserLevel::Admin,
                UserLevel::TechAdmin,
            ]);
        });
    }
}
