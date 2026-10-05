<?php

namespace App\Domain\Workflow\Listeners;

use App\Domain\Booking\Events\BookingCreated;
use App\Domain\Booking\Events\BookingStatusChanged;
use App\Domain\Workflow\Services\WorkflowRunnerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkflowTriggerListener implements ShouldQueue
{
    public function __construct(
        protected WorkflowRunnerService $runner
    ) {}

    /**
     * Handle BookingCreated event.
     */
    public function handleBookingCreated(BookingCreated $event): void
    {
        try {
            $booking = $event->booking;
            $payload = [
                'event' => 'booking.created',
                'booking_id' => $booking->id,
                'booking_code' => $booking->code,
                'tenant_id' => $booking->tenant_id,
                'service_id' => $booking->service_id,
                'status' => $booking->status_category->value,
                'payment_status' => $booking->payment_status,
                'total_idr' => $booking->total_idr,
                'start_at' => $booking->start_at->toIso8601String(),
                'metadata' => $event->metadata,
            ];

            $this->runner->handleEvent('booking.created', $payload, $booking);
        } catch (Throwable $e) {
            Log::error("[WorkflowTriggerListener] Failed to handle BookingCreated: {$e->getMessage()}", [
                'exception' => $e,
            ]);
        }
    }

    /**
     * Handle BookingStatusChanged event.
     */
    public function handleBookingStatusChanged(BookingStatusChanged $event): void
    {
        try {
            $booking = $event->booking;
            $payload = [
                'event' => 'booking.status_changed',
                'booking_id' => $booking->id,
                'booking_code' => $booking->code,
                'tenant_id' => $booking->tenant_id,
                'service_id' => $booking->service_id,
                'from_category' => $event->fromCategory,
                'to_category' => $event->toCategory,
                'status' => $event->toCategory,
                'payment_status' => $booking->payment_status,
                'total_idr' => $booking->total_idr,
                'start_at' => $booking->start_at->toIso8601String(),
                'metadata' => $event->metadata,
            ];

            $this->runner->handleEvent('booking.status_changed', $payload, $booking);
        } catch (Throwable $e) {
            Log::error("[WorkflowTriggerListener] Failed to handle BookingStatusChanged: {$e->getMessage()}", [
                'exception' => $e,
            ]);
        }
    }
}
