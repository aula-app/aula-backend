<?php

namespace App\Http\Middleware;

use App\Enums\UserLevel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LegacyUserRequireAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $authenticated_user = $request->attributes->get('authenticated_user');
        if (!$authenticated_user) {
            abort(401);
        }
        if ($authenticated_user->userlevel !== UserLevel::TechAdmin) {
            abort(403);
        }
        return $next($request);
    }
}
