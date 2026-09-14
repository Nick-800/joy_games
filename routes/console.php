<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Poll Smart TVs every minute to detect rogue / standby drift.
Schedule::command('tvs:reconcile')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Auto-end prepaid sessions whose allocated time has elapsed.
Schedule::command('sessions:expire-prepaid')
    ->everyMinute()
    ->withoutOverlapping();
