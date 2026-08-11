<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::pattern('user', '[a-zA-Z0-9]{32}');
    }
}
