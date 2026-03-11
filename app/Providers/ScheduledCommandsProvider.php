<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;

class ScheduledCommandsProvider extends ServiceProvider
{
    /**
     * Bootstrap any commands that are running on a schedule.
     */
    public function boot(): void
    {
        // Run on cron. The CSV is also saved to storage/app/statistics/ as a fallback.
        // See Dockerfile for the cron that is running the schedule.
        // Use envfile to override the default schedule (every Wed 07AM)
        Schedule::command('statistics:send-report')
            ->cron(config('statistics.report_schedule'));
    }
}
