<?php

namespace App\Providers;

use App\Models\Manager\CentralClient;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

class PassportServiceProvider extends ServiceProvider
{
    /**
     * Register Passport services.
     */
    public function register(): void
    {
        // The routes are manually setup in boot() override below, due
        // to the need to resolve the Tenant before Passport is used for authN
        Passport::ignoreRoutes();
    }

    /**
     * Bootstrap the Passport related services.
     */
    public function boot(): void
    {
        Passport::useClientModel(CentralClient::class);
        Route::group([
            'as' => 'passport.',
            'middleware' => [
                InitializeTenancyByRequestData::class,
            ],
            'prefix' => config('passport.path', '/api/v2/oauth'),
            'namespace' => 'Laravel\Passport\Http\Controllers',
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../../vendor/laravel/passport/src/../routes/web.php');
            Route::post('/token', [
                'uses' => '\App\Http\Controllers\Auth\SsoAwareAccessTokenController@login',
                'as' => 'token',
                'middleware' => 'throttle',
            ]);
        });

        // For now, we'll have only single central Password Client using which
        // all Users of all Tenants could authenticate with username+password
        Passport::enablePasswordGrant();

        Passport::tokensExpireIn(CarbonInterval::hours(4));
        Passport::refreshTokensExpireIn(CarbonInterval::days(60));
    }
}
