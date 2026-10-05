<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Enums\AllocationStatus;
use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Exceptions\BookingException;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingStatusHistory;
use App\Domain\Notification\Enums\NotificationEvent;
use App\Domain\Notification\Services\NotificationService;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BookingStateMachine
{
    /**
     * Formal transition table according to PRD 213.1.
     *
     * @var array<string, array<int, string>>
     */
    protected static array $transitions = [
        'DRAFT' => [
            'PENDING',
            'CANCELLED',
        ],
        'PENDING' => [
            'CONFIRMED',
            'CANCELLED',
            'EXPIRED',
        ],
        'CONFIRMED' => [
            'CONFIRMED', // Reschedule event
            'CHECKED_IN',
            'CANCELLED',
            'NO_SHOW',
        ],
        'CHECKED_IN' => [
            'IN_PROGRESS',
            'CANCELLED',
        ],
        'IN_PROGRESS' => [
            'COMPLETED',
        ],
        'NO_SHOW' => [
            'CONFIRMED', // Owner / Manager correction
        ],
        'COMPLETED' => [], // Terminal
        'CANCELLED' => [], // Terminal
        'EXPIRED' => [],   // Terminal
    ];

    /**
     * Check if a transition between two categories is valid.
     */
    public function canTransition(?BookingStatusCategory $from, BookingStatusCategory $to): bool
    {
        if ($from === null) {
            return in_array($to, [
                BookingStatusCategory::DRAFT,
                BookingStatusCategory::PENDING,
                BookingStatusCategory::CONFIRMED,
            ], true);
        }

        $allowed = self::$transitions[$from->value] ?? [];

        return in_array($to->value, $allowed, true);
    }

    /**
     * Execute a status transition on a booking with guards and side effects.
     *
     * @param  array{
     *     actor_id?: int|null,
     *     actor_type?: string|null,
     *     source?: string|null,
     *     reason?: string|null,
     *     reschedule?: bool|null,
     *     status_id?: int|string|null,
     *     bypass_payment_guard?: bool|null
     * }  $context
     *
     * @throws BookingException
     */
    public function transition(
        Booking $booking,
        BookingStatusCategory $toCategory,
        array $context = []
    ): Booking {
        $fromCategory = $booking->status_category;

        // Check if transition is allowed
        if (! $this->canTransition($fromCategory, $toCategory)) {
            throw BookingException::invalidTransition(
                $fromCategory->value,
                $toCategory->value
            );
        }

        // PRD 24 & 213: If transitioning PENDING -> CONFIRMED, check payment requirement guard
        if ($fromCategory === BookingStatusCategory::PENDING && $toCategory === BookingStatusCategory::CONFIRMED) {
            $isBypass = $context['bypass_payment_guard'] ?? false;
            $needsPayment = ($booking->hold_expires_at !== null
                || $booking->deposit_idr > 0
                || ($booking->total_idr > 0 && ! empty($booking->service_snapshot['requires_payment'])));

            if (! $isBypass && $needsPayment && $booking->payment_status === 'UNPAID') {
                throw BookingException::paymentRequired($booking->code);
            }
        }

        $isReschedule = $context['reschedule'] ?? ($fromCategory === BookingStatusCategory::CONFIRMED && $toCategory === BookingStatusCategory::CONFIRMED);

        $updatedBooking = DB::transaction(function () use ($booking, $fromCategory, $toCategory, $context, $isReschedule) {
            $actorId = $context['actor_id'] ?? null;
            $actorType = $context['actor_type'] ?? 'system';
            $source = $context['source'] ?? 'system';
            $reason = $context['reason'] ?? null;

            // Update booking status
            $booking->status_category = $toCategory;
            if (array_key_exists('status_id', $context)) {
                $booking->status_id = $context['status_id'] !== null ? (string) $context['status_id'] : null;
            }
            if ($isReschedule) {
                $booking->reschedule_count++;
            }
            $booking->save();

            // Apply Side Effects (PRD 213.1):
            // 1. If cancelled, expired, or no_show -> release resource allocations
            if (in_array($toCategory, [
                BookingStatusCategory::CANCELLED,
                BookingStatusCategory::EXPIRED,
                BookingStatusCategory::NO_SHOW,
            ], true)) {
                $booking->allocations()->update(['status' => AllocationStatus::RELEASED->value]);
            }

            // 2. If completed -> consume allocations
            if ($toCategory === BookingStatusCategory::COMPLETED) {
                $booking->allocations()->update(['status' => AllocationStatus::CONSUMED->value]);
            }

            // 3. If transitioning back to CONFIRMED (e.g. correction from NO_SHOW) -> re-activate allocations
            if ($fromCategory === BookingStatusCategory::NO_SHOW && $toCategory === BookingStatusCategory::CONFIRMED) {
                $booking->allocations()->update(['status' => AllocationStatus::ACTIVE->value]);
            }

            // 4. If NO_SHOW -> increment customer no_show_count (PRD 213.1)
            if ($toCategory === BookingStatusCategory::NO_SHOW && $booking->customer_id) {
                $booking->customer()->increment('no_show_count');
            }

            // 5. Inventory side effects (PRD 17.3, Phase 4.4)
            try {
                app(\App\Domain\Inventory\Services\InventoryService::class)->handleBookingStatusTransition(
                    $booking,
                    $fromCategory->value,
                    $toCategory->value
                );
            } catch (Throwable $e) {
                Log::warning("Inventory status transition handling failed: {$e->getMessage()}");
            }

            // Record status history (PRD 213.2, 214)
            BookingStatusHistory::create([
                'tenant_id' => $booking->tenant_id,
                'booking_id' => $booking->id,
                'from_category' => $fromCategory->value,
                'to_category' => $toCategory->value,
                'actor_id' => $actorId,
                'actor_type' => $actorType,
                'source' => $source,
                'reason' => $reason,
            ]);

            // Record audit log
            Audit::record([
                'tenant_id' => $booking->tenant_id,
                'actor_id' => $actorId,
                'actor_type' => $actorType,
                'action' => $isReschedule ? 'booking.reschedule' : 'booking.status_change',
                'entity_type' => 'booking',
                'entity_id' => $booking->id,
                'before' => ['status_category' => $fromCategory->value],
                'after' => ['status_category' => $toCategory->value],
                'source' => $source,
            ]);

            return $booking;
        });

        // Non-blocking notification dispatch on status change (Phase 2.4)
        $notifEvent = match (true) {
            $isReschedule => NotificationEvent::BOOKING_RESCHEDULED,
            $toCategory === BookingStatusCategory::CONFIRMED => NotificationEvent::BOOKING_CONFIRMED,
            $toCategory === BookingStatusCategory::CANCELLED => NotificationEvent::BOOKING_CANCELLED,
            $toCategory === BookingStatusCategory::COMPLETED => NotificationEvent::BOOKING_COMPLETED,
            $toCategory === BookingStatusCategory::NO_SHOW => NotificationEvent::NO_SHOW,
            default => null,
        };

        if ($notifEvent !== null) {
            try {
                app(NotificationService::class)->dispatchBookingNotification($updatedBooking, $notifEvent);
            } catch (Throwable $e) {
                Log::warning("Notification dispatch failed in BookingStateMachine: {$e->getMessage()}");
            }
        }

        // Fire domain event for Workflow Runner (PRD 62, 204.5)
        try {
            event(new \App\Domain\Booking\Events\BookingStatusChanged(
                $updatedBooking,
                $fromCategory->value,
                $toCategory->value
            ));
        } catch (Throwable $e) {
            Log::warning("BookingStatusChanged event dispatch failed: {$e->getMessage()}");
        }

        return $updatedBooking;
    }
}
