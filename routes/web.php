<?php

use App\Domain\Business\Models\Business;
use App\Domain\Identity\Controllers\AuthenticatedSessionController;
use App\Domain\Identity\Controllers\NewPasswordController;
use App\Domain\Identity\Controllers\PasswordResetLinkController;
use App\Domain\Identity\Controllers\RegisteredUserController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Owner\AuditLogController;
use App\Http\Controllers\Owner\BusinessHoursController;
use App\Http\Controllers\Owner\BusinessProfileController;
use App\Http\Controllers\Owner\CalendarExceptionController;
use App\Http\Controllers\Owner\DashboardController as OwnerDashboardController;
use App\Http\Controllers\Owner\MemberController;
use App\Http\Controllers\Owner\ServiceCategoryController;
use App\Http\Controllers\Owner\ServiceController;
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
    });

    // Super Admin Routes (/admin/*)
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    });
});

// Public Business Booking Page (/b/{business_slug})
Route::get('/b/{business_slug}', function (string $business_slug) {
    $business = Business::withoutGlobalScopes()
        ->where('slug', $business_slug)
        ->firstOrFail();

    return Inertia::render('Public/Booking', [
        'business' => [
            'name' => $business->name,
            'slug' => $business->slug,
            'timezone' => $business->timezone,
        ],
    ]);
})->name('public.business');
