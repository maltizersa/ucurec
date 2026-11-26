<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

/*
| Daily backup of the database and documents. On the server, run the Laravel
| scheduler every minute (Windows Task Scheduler or cron), see docs/DEPLOYMENT.md:
|   php artisan schedule:run
*/
Schedule::command('ucurec:backup')
    ->dailyAt(config('ucurec.backup.time', '01:00'))
    ->withoutOverlapping()
    ->onFailure(fn () => Log::error('UCUREC scheduled backup failed.'));
