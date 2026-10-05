<?php

use App\Domain\Booking\Actions\CreateBooking;
use App\Domain\Booking\Enums\AllocationStatus;
use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingAllocation;
use App\Domain\Booking\Services\BookingService;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Customer\Models\Customer;
use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationEvent;
use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Jobs\SendNotificationJob;
use App\Domain\Notification\Mail\GenericNotificationMail;
use App\Domain\Notification\Models\NotificationLog;
use App\Domain\Notification\Models\NotificationTemplate;
use App\Domain\Notification\Services\TemplateParser;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceType;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use App\Support\TenantContext;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Tenant\TenantIsolationTestHelper;

uses(RefreshDatabase::class);
uses(TenantIsolationTestHelper::class);

beforeEach(function () {
    Carbon::setTestNow('2026-10-05 10:00:00');
    $this->seed(RolePermissionSeeder::class);

    $env = $this->createTenantEnvironment('Studio Foto Kenangan');
    $this->owner = $env['user'];
    $this->tenant = $env['tenant'];
    $this->business = $env['business'];

    $this->business->update([
        'slug' => 'foto-kenangan',
        'timezone' => 'Asia/Jakarta',
        'whatsapp' => '081234567890',
        'phone' => '081234567890',
        'address' => 'Jl. Merdeka No. 45, Jakarta Pusat',
        'published_at' => Carbon::now()->subDays(10),
    ]);

    TenantContext::setTenant($this->tenant);

    // Setup operating hours (08:00 - 22:00)
    for ($d = 0; $d <= 6; $d++) {
        BusinessHour::create([
            'tenant_id' => $this->tenant->id,
            'business_id' => $this->business->id,
            'day_of_week' => $d,
            'is_open' => true,
            'open_time' => '08:00:00',
            'close_time' => '22:00:00',
            'breaks' => [],
        ]);
    }

    $this->service = Service::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Sesi Foto Wisuda',
        'duration_minutes' => 60,
        'price_idr' => 250000,
        'is_active' => true,
    ]);

    $this->staffType = ResourceType::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'FOTOGRAFER',
        'name' => 'Fotografer',
        'is_staff' => true,
    ]);

    $this->staff = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Andi Pratama',
        'state' => 'AVAILABLE',
    ]);
});

afterEach(function () {
    TenantContext::clear();
});

it('replaces all template variables correctly using TemplateParser', function () {
    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Rina Salsabila',
        'phone_e164' => '+6281298765432',
        'email' => 'rina@example.com',
    ]);

    $booking = Booking::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'BK-20261005-0001',
        'customer_id' => $customer->id,
        'service_id' => $this->service->id,
        'service_snapshot' => ['name' => $this->service->name],
        'start_at' => Carbon::parse('2026-10-06 14:00:00', 'Asia/Jakarta')->setTimezone('UTC'),
        'end_at' => Carbon::parse('2026-10-06 15:00:00', 'Asia/Jakarta')->setTimezone('UTC'),
        'business_timezone' => 'Asia/Jakarta',
        'status_category' => BookingStatusCategory::CONFIRMED,
        'payment_status' => 'PAID',
        'total_idr' => 250000,
        'manage_token' => 'sample-raw-token-12345',
    ]);

    BookingAllocation::create([
        'tenant_id' => $this->tenant->id,
        'booking_id' => $booking->id,
        'resource_id' => $this->staff->id,
        'role' => 'staff',
        'start_at' => $booking->start_at,
        'end_at' => $booking->end_at,
        'status' => AllocationStatus::ACTIVE,
        'quantity' => 1,
    ]);

    /** @var TemplateParser $parser */
    $parser = app(TemplateParser::class);
    $vars = $parser->extractBookingVariables($booking, 'sample-raw-token-12345');

    expect($vars['customer.name'])->toBe('Rina Salsabila')
        ->and($vars['business.name'])->toBe('Studio Foto Kenangan Business')
        ->and($vars['booking.code'])->toBe('BK-20261005-0001')
        ->and($vars['service.name'])->toBe('Sesi Foto Wisuda')
        ->and($vars['staff.name'])->toBe('Andi Pratama')
        ->and($vars['booking.total'])->toBe('Rp 250.000')
        ->and($vars['payment.status'])->toBe('Lunas')
        ->and($vars['manage_booking_url'])->toContain('/foto-kenangan/booking/manage/sample-raw-token-12345');

    $template = 'Halo {{customer.name}}, booking {{booking.code}} untuk {{service.name}} pada {{booking.date}} sebesar {{booking.total}} telah {{payment.status}}!';
    $hydrated = $parser->parse($template, $vars);

    expect($hydrated)->toContain('Halo Rina Salsabila')
        ->and($hydrated)->toContain('booking BK-20261005-0001')
        ->and($hydrated)->toContain('Sesi Foto Wisuda')
        ->and($hydrated)->toContain('Rp 250.000')
        ->and($hydrated)->toContain('telah Lunas!');
});

