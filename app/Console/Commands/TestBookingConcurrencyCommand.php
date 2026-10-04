<?php

namespace App\Console\Commands;

use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingAllocation;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceSchedule;
use App\Domain\Resource\Models\ResourceType;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class TestBookingConcurrencyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'booking:test-concurrency
        {--scenario=all : single-slot-50 | multi-slots-50 | class-capacity-20 | all}
        {--workers=50 : Number of concurrent workers for scenario 1 & 2}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run real MySQL multi-process parallel concurrency tests (PRD 204.7)';

    /**
     * @var array<string, mixed>
     */
    protected array $reportResults = [];

    public function handle(): int
    {
        $driver = DB::connection()->getDriverName();
        $this->info('=================================================');
        $this->info('AMAN BOOKING — Real Concurrency & Deadlock Benchmark');
        $this->info("Database Connection: {$driver} (".config('database.connections.'.config('database.default').'.database').')');
        $this->info('PHP Binary: '.PHP_BINARY);
        $this->info('=================================================');

        if ($driver !== 'mysql') {
            $this->error("PERINGATAN: Driver database saat ini adalah '{$driver}'. PRD 204.7 mensyaratkan MySQL sungguhan.");
            if (! $this->confirm('Apakah Anda ingin tetap melanjutkan? (Locks row-level mungkin berbeda)', false)) {
                return Command::FAILURE;
            }
        }

        $scenario = (string) $this->option('scenario');
        $workerCount = (int) $this->option('workers');

        // Setup isolated test tenant & business
        $this->info("\n[1/3] Menyiapkan environment test tenant & resource...");
        $fixture = $this->setupTestEnvironment();
        $tenant = $fixture['tenant'];
        $staff = $fixture['staff'];
        $room = $fixture['room'];
        $singleService = $fixture['single_service'];
        $classService = $fixture['class_service'];

        $overallSuccess = true;

        try {
            // Scenario 1: 50 parallel requests targeting SAME slot on SAME resource
            if ($scenario === 'all' || $scenario === 'single-slot-50') {
                $res1 = $this->runSingleSlotScenario($tenant, $singleService, $staff, $workerCount);
                $this->reportResults['single_slot_50'] = $res1;
                if (! $res1['passed']) {
                    $overallSuccess = false;
                }
            }

            // Scenario 2: 50 parallel requests targeting DIFFERENT slots on SAME resource (no deadlocks)
            if ($scenario === 'all' || $scenario === 'multi-slots-50') {
                $res2 = $this->runMultiSlotsScenario($tenant, $singleService, $staff, $workerCount);
                $this->reportResults['multi_slots_50'] = $res2;
                if (! $res2['passed']) {
                    $overallSuccess = false;
                }
            }

            // Scenario 3: 25 parallel requests for Class Capacity of 20 (exactly 20 succeed, 5 CAPACITY_FULL)
            if ($scenario === 'all' || $scenario === 'class-capacity-20') {
                $res3 = $this->runClassCapacityScenario($tenant, $classService, $room, 25, 20);
                $this->reportResults['class_capacity_20'] = $res3;
                if (! $res3['passed']) {
                    $overallSuccess = false;
                }
            }

            $this->renderSummaryTable();
        } finally {
            $this->info("\n[3/3] Membersihkan data test tenant...");
            $this->cleanupTestEnvironment($tenant);
        }

        return $overallSuccess ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Scenario 1: 50 parallel requests targeting the exact same slot.
     * Expectation: Exactly 1 succeeds, 49 get SLOT_TAKEN, 0 deadlocks.
     *
     * @return array<string, mixed>
     */
    protected function runSingleSlotScenario(Tenant $tenant, Service $service, Resource $resource, int $concurrency): array
    {
        $this->info("\n-------------------------------------------------");
        $this->info("SCENARIO 1: {$concurrency} Request Paralel ke Slot Tunggal yang Sama");
        $this->info('Target: 1 Booking Sukses (CONFIRMED), '.($concurrency - 1).' Gagal (SLOT_TAKEN), 0 Deadlock');
        $this->info('-------------------------------------------------');

        $targetSlot = '2026-11-25 10:00:00';
        $processes = [];
        $startTime = microtime(true);

        for ($i = 1; $i <= $concurrency; $i++) {
            $cmd = [
                PHP_BINARY,
                'artisan',
                'booking:worker-book',
                "--tenant-id={$tenant->id}",
                "--service-id={$service->id}",
                "--start-at={$targetSlot}",
                "--resource-ids={$resource->id}",
                "--customer-name=Concurrent Buyer {$i}",
                '--customer-phone=0812999900'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                '--quantity=1',
            ];

            $process = new Process($cmd, base_path());
            $process->setTimeout(60);
            $processes[] = $process;
        }

        // Launch all 50 in parallel simultaneously
        foreach ($processes as $p) {
            $p->start();
        }

        $this->output->write("Menjalankan {$concurrency} proses secara simultan");
        while (count(array_filter($processes, fn (Process $p) => $p->isRunning())) > 0) {
            $this->output->write('.');
            usleep(50000); // 50ms poll
        }
        $this->output->writeln(' [SELESAI]');

        $totalWallTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        // Parse outputs
        $successCount = 0;
        $slotTakenCount = 0;
        $deadlockCount = 0;
        $otherFailures = [];
        $latencies = [];

        foreach ($processes as $p) {
            $out = trim($p->getOutput());
            $data = json_decode($out, true);

            if (! is_array($data)) {
                $err = trim($p->getErrorOutput()) ?: $out;
                $otherFailures[] = "Non-JSON Output: {$err}";

                continue;
            }

            $latencies[] = $data['duration_ms'] ?? 0;

            if (! empty($data['success'])) {
                $successCount++;
            } else {
                $err = $data['error'] ?? 'UNKNOWN';
                if ($err === 'SLOT_TAKEN') {
                    $slotTakenCount++;
                } elseif ($err === 'DEADLOCK') {
                    $deadlockCount++;
                } else {
                    $otherFailures[] = "{$err}: ".($data['message'] ?? '');
                }
            }
        }

        sort($latencies);
        $countLat = count($latencies);
        $minLat = $countLat > 0 ? $latencies[0] : 0;
        $maxLat = $countLat > 0 ? $latencies[$countLat - 1] : 0;
        $avgLat = $countLat > 0 ? round(array_sum($latencies) / $countLat, 2) : 0;
        $p95Index = (int) ceil(0.95 * $countLat) - 1;
        $p95Lat = $countLat > 0 ? $latencies[max(0, $p95Index)] : 0;

        // DB Verification
        $dbAllocations = BookingAllocation::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('resource_id', $resource->id)
            ->where('status', 'ACTIVE')
            ->count();

        $passed = ($successCount === 1) && ($slotTakenCount === ($concurrency - 1)) && ($deadlockCount === 0) && ($dbAllocations === 1);

        $this->line("  ✓ Sukses: {$successCount} / {$concurrency} (Ekspektasi: 1)");
        $this->line("  ✓ Ditolak SLOT_TAKEN: {$slotTakenCount} / {$concurrency} (Ekspektasi: ".($concurrency - 1).')');
        $this->line("  ✓ Deadlock Terdeteksi: {$deadlockCount} (Ekspektasi: 0)");
        $this->line("  ✓ Alokasi Nyata di DB: {$dbAllocations} (Ekspektasi: 1)");
        $this->line("  ⏱ Waktu Total: {$totalWallTimeMs} ms | Min: {$minLat} ms | Max: {$maxLat} ms | Avg: {$avgLat} ms | P95: {$p95Lat} ms");

        if (! empty($otherFailures)) {
            $this->warn('  ⚠️ Error Lain: '.implode('; ', array_slice($otherFailures, 0, 3)));
        }

        $this->line($passed ? '  👉 Status: [LULUS]' : '  👉 Status: [GAGAL]');

        return [
            'name' => '50 Paralel Slot Tunggal',
            'concurrency' => $concurrency,
            'success_count' => $successCount,
            'expected_success' => 1,
            'slot_taken_count' => $slotTakenCount,
            'expected_slot_taken' => $concurrency - 1,
            'deadlock_count' => $deadlockCount,
            'db_allocations' => $dbAllocations,
            'wall_time_ms' => $totalWallTimeMs,
            'avg_ms' => $avgLat,
            'p95_ms' => $p95Lat,
            'passed' => $passed,
        ];
    }

    /**
     * Scenario 2: 50 parallel requests targeting 50 different slots on same resource.
     * Expectation: All 50 succeed, 0 deadlocks.
     *
     * @return array<string, mixed>
     */
    protected function runMultiSlotsScenario(Tenant $tenant, Service $service, Resource $resource, int $concurrency): array
    {
        $this->info("\n-------------------------------------------------");
        $this->info("SCENARIO 2: {$concurrency} Request Paralel ke Slot Berbeda (Resource Sama)");
        $this->info("Target: {$concurrency} Booking Sukses, 0 Deadlock, 0 Konflik");
        $this->info('-------------------------------------------------');

        $baseDate = Carbon::parse('2026-11-26 08:00:00', 'Asia/Jakarta')->setTimezone('UTC');
        $processes = [];
        $startTime = microtime(true);

        for ($i = 1; $i <= $concurrency; $i++) {
            // Each booking is 15 minutes, scheduled consecutively
            $slot = $baseDate->copy()->addMinutes(($i - 1) * 15)->format('Y-m-d H:i:s');
            $cmd = [
                PHP_BINARY,
                'artisan',
                'booking:worker-book',
                "--tenant-id={$tenant->id}",
                "--service-id={$service->id}",
                "--start-at={$slot}",
                "--resource-ids={$resource->id}",
                "--customer-name=Multi Slot Buyer {$i}",
                '--customer-phone=0813999900'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                '--quantity=1',
            ];

            $process = new Process($cmd, base_path());
            $process->setTimeout(60);
            $processes[] = $process;
        }

        foreach ($processes as $p) {
            $p->start();
        }

        $this->output->write("Menjalankan {$concurrency} proses multi-slot secara simultan");
        while (count(array_filter($processes, fn (Process $p) => $p->isRunning())) > 0) {
            $this->output->write('.');
            usleep(50000);
        }
        $this->output->writeln(' [SELESAI]');

        $totalWallTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        $successCount = 0;
        $deadlockCount = 0;
        $failedCount = 0;
        $latencies = [];

        foreach ($processes as $p) {
            $out = trim($p->getOutput());
            $data = json_decode($out, true);

            if (! is_array($data)) {
                $failedCount++;

                continue;
            }

            $latencies[] = $data['duration_ms'] ?? 0;

            if (! empty($data['success'])) {
                $successCount++;
            } else {
                $failedCount++;
                if (($data['error'] ?? '') === 'DEADLOCK') {
                    $deadlockCount++;
                }
            }
        }

        sort($latencies);
        $countLat = count($latencies);
        $minLat = $countLat > 0 ? $latencies[0] : 0;
        $maxLat = $countLat > 0 ? $latencies[$countLat - 1] : 0;
        $avgLat = $countLat > 0 ? round(array_sum($latencies) / $countLat, 2) : 0;
        $p95Index = (int) ceil(0.95 * $countLat) - 1;
        $p95Lat = $countLat > 0 ? $latencies[max(0, $p95Index)] : 0;

        $dbAllocations = BookingAllocation::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('resource_id', $resource->id)
            ->where('status', 'ACTIVE')
            ->count();

        // Account for the 1 allocation from scenario 1 if in same run
        $passed = ($successCount === $concurrency) && ($deadlockCount === 0);

        $this->line("  ✓ Sukses: {$successCount} / {$concurrency} (Ekspektasi: {$concurrency})");
        $this->line("  ✓ Gagal: {$failedCount} (Ekspektasi: 0)");
        $this->line("  ✓ Deadlock Terdeteksi: {$deadlockCount} (Ekspektasi: 0)");
        $this->line("  ⏱ Waktu Total: {$totalWallTimeMs} ms | Min: {$minLat} ms | Max: {$maxLat} ms | Avg: {$avgLat} ms | P95: {$p95Lat} ms");
        $this->line($passed ? '  👉 Status: [LULUS]' : '  👉 Status: [GAGAL]');

        return [
            'name' => '50 Paralel Multi-Slot',
            'concurrency' => $concurrency,
            'success_count' => $successCount,
            'expected_success' => $concurrency,
            'slot_taken_count' => 0,
            'expected_slot_taken' => 0,
            'deadlock_count' => $deadlockCount,
            'db_allocations' => $dbAllocations,
            'wall_time_ms' => $totalWallTimeMs,
            'avg_ms' => $avgLat,
            'p95_ms' => $p95Lat,
            'passed' => $passed,
        ];
    }

    /**
     * Scenario 3: 25 parallel requests for a class with capacity 20 on same slot.
     * Expectation: Exactly 20 succeed, 5 get CAPACITY_FULL, 0 deadlocks.
     *
     * @return array<string, mixed>
     */
    protected function runClassCapacityScenario(Tenant $tenant, Service $service, Resource $resource, int $totalAttempts = 25, int $capacity = 20): array
    {
        $this->info("\n-------------------------------------------------");
        $this->info("SCENARIO 3: {$totalAttempts} Request Paralel untuk Kelas Kapasitas {$capacity}");
        $this->info("Target: Tepat {$capacity} Sukses, ".($totalAttempts - $capacity).' Gagal (CAPACITY_FULL), 0 Deadlock');
        $this->info('-------------------------------------------------');

        $targetSlot = '2026-11-27 15:00:00';
        $processes = [];
        $startTime = microtime(true);

        for ($i = 1; $i <= $totalAttempts; $i++) {
            $cmd = [
                PHP_BINARY,
                'artisan',
                'booking:worker-book',
                "--tenant-id={$tenant->id}",
                "--service-id={$service->id}",
                "--start-at={$targetSlot}",
                "--resource-ids={$resource->id}",
                "--customer-name=Class Participant {$i}",
                '--customer-phone=0814999900'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                '--quantity=1',
            ];

            $process = new Process($cmd, base_path());
            $process->setTimeout(60);
            $processes[] = $process;
        }

        foreach ($processes as $p) {
            $p->start();
        }

        $this->output->write("Menjalankan {$totalAttempts} proses kelas secara simultan");
        while (count(array_filter($processes, fn (Process $p) => $p->isRunning())) > 0) {
            $this->output->write('.');
            usleep(50000);
        }
        $this->output->writeln(' [SELESAI]');

        $totalWallTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        $successCount = 0;
        $capacityFullCount = 0;
        $deadlockCount = 0;
        $otherFailures = [];
        $latencies = [];

        foreach ($processes as $p) {
            $out = trim($p->getOutput());
            $data = json_decode($out, true);

            if (! is_array($data)) {
                $otherFailures[] = 'Non-JSON Output';

                continue;
            }

            $latencies[] = $data['duration_ms'] ?? 0;

            if (! empty($data['success'])) {
                $successCount++;
            } else {
                $err = $data['error'] ?? 'UNKNOWN';
                if ($err === 'CAPACITY_FULL') {
                    $capacityFullCount++;
                } elseif ($err === 'DEADLOCK') {
                    $deadlockCount++;
                } else {
                    $otherFailures[] = "{$err}: ".($data['message'] ?? '');
                }
            }
        }

        sort($latencies);
        $countLat = count($latencies);
        $minLat = $countLat > 0 ? $latencies[0] : 0;
        $maxLat = $countLat > 0 ? $latencies[$countLat - 1] : 0;
        $avgLat = $countLat > 0 ? round(array_sum($latencies) / $countLat, 2) : 0;
        $p95Index = (int) ceil(0.95 * $countLat) - 1;
        $p95Lat = $countLat > 0 ? $latencies[max(0, $p95Index)] : 0;

        $dbAllocationsSum = BookingAllocation::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('resource_id', $resource->id)
            ->where('status', 'ACTIVE')
            ->where('start_at', Carbon::parse($targetSlot)->setTimezone('UTC')->toDateTimeString())
            ->sum('quantity');

        $expectedExcess = $totalAttempts - $capacity;
        $passed = ($successCount === $capacity) && ($capacityFullCount === $expectedExcess) && ($deadlockCount === 0) && ((int) $dbAllocationsSum === $capacity);

        $this->line("  ✓ Sukses: {$successCount} / {$totalAttempts} (Ekspektasi: {$capacity})");
        $this->line("  ✓ Ditolak CAPACITY_FULL: {$capacityFullCount} / {$totalAttempts} (Ekspektasi: {$expectedExcess})");
        $this->line("  ✓ Deadlock Terdeteksi: {$deadlockCount} (Ekspektasi: 0)");
        $this->line("  ✓ Total Peserta di DB: {$dbAllocationsSum} (Ekspektasi: {$capacity})");
        $this->line("  ⏱ Waktu Total: {$totalWallTimeMs} ms | Min: {$minLat} ms | Max: {$maxLat} ms | Avg: {$avgLat} ms | P95: {$p95Lat} ms");
        $this->line($passed ? '  👉 Status: [LULUS]' : '  👉 Status: [GAGAL]');

        return [
            'name' => "Kapasitas Kelas {$capacity}",
            'concurrency' => $totalAttempts,
            'success_count' => $successCount,
            'expected_success' => $capacity,
            'slot_taken_count' => $capacityFullCount,
            'expected_slot_taken' => $expectedExcess,
            'deadlock_count' => $deadlockCount,
            'db_allocations' => $dbAllocationsSum,
            'wall_time_ms' => $totalWallTimeMs,
            'avg_ms' => $avgLat,
            'p95_ms' => $p95Lat,
            'passed' => $passed,
        ];
    }

    /**
     * Set up isolated test tenant and resources on MySQL.
     *
     * @return array{tenant: Tenant, staff: resource, room: resource, single_service: Service, class_service: Service}
     */
    protected function setupTestEnvironment(): array
    {
        $uniqueTag = Str::random(8);

        $user = User::firstOrCreate(
            ['email' => 'concurrency-tester@example.com'],
            ['name' => 'Concurrency Tester', 'password' => bcrypt('secret123')]
        );

        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => "Concurrency Test Tenant {$uniqueTag}",
            'status' => 'ACTIVE',
            'owner_user_id' => $user->id,
        ]);

        /** @var Business $business */
        $business = Business::create([
            'tenant_id' => $tenant->id,
            'name' => "Concurrency Salon {$uniqueTag}",
            'slug' => "concurrency-salon-{$uniqueTag}",
            'timezone' => 'Asia/Jakarta',
            'settings' => [
                'min_advance_hours' => 0,
                'max_advance_days' => 120,
            ],
        ]);

        // 7 days open 24 hours (00:00:00 to 23:59:59)
        for ($d = 0; $d <= 6; $d++) {
            BusinessHour::create([
                'tenant_id' => $tenant->id,
                'business_id' => $business->id,
                'day_of_week' => $d,
                'is_open' => true,
                'open_time' => '00:00:00',
                'close_time' => '23:59:59',
                'breaks' => [],
            ]);
        }

        $staffType = ResourceType::create([
            'tenant_id' => $tenant->id,
            'code' => 'staff',
            'name' => 'Staff',
            'is_staff' => true,
        ]);

        $spaceType = ResourceType::create([
            'tenant_id' => $tenant->id,
            'code' => 'room',
            'name' => 'Room',
            'is_space' => true,
        ]);

        $staff = Resource::create([
            'tenant_id' => $tenant->id,
            'resource_type_id' => $staffType->id,
            'name' => "Therapist Ultra {$uniqueTag}",
            'state' => 'AVAILABLE',
        ]);

        $room = Resource::create([
            'tenant_id' => $tenant->id,
            'resource_type_id' => $spaceType->id,
            'name' => "Studio Class {$uniqueTag}",
            'state' => 'AVAILABLE',
        ]);

        for ($d = 0; $d <= 6; $d++) {
            ResourceSchedule::create([
                'tenant_id' => $tenant->id,
                'resource_id' => $staff->id,
                'day_of_week' => $d,
                'is_available' => true,
                'start_time' => '00:00:00',
                'end_time' => '23:59:59',
                'breaks' => [],
            ]);
            ResourceSchedule::create([
                'tenant_id' => $tenant->id,
                'resource_id' => $room->id,
                'day_of_week' => $d,
                'is_available' => true,
                'start_time' => '00:00:00',
                'end_time' => '23:59:59',
                'breaks' => [],
            ]);
        }

        $singleService = Service::create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'name' => 'Single Slot Treatment',
            'duration_minutes' => 15,
            'price_idr' => 100000,
            'capacity' => 1,
            'buffer_before' => 0,
            'buffer_after' => 0,
            'is_active' => true,
        ]);

        $classService = Service::create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'name' => 'Group Yoga Class 20',
            'duration_minutes' => 60,
            'price_idr' => 50000,
            'capacity' => 20,
            'buffer_before' => 0,
            'buffer_after' => 0,
            'is_active' => true,
        ]);

        return [
            'tenant' => $tenant,
            'staff' => $staff,
            'room' => $room,
            'single_service' => $singleService,
            'class_service' => $classService,
        ];
    }

    /**
     * Clean up test tenant and data.
     */
    protected function cleanupTestEnvironment(Tenant $tenant): void
    {
        try {
            DB::table('booking_status_history')->where('tenant_id', $tenant->id)->delete();
            DB::table('booking_allocations')->where('tenant_id', $tenant->id)->delete();
            DB::table('bookings')->where('tenant_id', $tenant->id)->delete();
            DB::table('booking_counters')->where('tenant_id', $tenant->id)->delete();
            DB::table('customers')->where('tenant_id', $tenant->id)->delete();
            DB::table('resource_schedules')->where('tenant_id', $tenant->id)->delete();
            DB::table('resources')->where('tenant_id', $tenant->id)->delete();
            DB::table('resource_types')->where('tenant_id', $tenant->id)->delete();
            DB::table('services')->where('tenant_id', $tenant->id)->delete();
            DB::table('business_hours')->where('tenant_id', $tenant->id)->delete();
            DB::table('businesses')->where('tenant_id', $tenant->id)->delete();
            DB::table('tenants')->where('id', $tenant->id)->delete();
        } catch (\Throwable $e) {
            $this->warn('Gagal membersihkan test tenant: '.$e->getMessage());
        }
    }

    /**
     * Render the summary table.
     */
    protected function renderSummaryTable(): void
    {
        $this->info("\n=================================================");
        $this->info('RINGKASAN HASIL BENCHMARK KONKURENSI MYSQL (PRD 204.7)');
        $this->info('=================================================');

        $headers = ['Skenario', 'Req Paralel', 'Sukses', 'Ditolak', 'Deadlock', 'Total (ms)', 'Avg (ms)', 'P95 (ms)', 'Hasil'];
        $rows = [];

        foreach ($this->reportResults as $res) {
            $rows[] = [
                $res['name'],
                $res['concurrency'],
                "{$res['success_count']} (exp: {$res['expected_success']})",
                "{$res['slot_taken_count']} (exp: {$res['expected_slot_taken']})",
                $res['deadlock_count'],
                $res['wall_time_ms'],
                $res['avg_ms'],
                $res['p95_ms'],
                $res['passed'] ? 'PASS' : 'FAIL',
            ];
        }

        $this->table($headers, $rows);
    }
}
