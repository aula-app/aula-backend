<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        web: __DIR__.'/../routes/web.php',
        // universal API
        api: __DIR__.'/../routes/api.universal.php',
        apiPrefix: '',
        // per-instance (multi-tenant) API is loaded using TenancyServiceProvider.php
        //   basically, routes/tenant/api/v2/aula.php
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