it('creates pending notification logs when booking is created', function () {
    Queue::fake();

    /** @var CreateBooking $createBooking */
    $createBooking = app(CreateBooking::class);

    $booking = $createBooking->execute([
        'tenant' => $this->tenant,
        'service' => $this->service,
        'customer' => [
            'name' => 'Budi Santoso',
            'phone' => '081211112222',
            'email' => 'budi@example.com',
        ],
        'start_at' => '2026-10-06 10:00:00',
        'staff_id' => $this->staff->id,
        'source' => 'PUBLIC_WEB',
    ]);

    expect($booking)->not->toBeNull();

    // Verify NotificationLog records for both Email and WhatsApp
    $emailLog = NotificationLog::where('booking_id', $booking->id)
        ->where('channel', NotificationChannel::EMAIL->value)
        ->first();

    $waLog = NotificationLog::where('booking_id', $booking->id)
        ->where('channel', NotificationChannel::WHATSAPP->value)
        ->first();

    expect($emailLog)->not->toBeNull()
        ->and($emailLog->recipient)->toBe('budi@example.com')
        ->and($emailLog->event)->toBe(NotificationEvent::BOOKING_CREATED)
        ->and($emailLog->status)->toBe(NotificationStatus::PENDING)
        ->and($emailLog->body)->toContain('Budi Santoso')
        ->and($emailLog->body)->toContain($booking->code);

    expect($waLog)->not->toBeNull()
        ->and($waLog->recipient)->toBe('+6281211112222')
        ->and($waLog->status)->toBe(NotificationStatus::PENDING);

    Queue::assertPushed(SendNotificationJob::class, 2);
});

it('handles notification failures without rolling back or failing booking creation', function () {
    // Force an exception inside mail sending
    Mail::shouldReceive('to')->andThrow(new Exception('SMTP Connection timeout'));

    /** @var CreateBooking $createBooking */
    $createBooking = app(CreateBooking::class);

    // Booking creation MUST succeed despite notification service exceptions
    $booking = $createBooking->execute([
        'tenant' => $this->tenant,
        'service' => $this->service,
        'customer' => [
            'name' => 'Citra Dewi',
            'phone' => '081233334444',
            'email' => 'citra@example.com',
        ],
        'start_at' => '2026-10-06 11:00:00',
        'staff_id' => $this->staff->id,
        'source' => 'PUBLIC_WEB',
    ]);

    expect($booking)->not->toBeNull()
        ->and($booking->id)->toBeGreaterThan(0);

    // Verify booking is firmly persisted in DB
    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'code' => $booking->code,
    ]);
});

