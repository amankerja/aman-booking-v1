<?php

namespace App\Providers;

use App\Domain\Audit\Listeners\AuditAuthEventListener;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Factory::guessFactoryNamesUsing(function (string $modelName): string {
            return 'Database\\Factories\\'.class_basename($modelName).'Factory';
        });

        Event::listen(
            Login::class,
            [AuditAuthEventListener::class, 'handleLogin']
        );

        Event::listen(
            Logout::class,
            [AuditAuthEventListener::class, 'handleLogout']
        );

        Event::listen(
            \App\Domain\Booking\Events\BookingCreated::class,
            [\App\Domain\Workflow\Listeners\WorkflowTriggerListener::class, 'handleBookingCreated']
        );

        Event::listen(
            \App\Domain\Booking\Events\BookingStatusChanged::class,
            [\App\Domain\Workflow\Listeners\WorkflowTriggerListener::class, 'handleBookingStatusChanged']
        );
    }
}
