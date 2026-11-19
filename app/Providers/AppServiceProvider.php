<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // patterns based on living data, not really enforced on DB level
        // no validation in references, e.g. publicId in RoomMembershipData
        Route::pattern('user', '[a-zA-Z0-9]{32}');
        Route::pattern('room', '[a-zA-Z0-9]{32}');
    }
}