it('tracks retry attempts and transitions to DEAD_LETTER after 5 failed attempts', function () {
    /** @var NotificationLog $log */
    $log = NotificationLog::create([
        'tenant_id' => $this->tenant->id,
        'channel' => NotificationChannel::EMAIL,
        'event' => NotificationEvent::BOOKING_CREATED,
        'recipient' => 'test-retry@example.com',
        'subject' => 'Test Subject',
        'body' => 'Test Body',
        'status' => NotificationStatus::PENDING,
        'attempts' => 0,
        'max_attempts' => 5,
    ]);

    // Attempt 1: Fails
    $log->recordFailure('Connection failed attempt 1');
    $log->refresh();
    expect($log->attempts)->toBe(1)
        ->and($log->status)->toBe(NotificationStatus::FAILED)
        ->and($log->next_retry_at)->not->toBeNull()
        ->and($log->last_error)->toBe('Connection failed attempt 1');

    // Attempt 2: Fails
    $log->recordFailure('Connection failed attempt 2');
    $log->refresh();
    expect($log->attempts)->toBe(2)
        ->and($log->status)->toBe(NotificationStatus::FAILED);

    // Attempt 3: Fails
    $log->recordFailure('Connection failed attempt 3');
    $log->refresh();
    expect($log->attempts)->toBe(3);

    // Attempt 4: Fails
    $log->recordFailure('Connection failed attempt 4');
    $log->refresh();
    expect($log->attempts)->toBe(4)
        ->and($log->status)->toBe(NotificationStatus::FAILED);

    // Attempt 5: Reaches max_attempts (5) -> Transitions strictly to DEAD_LETTER (PRD 61)
    $log->recordFailure('Fatal timeout attempt 5');
    $log->refresh();
    expect($log->attempts)->toBe(5)
        ->and($log->status)->toBe(NotificationStatus::DEAD_LETTER)
        ->and($log->isDeadLetter())->toBeTrue()
        ->and($log->next_retry_at)->toBeNull()
        ->and($log->last_error)->toBe('Fatal timeout attempt 5');
});

it('dispatches notifications on booking reschedule and cancellation', function () {
    Queue::fake();

    /** @var BookingService $bookingService */
    $bookingService = app(BookingService::class);

    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'customer-reschedule@example.com',
        'phone_e164' => '+6281255556666',
    ]);

    $booking = Booking::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'BK-20261005-0099',
        'customer_id' => $customer->id,
        'service_id' => $this->service->id,
        'service_snapshot' => ['name' => $this->service->name],
        'start_at' => Carbon::parse('2026-10-07 10:00:00', 'Asia/Jakarta')->setTimezone('UTC'),
        'end_at' => Carbon::parse('2026-10-07 11:00:00', 'Asia/Jakarta')->setTimezone('UTC'),
        'business_timezone' => 'Asia/Jakarta',
        'status_category' => BookingStatusCategory::CONFIRMED,
        'payment_status' => 'PAID',
        'total_idr' => 250000,
        'manage_token' => 'reschedule-test-token',
    ]);

    BookingAllocation::create([
        'tenant_id' => $this->tenant->id,
        'booking_id' => $booking->id,
        'resource_id' => $this->staff->id,
        'role' => 'staff',
        'start_at' => $booking->start_at,
        'end_at' => $booking->end_at,
        'status' => AllocationStatus::ACTIVE,
        'quantity' => 1,
    ]);

    // Reschedule booking
    $newStart = Carbon::parse('2026-10-08 14:00:00', 'Asia/Jakarta');
    $bookingService->reschedule($booking, $newStart, ['reason' => 'Permintaan pelanggan']);

    $rescheduleLog = NotificationLog::where('booking_id', $booking->id)
        ->where('event', NotificationEvent::BOOKING_RESCHEDULED->value)
        ->first();

    expect($rescheduleLog)->not->toBeNull();

    // Cancel booking
    $bookingService->cancel($booking, 'Jadwal bertabrakan');

    $cancelLog = NotificationLog::where('booking_id', $booking->id)
        ->where('event', NotificationEvent::BOOKING_CANCELLED->value)
        ->first();

    expect($cancelLog)->not->toBeNull();
});

