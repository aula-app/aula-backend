<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\LegacyJwtVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Keycloak\KeycloakExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(SocialiteWasCalled::class, KeycloakExtendSocialite::class);

        // Transitional: the deployed frontend logs in against BE.v1 and holds
        // its HS512 JWT, so routes it already calls have to take both. Drop
        // this once the frontend uses /api/v2/oauth/token.
        Auth::viaRequest('passport_or_legacy_jwt', function (Request $request) {
            // Captured first: Passport's TokenGuard blanks the Authorization
            // header once it has run, so the fallback would see nothing.
            $bearer = $request->bearerToken();
            $legacy = app(LegacyJwtVerifier::class);

            // Passport reports an exception for every token it cannot parse, so
            // a v1 token would log a stack trace on each request.
            return $legacy->handles($bearer)
                ? $legacy->resolve($bearer)
                : Auth::guard('api')->user();
        });
    }
}
