<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Actions\CheckInBooking;
use App\Domain\Booking\Actions\CreateBooking;
use App\Domain\Booking\Enums\AllocationStatus;
use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Exceptions\BookingException;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingAllocation;
use App\Domain\Identity\Models\User;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\TimeBlock;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class BookingService
{
    protected CheckInBooking $checkInBookingAction;

    public function __construct(
        protected CreateBooking $createBookingAction,
        protected BookingStateMachine $stateMachine,
        ?CheckInBooking $checkInBookingAction = null
    ) {
        $this->checkInBookingAction = $checkInBookingAction ?? app(CheckInBooking::class);
    }

    /**
     * Create a new booking via CreateBooking action.
     *
     * @param  array{
     *     tenant: Tenant,
     *     service: Service,
     *     customer: array{name: string, phone: string, email?: string|null},
     *     start_at: CarbonInterface|string,
     *     quantity?: int,
     *     variant_id?: int|null,
     *     addon_ids?: array<int>,
     *     staff_id?: int|null,
     *     resource_ids?: array<int>,
     *     idempotency_key?: string|null,
     *     source?: string,
     *     requires_payment?: bool|null,
     *     actor_id?: int|null,
     *     actor_type?: string,
     * }  $data
     */
    public function create(array $data): Booking
    {
        return $this->createBookingAction->execute($data);
    }

    /**
     * Transition booking status according to PRD 213 state machine.
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
     */
    public function transition(
        Booking $booking,
        BookingStatusCategory $toCategory,
        array $context = []
    ): Booking {
        return $this->stateMachine->transition($booking, $toCategory, $context);
    }

    /**
     * Check in a customer for an appointment.
     * PRD 36, 213.
     *
     * @param  array{
     *     method?: string,
     *     desk_override?: bool,
     *     notes?: string|null,
     *     status_id?: int|string|null
     * }  $options
     * @return array{
     *     success: bool,
     *     already_checked_in: bool,
     *     booking: Booking,
     *     message: string
     * }
     */
    public function checkIn(
        Tenant $tenant,
        Booking|int|string $bookingOrIdentifier,
        User $actor,
        array $options = []
    ): array {
        return $this->checkInBookingAction->execute($tenant, $bookingOrIdentifier, $actor, $options);
    }

    /**
     * Resolve booking and preview check-in window status without executing transition.
     *
     * @return array{
     *     booking: Booking,
     *     window_status: array<string, mixed>,
     *     can_check_in: bool
     * }
     */
    public function previewCheckIn(
        Tenant $tenant,
        Booking|int|string $bookingOrIdentifier
    ): array {
        $booking = $this->checkInBookingAction->resolveBooking($tenant, $bookingOrIdentifier);
        $windowStatus = $booking->getCheckInWindowStatus();

        $canCheckIn = $booking->status_category === BookingStatusCategory::CONFIRMED
            && (bool) $windowStatus['is_open'];

        return [
            'booking' => $booking,
            'window_status' => $windowStatus,
            'can_check_in' => $canCheckIn,
        ];
    }

    /**
     * Reschedule booking to a new time window (PRD 213.1).
     *
     * @param  array{
     *     actor_id?: int|null,
     *     actor_type?: string|null,
     *     source?: string|null,
     *     reason?: string|null
     * }  $context
     *
     * @throws BookingException
     */
    public function reschedule(
        Booking $booking,
        CarbonInterface|string $newStartAt,
        array $context = []
    ): Booking {
        /** @var array<string, mixed> $snapshot */
        $snapshot = $booking->service_snapshot;
        $durationMinutes = (int) ($snapshot['duration_minutes'] ?? 60);
        $bufferBefore = (int) ($snapshot['buffer_before'] ?? 0);
        $bufferAfter = (int) ($snapshot['buffer_after'] ?? 0);

        $newStartUtc = Carbon::parse($newStartAt)->setTimezone('UTC');
        $newEndUtc = $newStartUtc->copy()->addMinutes($durationMinutes);
        $newOccupiedStart = $newStartUtc->copy()->subMinutes($bufferBefore);
        $newOccupiedEnd = $newEndUtc->copy()->addMinutes($bufferAfter);

        return DB::transaction(function () use (
            $booking,
            $newStartUtc,
            $newEndUtc,
            $newOccupiedStart,
            $newOccupiedEnd,
            $context
        ) {
            // Get current allocated resource IDs
            $resourceIds = $booking->allocations()->pluck('resource_id')->all();

            // Lock resources in ascending ID order
            sort($resourceIds);
            $lockedResources = Resource::withoutGlobalScopes()
                ->where('tenant_id', $booking->tenant_id)
                ->whereIn('id', $resourceIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            // Check conflicts on new window
            foreach ($lockedResources as $resource) {
                // Time block
                $hasTimeBlock = TimeBlock::withoutGlobalScopes()
                    ->where('tenant_id', $booking->tenant_id)
                    ->affectingResource($resource->id)
                    ->overlapping($newOccupiedStart, $newOccupiedEnd)
                    ->exists();

                if ($hasTimeBlock) {
                    throw BookingException::resourceUnavailable("Resource {$resource->name} memiliki blok waktu pada jam baru tersebut.");
                }

                // Other bookings' active allocations (exclude current booking's allocations)
                $hasConflict = DB::table('booking_allocations')
                    ->where('tenant_id', $booking->tenant_id)
                    ->where('resource_id', $resource->id)
                    ->where('booking_id', '!=', $booking->id)
                    ->where('status', 'ACTIVE')
                    ->where('start_at', '<', $newOccupiedEnd->toDateTimeString())
                    ->where('end_at', '>', $newOccupiedStart->toDateTimeString())
                    ->exists();

                if ($hasConflict) {
                    throw BookingException::slotTaken("Resource {$resource->name} sudah dipesan customer lain pada jam baru tersebut.");
                }
            }

            // Update booking dates
            $booking->start_at = $newStartUtc;
            $booking->end_at = $newEndUtc;
            $booking->save();

            // Update allocations window
            /** @var BookingAllocation $allocation */
            foreach ($booking->allocations as $allocation) {
                $allocation->update([
                    'start_at' => $newOccupiedStart,
                    'end_at' => $newOccupiedEnd,
                    'status' => AllocationStatus::ACTIVE,
                ]);
            }

            // Fire state machine transition for reschedule
            $context['reschedule'] = true;
            $context['reason'] = $context['reason'] ?? 'Rescheduled booking to new slot';

            return $this->stateMachine->transition($booking, BookingStatusCategory::CONFIRMED, $context);
        });
    }

    /**
     * Cancel a booking (PRD 213.1).
     *
     * @param  array{
     *     actor_id?: int|null,
     *     actor_type?: string|null,
     *     source?: string|null,
     *     reason?: string|null
     * }  $context
     */
    public function cancel(Booking $booking, ?string $reason = null, array $context = []): Booking
    {
        $context['reason'] = $reason ?? $context['reason'] ?? 'Booking cancelled';

        return $this->stateMachine->transition($booking, BookingStatusCategory::CANCELLED, $context);
    }

    /**
     * Expire all pending bookings whose reservation hold has passed (PRD 139, 210 point 4).
     *
     * Processes bookings atomically with row-locking to guard against concurrency races
     * with payment webhooks or manual cashier confirmation.
     *
     * @return int Number of holds successfully expired
     */
    public function expireExpiredHolds(?int $limit = 100): int
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Booking> $expiredBookings */
        $expiredBookings = Booking::withoutGlobalScopes()
            ->where('status_category', BookingStatusCategory::PENDING->value)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', now())
            ->limit($limit ?? 100)
            ->get();

        $count = 0;
        foreach ($expiredBookings as $b) {
            $expired = DB::transaction(function () use ($b) {
                /** @var Booking|null $booking */
                $booking = Booking::withoutGlobalScopes()
                    ->where('id', $b->id)
                    ->lockForUpdate()
                    ->first();

                // Double-check inside transaction lock: only proceed if still PENDING and hold expired
                if (! $booking || $booking->status_category !== BookingStatusCategory::PENDING) {
                    return false;
                }

                if (! $booking->hold_expires_at || $booking->hold_expires_at->isFuture()) {
                    return false;
                }

                // Transition through state machine (which automatically marks allocations as RELEASED)
                $this->stateMachine->transition(
                    $booking,
                    BookingStatusCategory::EXPIRED,
                    [
                        'actor_type' => 'system',
                        'source' => 'system_hold_expiration',
                        'reason' => 'Batas waktu penahanan reservasi (hold) telah kedaluwarsa.',
                    ]
                );

                // Update any UNPAID / PENDING invoice status to FAILED
                $invoices = \App\Domain\Payment\Models\Invoice::where('booking_id', $booking->id)
                    ->whereIn('status', [
                        \App\Domain\Payment\Models\Invoice::STATUS_UNPAID,
                        \App\Domain\Payment\Models\Invoice::STATUS_PENDING,
                    ])
                    ->get();

                foreach ($invoices as $inv) {
                    $inv->status = \App\Domain\Payment\Models\Invoice::STATUS_FAILED;
                    $inv->save();
                }

                return true;
            });

            if ($expired) {
                $count++;
            }
        }

        return $count;
    }
}
