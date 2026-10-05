<?php

use App\Domain\Business\Models\Business;
use App\Domain\Identity\Controllers\AuthenticatedSessionController;
use App\Domain\Identity\Controllers\NewPasswordController;
use App\Domain\Identity\Controllers\PasswordResetLinkController;
use App\Domain\Identity\Controllers\RegisteredUserController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Owner\AuditLogController;
use App\Http\Controllers\Owner\BookingController;
use App\Http\Controllers\Owner\BookingFormController;
use App\Http\Controllers\Owner\BookingStatusController;
use App\Http\Controllers\Owner\BusinessHoursController;
use App\Http\Controllers\Owner\BusinessProfileController;
use App\Http\Controllers\Owner\CalendarExceptionController;
use App\Http\Controllers\Owner\CustomerController;
use App\Http\Controllers\Owner\DashboardController as OwnerDashboardController;
use App\Http\Controllers\Owner\MemberController;
use App\Http\Controllers\Owner\NotificationController;
use App\Http\Controllers\Owner\OnboardingController;
use App\Http\Controllers\Owner\ResourceController;
use App\Http\Controllers\Owner\ResourceGroupController;
use App\Http\Controllers\Owner\ServiceCategoryController;
use App\Http\Controllers\Owner\ServiceController;
use App\Http\Controllers\Owner\TimeBlockController;
use App\Http\Controllers\Owner\WorkflowController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public Root
Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

