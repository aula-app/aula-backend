<?php

namespace App\Providers;

use App\Auth\LegacyJwtGuard;
use App\Services\LegacyJwtService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Keycloak\KeycloakExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LegacyJwtService::class, function ($app) {
            return new LegacyJwtService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::extend('legacy_jwt', function ($app, $name, array $config) {
            return new LegacyJwtGuard(
                $app->make(LegacyJwtService::class),
                $app['request']
            );
        });

        Event::listen(SocialiteWasCalled::class, KeycloakExtendSocialite::class);
    }
}
