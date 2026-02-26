<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run every Monday at 08:00. The CSV is also saved to storage/app/statistics/ as a fallback.
// Production cron: * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('statistics:send-report')->weeklyOn(1, '08:00');
