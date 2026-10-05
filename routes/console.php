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

// Notification retries and H-1 reminders (Phase 2.4 - PRD 37, 61, 216)
Schedule::command('notifications:retry-failed')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('notifications:send-reminders')
    ->dailyAt('08:00')
    ->withoutOverlapping();

// Workflow runner delay processing (Phase 3.4 - PRD 62, 204.5)
Schedule::command('workflows:process-delays')
    ->everyMinute()
    ->withoutOverlapping();

// Temporary reservation hold expiration (Phase 4.2 - PRD 139, 210 point 4)
Schedule::command('bookings:expire-holds')
    ->everyMinute()
    ->withoutOverlapping();


