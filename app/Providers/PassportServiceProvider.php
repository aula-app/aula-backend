<?php

namespace App\Providers;

use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

class PassportServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Passport::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::group([
            'as' => 'passport.',
            'middleware' => [
                'universal',
                InitializeTenancyByRequestData::class,
            ],
            'prefix' => config('passport.path', '/api/v2/oauth'),
            'namespace' => 'Laravel\Passport\Http\Controllers',
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../../vendor/laravel/passport/src/../routes/web.php');
        });

        // For now, we'll have only single internal Password Client to authenticate with username+password
        Passport::enablePasswordGrant();

        // @TODO: nikola - after testing extend to 30-60 mins or so, to cover usual session length
        Passport::tokensExpireIn(CarbonInterval::minutes(1));
        Passport::refreshTokensExpireIn(CarbonInterval::days(60));
        /* Passport::personalAccessTokensExpireIn(CarbonInterval::months(6)); */
    }
}