// Authenticated Routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Owner Workspace Routes (/app/*)
    Route::prefix('app')->name('owner.')->group(function () {
        Route::get('/dashboard', [OwnerDashboardController::class, 'index'])->name('dashboard');

        // Onboarding Wizard (PRD 48, 49, 168-170, 192)
        Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding');
        Route::post('/onboarding/step-1', [OnboardingController::class, 'saveProfile'])->name('onboarding.step1');
        Route::post('/onboarding/install-template', [OnboardingController::class, 'installTemplate'])->name('onboarding.install-template');
        Route::post('/onboarding/update-services', [OnboardingController::class, 'updateServices'])->name('onboarding.update-services');
        Route::post('/onboarding/update-resources', [OnboardingController::class, 'updateResources'])->name('onboarding.update-resources');
        Route::post('/onboarding/update-schedule', [OnboardingController::class, 'updateSchedule'])->name('onboarding.update-schedule');
        Route::post('/onboarding/update-workflow', [OnboardingController::class, 'updateWorkflow'])->name('onboarding.update-workflow');
        Route::post('/onboarding/publish', [OnboardingController::class, 'publish'])->name('onboarding.publish');
        Route::post('/onboarding/reset', [OnboardingController::class, 'reset'])->name('onboarding.reset');

        // Booking Module (PRD 41, 42, 67, 157, 158, 204.4)
        Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
        Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
        Route::get('/bookings/slots', [BookingController::class, 'slots'])->name('bookings.slots');
        Route::get('/bookings/feed', [BookingController::class, 'feed'])->name('bookings.feed');
        Route::get('/bookings/{id}', [BookingController::class, 'show'])->name('bookings.show');
        Route::post('/bookings/{id}/status', [BookingController::class, 'transitionStatus'])->name('bookings.status');
        Route::post('/bookings/{id}/reschedule', [BookingController::class, 'reschedule'])->name('bookings.reschedule');

        Route::get('/members', [MemberController::class, 'index'])->name('members.index');
        Route::post('/members', [MemberController::class, 'store'])->name('members.store');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/_styleguide', function () {
            return Inertia::render('Owner/Styleguide');
        })->name('styleguide');

        // Business Profile, Rules & Policies
        Route::get('/settings/business', [BusinessProfileController::class, 'index'])->name('settings.business');
        Route::put('/settings/business', [BusinessProfileController::class, 'update'])->name('settings.business.update');
        Route::post('/settings/business/logo', [BusinessProfileController::class, 'uploadLogo'])->name('settings.business.logo');
        Route::delete('/settings/business/logo', [BusinessProfileController::class, 'removeLogo'])->name('settings.business.logo.remove');

        // Operating Hours & Breaks
        Route::get('/settings/hours', [BusinessHoursController::class, 'index'])->name('settings.hours');
        Route::put('/settings/hours', [BusinessHoursController::class, 'update'])->name('settings.hours.update');

        // Calendar Exceptions (Holidays, Blackout Dates, Special Hours)
        Route::get('/settings/calendar', [CalendarExceptionController::class, 'index'])->name('settings.calendar');
        Route::post('/settings/calendar', [CalendarExceptionController::class, 'store'])->name('settings.calendar.store');
        Route::delete('/settings/calendar/{id}', [CalendarExceptionController::class, 'destroy'])->name('settings.calendar.destroy');

        // Notification Settings & Delivery Logs (PRD 37, 61, 214, 216)
        Route::get('/settings/notifications', [NotificationController::class, 'index'])->name('settings.notifications');
        Route::put('/settings/notifications/templates/{id}', [NotificationController::class, 'updateTemplate'])->name('settings.notifications.templates.update');
        Route::post('/settings/notifications/templates/{id}/reset', [NotificationController::class, 'resetTemplate'])->name('settings.notifications.templates.reset');
        Route::post('/settings/notifications/logs/{id}/retry', [NotificationController::class, 'retryLog'])->name('settings.notifications.logs.retry');

        // Custom Booking Statuses & Kanban Mapping (PRD 24, 25, 140, 213)
        Route::get('/settings/statuses', [BookingStatusController::class, 'index'])->name('settings.statuses.index');
        Route::post('/settings/statuses', [BookingStatusController::class, 'store'])->name('settings.statuses.store');
        Route::put('/settings/statuses/{id}', [BookingStatusController::class, 'update'])->name('settings.statuses.update');
        Route::delete('/settings/statuses/{id}', [BookingStatusController::class, 'destroy'])->name('settings.statuses.destroy');
        Route::post('/settings/statuses/reorder', [BookingStatusController::class, 'reorder'])->name('settings.statuses.reorder');
        Route::post('/settings/statuses/seed-defaults', [BookingStatusController::class, 'seedDefaults'])->name('settings.statuses.seed-defaults');

        // Form Builder & Conditional Form (PRD 26, 27, 179)
        Route::get('/settings/forms', [BookingFormController::class, 'index'])->name('settings.forms.index');
        Route::post('/settings/forms', [BookingFormController::class, 'store'])->name('settings.forms.store');
        Route::put('/settings/forms/{id}', [BookingFormController::class, 'update'])->name('settings.forms.update');
        Route::delete('/settings/forms/{id}', [BookingFormController::class, 'destroy'])->name('settings.forms.destroy');
        Route::post('/settings/forms/{id}/fields', [BookingFormController::class, 'addField'])->name('settings.forms.fields.store');
        Route::put('/settings/forms/{id}/fields/{fieldId}', [BookingFormController::class, 'updateField'])->name('settings.forms.fields.update');
        Route::delete('/settings/forms/{id}/fields/{fieldId}', [BookingFormController::class, 'deleteField'])->name('settings.forms.fields.destroy');
        Route::post('/settings/forms/{id}/reorder', [BookingFormController::class, 'reorderFields'])->name('settings.forms.fields.reorder');
        Route::post('/settings/forms/install-preset', [BookingFormController::class, 'installPreset'])->name('settings.forms.install-preset');
        Route::get('/booking-files/{id}', [BookingFormController::class, 'downloadFile'])->name('booking-files.download');

        // Workflow Builder & Business Automation (PRD 21, 22, 66, 204.5)
        Route::get('/workflows', [WorkflowController::class, 'index'])->name('workflows.index');
        Route::get('/workflows/{id}/builder', [WorkflowController::class, 'builder'])->name('workflows.builder');
        Route::post('/workflows', [WorkflowController::class, 'store'])->name('workflows.store');
        Route::put('/workflows/{id}', [WorkflowController::class, 'update'])->name('workflows.update');
        Route::delete('/workflows/{id}', [WorkflowController::class, 'destroy'])->name('workflows.destroy');
        Route::post('/workflows/{id}/save-draft', [WorkflowController::class, 'saveDraft'])->name('workflows.save-draft');
        Route::post('/workflows/{id}/publish', [WorkflowController::class, 'publish'])->name('workflows.publish');
        Route::post('/workflows/{id}/test-run', [WorkflowController::class, 'testRun'])->name('workflows.test-run');
        Route::get('/workflows/{id}/runs', [WorkflowController::class, 'runs'])->name('workflows.runs');
        Route::get('/workflows/runs/{runId}', [WorkflowController::class, 'showRun'])->name('workflows.runs.show');
        Route::post('/workflows/runs/{runId}/retry', [WorkflowController::class, 'retryRun'])->name('workflows.runs.retry');
        Route::post('/workflows/presets/{preset}', [WorkflowController::class, 'installPreset'])->name('workflows.presets.install');

        // Service Catalog (Services, Variants, Addons, Categories)
        Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
        Route::get('/services/create', [ServiceController::class, 'create'])->name('services.create');
        Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
        Route::get('/services/{id}/edit', [ServiceController::class, 'edit'])->name('services.edit');
        Route::put('/services/{id}', [ServiceController::class, 'update'])->name('services.update');
        Route::post('/services/{id}/archive', [ServiceController::class, 'archive'])->name('services.archive');
        Route::delete('/services/{id}', [ServiceController::class, 'destroy'])->name('services.destroy');

        // Service Categories
        Route::post('/service-categories', [ServiceCategoryController::class, 'store'])->name('service-categories.store');
        Route::put('/service-categories/{id}', [ServiceCategoryController::class, 'update'])->name('service-categories.update');
        Route::delete('/service-categories/{id}', [ServiceCategoryController::class, 'destroy'])->name('service-categories.destroy');

        // Resources (Staff, Room, Equipment, etc.)
        Route::get('/resources', [ResourceController::class, 'index'])->name('resources.index');
        Route::get('/resources/create', [ResourceController::class, 'create'])->name('resources.create');
        Route::post('/resources', [ResourceController::class, 'store'])->name('resources.store');
        Route::get('/resources/{id}/edit', [ResourceController::class, 'edit'])->name('resources.edit');
        Route::put('/resources/{id}', [ResourceController::class, 'update'])->name('resources.update');
        Route::post('/resources/{id}/archive', [ResourceController::class, 'archive'])->name('resources.archive');
        Route::post('/resources/{id}/unarchive', [ResourceController::class, 'unarchive'])->name('resources.unarchive');
        Route::delete('/resources/{id}', [ResourceController::class, 'destroy'])->name('resources.destroy');

        // Time Blocks & Cuti
        Route::get('/time-blocks', [TimeBlockController::class, 'index'])->name('time-blocks.index');
        Route::post('/time-blocks', [TimeBlockController::class, 'store'])->name('time-blocks.store');
        Route::delete('/time-blocks/{id}', [TimeBlockController::class, 'destroy'])->name('time-blocks.destroy');

        // Resource Groups
        Route::post('/resource-groups', [ResourceGroupController::class, 'store'])->name('resource-groups.store');
        Route::delete('/resource-groups/{id}', [ResourceGroupController::class, 'destroy'])->name('resource-groups.destroy');

        // Customer Module (PRD 7, 39, 40, 210 point 14, 212)
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/customers/export', [CustomerController::class, 'export'])->name('customers.export');
        Route::get('/customers/{id}', [CustomerController::class, 'show'])->name('customers.show');
        Route::put('/customers/{id}', [CustomerController::class, 'update'])->name('customers.update');
        Route::post('/customers/{id}/merge', [CustomerController::class, 'merge'])->name('customers.merge');
    });

    // Super Admin Routes (/admin/*)
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    });
});

