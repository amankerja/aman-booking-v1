<?php

namespace App\Console\Commands;

use App\Domain\Booking\Actions\CreateBooking;
use App\Domain\Booking\Exceptions\BookingException;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Throwable;

class BookingWorkerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'booking:worker-book
        {--tenant-id= : Tenant ID}
        {--service-id= : Service ID}
        {--start-at= : Slot start time (ISO/UTC)}
        {--customer-name= : Customer Name}
        {--customer-phone= : Customer Phone}
        {--resource-ids= : Comma-separated resource IDs}
        {--quantity=1 : Booking quantity}
        {--idempotency-key= : Optional idempotency key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Worker process that attempts a single booking under concurrency testing';

    public function handle(CreateBooking $createBooking): int
    {
        $tenantId = (int) $this->option('tenant-id');
        $serviceId = (int) $this->option('service-id');
        $startAt = (string) $this->option('start-at');
        $customerName = (string) ($this->option('customer-name') ?: 'Concurrent Tester');
        $customerPhone = (string) ($this->option('customer-phone') ?: '0812'.rand(10000000, 99999999));
        $quantity = (int) ($this->option('quantity') ?: 1);
        $idempotencyKey = $this->option('idempotency-key') ? (string) $this->option('idempotency-key') : null;

        $rawResourceIds = (string) $this->option('resource-ids');
        $resourceIds = ! empty($rawResourceIds)
            ? array_map('intval', explode(',', $rawResourceIds))
            : [];

        $startTime = microtime(true);

        try {
            /** @var Tenant $tenant */
            $tenant = Tenant::withoutGlobalScopes()->findOrFail($tenantId);
            TenantContext::setTenant($tenant);

            /** @var Service $service */
            $service = Service::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($serviceId);

            $booking = $createBooking->execute([
                'tenant' => $tenant,
                'service' => $service,
                'customer' => [
                    'name' => $customerName,
                    'phone' => $customerPhone,
                ],
                'start_at' => $startAt,
                'resource_ids' => $resourceIds,
                'quantity' => $quantity,
                'idempotency_key' => $idempotencyKey,
            ]);

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            $this->line(json_encode([
                'success' => true,
                'booking_id' => $booking->id,
                'code' => $booking->code,
                'duration_ms' => $durationMs,
            ], JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        } catch (BookingException $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            $this->line(json_encode([
                'success' => false,
                'error' => $e->getErrorCode(),
                'message' => $e->getMessage(),
                'duration_ms' => $durationMs,
            ], JSON_THROW_ON_ERROR));

            return Command::FAILURE;
        } catch (Throwable $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);
            $isDeadlock = str_contains($e->getMessage(), 'Deadlock') || str_contains($e->getMessage(), '1213');

            $this->line(json_encode([
                'success' => false,
                'error' => $isDeadlock ? 'DEADLOCK' : 'UNEXPECTED_ERROR',
                'message' => $e->getMessage(),
                'duration_ms' => $durationMs,
            ], JSON_THROW_ON_ERROR));

            return Command::INVALID;
        }
    }
}