it('sends H-1 reminders idempotently without duplication', function () {
    Queue::fake();

    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'reminder-test@example.com',
        'phone_e164' => '+6281277778888',
    ]);

    // Booking scheduled for tomorrow (2026-10-06 14:00)
    $booking = Booking::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'BK-20261005-REMIND',
        'customer_id' => $customer->id,
        'service_id' => $this->service->id,
        'service_snapshot' => ['name' => $this->service->name],
        'start_at' => Carbon::parse('2026-10-06 14:00:00', 'Asia/Jakarta')->setTimezone('UTC'),
        'end_at' => Carbon::parse('2026-10-06 15:00:00', 'Asia/Jakarta')->setTimezone('UTC'),
        'business_timezone' => 'Asia/Jakarta',
        'status_category' => BookingStatusCategory::CONFIRMED,
        'payment_status' => 'PAID',
        'total_idr' => 250000,
        'manage_token' => 'reminder-token',
    ]);

    // Run command first time
    $this->artisan('notifications:send-reminders')
        ->expectsOutputToContain('Dispatched 1 H-1 booking reminder notification(s).')
        ->assertSuccessful();

    $remindCount = NotificationLog::where('booking_id', $booking->id)
        ->where('event', NotificationEvent::BOOKING_REMINDER_H1->value)
        ->count();

    expect($remindCount)->toBe(2); // 1 Email + 1 WhatsApp

    // Run command second time (idempotent test)
    $this->artisan('notifications:send-reminders')
        ->expectsOutputToContain('Dispatched 0 H-1 booking reminder notification(s).')
        ->assertSuccessful();

    // Count must remain 2 (no duplicates)
    $remindCountAfter = NotificationLog::where('booking_id', $booking->id)
        ->where('event', NotificationEvent::BOOKING_REMINDER_H1->value)
        ->count();

    expect($remindCountAfter)->toBe(2);
});

it('retries due failed notifications via console command', function () {
    Queue::fake();

    /** @var NotificationLog $log */
    $log = NotificationLog::create([
        'tenant_id' => $this->tenant->id,
        'channel' => NotificationChannel::EMAIL,
        'event' => NotificationEvent::BOOKING_CREATED,
        'recipient' => 'retry-worker@example.com',
        'subject' => 'Subject',
        'body' => 'Body',
        'status' => NotificationStatus::FAILED,
        'attempts' => 2,
        'max_attempts' => 5,
        'next_retry_at' => Carbon::now()->subMinutes(5), // Overdue for retry
    ]);

    $this->artisan('notifications:retry-failed')
        ->expectsOutputToContain('Processed and redispatched 1 due failed notification(s).')
        ->assertSuccessful();

    $log->refresh();
    expect($log->status)->toBe(NotificationStatus::PENDING)
        ->and($log->next_retry_at)->toBeNull();

    Queue::assertPushed(SendNotificationJob::class);
});

it('enforces tenant isolation for notification templates and logs', function () {
    $envB = $this->createTenantEnvironment('Tenant B Studio');
    $ownerB = $envB['user'];
    $tenantB = $envB['tenant'];

    $logA = NotificationLog::create([
        'tenant_id' => $this->tenant->id,
        'channel' => NotificationChannel::EMAIL,
        'event' => NotificationEvent::BOOKING_CREATED,
        'recipient' => 'tenantA@example.com',
        'body' => 'Tenant A Secret Notification',
        'status' => NotificationStatus::SENT,
    ]);

    $logB = NotificationLog::create([
        'tenant_id' => $tenantB->id,
        'channel' => NotificationChannel::EMAIL,
        'event' => NotificationEvent::BOOKING_CREATED,
        'recipient' => 'tenantB@example.com',
        'body' => 'Tenant B Secret Notification',
        'status' => NotificationStatus::SENT,
    ]);

    // Owner of Tenant A viewing /app/settings/notifications
    $response = $this->actingAs($this->owner)
        ->get('/app/settings/notifications');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Owner/Settings/Notifications')
        ->has('templates')
        ->has('logs.data', 1)
        ->where('logs.data.0.recipient', 'tenantA@example.com')
    );
});