use App\Http\Controllers\Public\LandingPageController;

// Public Tenant & Business Routes (PRD 28, 29, 167, 184, 205, 215.1)
Route::prefix('{slug}')
    ->where(['slug' => '^(?!(app|admin|login|register|logout|forgot-password|reset-password|up|_debugbar|b)$)[a-z0-9\-_]+$'])
    ->group(function () {
        Route::get('/', [LandingPageController::class, 'show'])->name('public.landing');
        Route::get('/booking', [LandingPageController::class, 'booking'])->name('public.booking');
        Route::post('/booking', [LandingPageController::class, 'storeBooking'])->name('public.booking.store');
        Route::get('/availability', [LandingPageController::class, 'availability'])->name('public.availability');
        Route::get('/booking/success/{code}', [LandingPageController::class, 'success'])->name('public.booking.success');
        Route::get('/booking/success/{code}/calendar.ics', [LandingPageController::class, 'calendar'])->name('public.booking.success.calendar');
        Route::get('/booking/manage/{token}', [LandingPageController::class, 'manage'])->name('public.booking.manage');
        Route::get('/booking/manage/{token}/calendar.ics', [LandingPageController::class, 'calendar'])->name('public.booking.manage.calendar');
        Route::post('/booking/manage/{token}/reschedule', [LandingPageController::class, 'reschedule'])
            ->middleware('throttle:10,1')
            ->name('public.booking.manage.reschedule');
        Route::post('/booking/manage/{token}/cancel', [LandingPageController::class, 'cancel'])
            ->middleware('throttle:10,1')
            ->name('public.booking.manage.cancel');
    });

// Public API aliases (PRD 215.1)
Route::prefix('api/public/{slug}')->group(function () {
    Route::get('/availability', [LandingPageController::class, 'availability'])->name('api.public.availability');
    Route::post('/bookings', [LandingPageController::class, 'storeBooking'])->name('api.public.bookings.store');
    Route::post('/bookings/{token}/reschedule', [LandingPageController::class, 'reschedule'])
        ->middleware('throttle:10,1')
        ->name('api.public.bookings.reschedule');
    Route::post('/bookings/{token}/cancel', [LandingPageController::class, 'cancel'])
        ->middleware('throttle:10,1')
        ->name('api.public.bookings.cancel');
});

// Backward compatibility alias for booking portal
Route::get('/b/{business_slug}', [LandingPageController::class, 'booking'])->name('public.business');
