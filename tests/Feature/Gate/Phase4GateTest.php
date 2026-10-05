<?php

namespace Tests\Feature\Gate;

use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\Actions\CreateBooking;
use App\Domain\Booking\Enums\AllocationStatus;
use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Customer\Models\Customer;
use App\Domain\Identity\Models\User;
use App\Domain\Payment\Models\Invoice;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Services\PaymentService;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceSchedule;
use App\Domain\Service\Models\Service;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Tenant\Models\Tenant;
use App\Support\TenantContext;
use Carbon\Carbon;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Tenant\TenantIsolationTestHelper;
use Tests\TestCase;

class Phase4GateTest extends TestCase
{
    use RefreshDatabase;
    use TenantIsolationTestHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);
        TenantContext::clear();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * EXIT CRITERIA 1:
     * Replay webhook tidak menghasilkan data ganda.
     * Webhook settlement yang diterima berulang kali (duplikasi / replay attack)
     * tidak mencatat pembayaran ganda ataupun merusak invoice.
     */
    public function test_exit_criteria_1_webhook_replay_does_not_create_duplicate_data(): void
    {
        $env = $this->createTenantEnvironment('Studio Musik Harmoni');
        /** @var Tenant $tenant */
        $tenant = $env['tenant'];
        $business = $env['business'];

        $serverKey = 'Midtrans-Secret-Key-Gate4-2026';
        $business->settings = [
            'payment_gateway' => [
                'midtrans_server_key' => $serverKey,
                'default_provider' => 'midtrans',
                'is_production' => false,
            ],
        ];
        $business->save();

        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'price_idr' => 250000,
        ]);

        $booking = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status_category' => BookingStatusCategory::PENDING,
            'total_idr' => 250000,
            'payment_status' => 'UNPAID',
        ]);

        /** @var PaymentService $paymentService */
        $paymentService = app(PaymentService::class);
        $invoice = $paymentService->createInvoiceForBooking($booking);

        $orderId = $invoice->invoice_number;
        $statusCode = '200';
        $grossAmount = '250000.00';
        $signature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        $payload = [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
            'transaction_id' => 'midtrans-trx-replay-test-01',
        ];

        // 1. Pengiriman Webhook Pertama (Original Delivery)
        $firstResponse = $this->postJson('/webhooks/payment/midtrans', $payload);
        $firstResponse->assertStatus(200)->assertJson(['status' => 'success']);

        $invoice->refresh();
        $booking->refresh();

        $this->assertSame(250000, $invoice->amount_paid_idr);
        $this->assertSame('PAID', $invoice->status);
        $this->assertSame('PAID', $booking->payment_status);
        $this->assertSame(BookingStatusCategory::CONFIRMED, $booking->status_category);
        $this->assertSame(1, Payment::where('invoice_id', $invoice->id)->count());

        // 2. Pengiriman Webhook Kedua (Replay / Retry dari Payment Gateway)
        $secondResponse = $this->postJson('/webhooks/payment/midtrans', $payload);
        $secondResponse->assertStatus(200)->assertJson(['status' => 'duplicate']);

        // 3. Pengiriman Webhook Ketiga (Replay berulang)
        $thirdResponse = $this->postJson('/webhooks/payment/midtrans', $payload);
        $thirdResponse->assertStatus(200)->assertJson(['status' => 'duplicate']);

        // Verifikasi mutlak: Tidak ada duplikasi record pembayaran atau lonjakan nominal
        $invoice->refresh();
        $this->assertSame(250000, $invoice->amount_paid_idr);
        $this->assertSame(1, Payment::where('invoice_id', $invoice->id)->count());
    }

    /**
     * EXIT CRITERIA 2:
     * Hold kedaluwarsa melepas slot di AvailabilityService.
     * Ketika hold masih aktif, slot terkunci; ketika hold kedaluwarsa, slot otomatis kembali tersedia
     * dan artisan command holds:expire membatalkan pemesanan kedaluwarsa secara bersih.
     */
    public function test_exit_criteria_2_expired_hold_releases_slot_in_availability(): void
    {
        Carbon::setTestNow('2026-10-12 08:00:00'); // Senin pagi

        $env = $this->createTenantEnvironment('Futsal Arena Pro');
        /** @var Tenant $tenant */
        $tenant = $env['tenant'];
        $business = $env['business'];

        // Jam operasional bisnis buka 24 jam untuk pengujian
        for ($i = 0; $i < 7; $i++) {
            BusinessHour::create([
                'tenant_id' => $tenant->id,
                'business_id' => $business->id,
                'day_of_week' => $i,
                'open_time' => '00:00:00',
                'close_time' => '23:59:59',
                'is_open' => true,
            ]);
        }

        $court = Resource::factory()->create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'visibility' => 'PUBLIC',
            'state' => 'AVAILABLE',
        ]);

        ResourceSchedule::create([
            'tenant_id' => $tenant->id,
            'resource_id' => $court->id,
            'day_of_week' => 1, // Senin
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'is_active' => true,
        ]);

        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'duration_minutes' => 60,
        ]);

        /** @var CreateBooking $createBooking */
        $createBooking = app(CreateBooking::class);

        // 1. Buat pemesanan berstatus PENDING dengan hold aktif 15 menit untuk slot 10:00
        $booking = $createBooking->execute([
            'tenant' => $tenant,
            'service' => $service,
            'customer' => ['name' => 'Pemesan Lapangan A', 'phone' => '081199887766'],
            'start_at' => '2026-10-12 10:00:00',
            'staff_id' => $court->id,
            'requires_payment' => true,
            'hold_minutes' => 15,
        ]);

        $this->assertTrue($booking->isHoldActive());

        /** @var AvailabilityService $availabilityService */
        $availabilityService = app(AvailabilityService::class);

        // Slot 10:00 harus terkunci (tidak tersedia) selama masa hold
        $slotsDuringHold = $availabilityService->getSlotsForDate($tenant, $service, '2026-10-12', [
            'staff_id' => $court->id,
        ]);
        $slot10DuringHold = $slotsDuringHold->firstWhere('start_time', '10:00');
        $this->assertNotNull($slot10DuringHold);
        $this->assertFalse($slot10DuringHold['is_available']);

        // 2. Waktu maju 20 menit: hold telah kedaluwarsa
        Carbon::setTestNow('2026-10-12 08:20:00');
        $this->assertFalse($booking->fresh()->isHoldActive());

        // Slot 10:00 otomatis kembali tersedia di AvailabilityService
        $slotsAfterHoldExpiry = $availabilityService->getSlotsForDate($tenant, $service, '2026-10-12', [
            'staff_id' => $court->id,
        ]);
        $slot10AfterHoldExpiry = $slotsAfterHoldExpiry->firstWhere('start_time', '10:00');
        $this->assertNotNull($slot10AfterHoldExpiry);
        $this->assertTrue($slot10AfterHoldExpiry['is_available']);

        // 3. Jalankan scheduler pembersihan berkala bookings:expire-holds
        Artisan::call('bookings:expire-holds');

        $booking->refresh();
        $this->assertSame(BookingStatusCategory::EXPIRED, $booking->status_category);
        $this->assertSame(AllocationStatus::RELEASED, $booking->allocations()->first()->status);
    }

    /**
     * EXIT CRITERIA 3:
     * Suspend tenant menutup halaman publik (503 TENANT_UNAVAILABLE) dan membatasi mutasi owner.
     * Ketika tenant disuspend oleh Super Admin, pelanggan melihat error 503 yang jelas,
     * dan owner dibatasi dalam mode read-only (mutasi data ditolak 403 Forbidden).
     */
    public function test_exit_criteria_3_suspended_tenant_closes_public_page_and_blocks_mutations(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);

        $env = $this->createTenantEnvironment('Klinik Estetika Glow');
        $owner = $env['user'];
        /** @var Tenant $tenant */
        $tenant = $env['tenant'];
        $business = $env['business'];

        // Sebelum suspend: Halaman publik aktif (200 OK)
        $publicLanding = $this->get(route('public.landing', $business->slug));
        $publicLanding->assertStatus(200);

        $publicBooking = $this->get(route('public.booking', $business->slug));
        $publicBooking->assertStatus(200);

        // Super Admin melakukan suspend pada tenant (PRD 73, 81)
        $suspendResponse = $this->actingAs($superAdmin)->post(route('admin.tenants.suspend', $tenant->id), [
            'reason' => 'Pelanggaran ketentuan penggunaan: spam booking terdeteksi',
        ]);
        $suspendResponse->assertRedirect();
        $suspendResponse->assertSessionHas('success');

        $tenant->refresh();
        $this->assertSame('SUSPENDED', $tenant->status);

        // 1. Verifikasi Halaman Publik Tertutup dengan status 503 TENANT_UNAVAILABLE
        $suspendedLanding = $this->get(route('public.landing', $business->slug));
        $suspendedLanding->assertStatus(503);

        $suspendedBooking = $this->get(route('public.booking', $business->slug));
        $suspendedBooking->assertStatus(503);

        // 2. Verifikasi Mutasi Data Owner Ditolak (403 Forbidden)
        TenantContext::setTenant($tenant);
        $mutationAttempt = $this->actingAs($owner)->post(route('owner.customers.store'), [
            'name' => 'Data Pelanggan Baru Terblokir',
            'phone' => '081298765432',
        ]);
        $mutationAttempt->assertForbidden();

        // 3. Verifikasi Super Admin Dapat Mengaktifkan Kembali (Restorasi Operasional)
        $activateResponse = $this->actingAs($superAdmin)->post(route('admin.tenants.activate', $tenant->id));
        $activateResponse->assertRedirect();

        $tenant->refresh();
        $this->assertSame('ACTIVE', $tenant->status);

        // Halaman publik kembali online (200 OK)
        $restoredLanding = $this->get(route('public.landing', $business->slug));
        $restoredLanding->assertStatus(200);
    }
}