it('allows owner to view notifications page, edit template, and reset to default', function () {
    $this->actingAs($this->owner);

    // 1. Visit index page
    $response = $this->get('/app/settings/notifications');
    $response->assertOk();

    // 2. Find a template to edit
    /** @var NotificationTemplate $template */
    $template = NotificationTemplate::where('tenant_id', $this->tenant->id)->first();
    expect($template)->not->toBeNull();

    // 3. Update template
    $updateResponse = $this->put("/app/settings/notifications/templates/{$template->id}", [
        'name' => 'Custom Notif Title',
        'subject' => 'Custom Subjek {{booking.code}}',
        'body' => 'Custom Body Pesan {{customer.name}}',
        'is_active' => true,
    ]);

    $updateResponse->assertRedirect();
    $template->refresh();
    expect($template->name)->toBe('Custom Notif Title')
        ->and($template->body)->toBe('Custom Body Pesan {{customer.name}}');

    // 4. Reset to default
    $resetResponse = $this->post("/app/settings/notifications/templates/{$template->id}/reset");
    $resetResponse->assertRedirect();

    $template->refresh();
    expect($template->body)->not->toBe('Custom Body Pesan {{customer.name}}');
});

it('allows owner to manually retry a dead letter notification', function () {
    Queue::fake();

    $log = NotificationLog::create([
        'tenant_id' => $this->tenant->id,
        'channel' => NotificationChannel::EMAIL,
        'event' => NotificationEvent::BOOKING_CREATED,
        'recipient' => 'deadletter@example.com',
        'body' => 'Dead letter message',
        'status' => NotificationStatus::DEAD_LETTER,
        'attempts' => 5,
        'last_error' => 'Exhausted retries',
    ]);

    $response = $this->actingAs($this->owner)
        ->post("/app/settings/notifications/logs/{$log->id}/retry");

    $response->assertRedirect();

    $log->refresh();
    expect($log->status)->toBe(NotificationStatus::PENDING)
        ->and($log->last_error)->toBeNull();

    Queue::assertPushed(SendNotificationJob::class);
});

it('sends email and marks log as SENT when SendNotificationJob is executed', function () {
    Mail::fake();

    /** @var NotificationLog $log */
    $log = NotificationLog::create([
        'tenant_id' => $this->tenant->id,
        'channel' => NotificationChannel::EMAIL,
        'event' => NotificationEvent::BOOKING_CREATED,
        'recipient' => 'test-delivery@example.com',
        'subject' => 'Reservasi Berhasil',
        'body' => 'Halo Budi, reservasi berhasil. Tautan: http://localhost/manage/123',
        'status' => NotificationStatus::PENDING,
        'attempts' => 0,
        'max_attempts' => 5,
    ]);

    $job = new SendNotificationJob($log);
    $job->handle();

    $log->refresh();
    expect($log->status)->toBe(NotificationStatus::SENT)
        ->and($log->sent_at)->not->toBeNull()
        ->and($log->last_error)->toBeNull();

    Mail::assertSent(GenericNotificationMail::class, function ($mail) use ($log) {
        return $mail->hasTo($log->recipient)
            && $mail->subjectLine === 'Reservasi Berhasil';
    });
});

it('records failure on SendNotificationJob when recipient email is invalid', function () {
    Mail::fake();

    /** @var NotificationLog $log */
    $log = NotificationLog::create([
        'tenant_id' => $this->tenant->id,
        'channel' => NotificationChannel::EMAIL,
        'event' => NotificationEvent::BOOKING_CREATED,
        'recipient' => 'not-an-email',
        'subject' => 'Subject',
        'body' => 'Body',
        'status' => NotificationStatus::PENDING,
        'attempts' => 0,
        'max_attempts' => 5,
    ]);

    $job = new SendNotificationJob($log);
    $job->handle();

    $log->refresh();
    expect($log->status)->toBe(NotificationStatus::FAILED)
        ->and($log->attempts)->toBe(1)
        ->and($log->last_error)->toContain('tidak valid');
});
