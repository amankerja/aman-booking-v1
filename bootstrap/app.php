<?php

use App\Domain\Subscription\Commands\UpdateSubscriptionStatusesCommand;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        UpdateSubscriptionStatusesCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            ResolveTenant::class,
            EnsureSubscriptionActive::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
            'api/*',
        ]);

        $middleware->alias([
            'subscription.active' => EnsureSubscriptionActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
