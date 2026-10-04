<?php

use App\Domain\Subscription\Jobs\UpdateSubscriptionStatusesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new UpdateSubscriptionStatusesJob)->daily();

// Shared hosting / cPanel queue runner (processes queued jobs for up to 50 seconds each minute)
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();
