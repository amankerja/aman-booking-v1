<?php

namespace App\Domain\Booking\Actions;

use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Events\BookingCheckedIn;
use App\Domain\Booking\Exceptions\BookingException;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Services\BookingStateMachine;
use App\Domain\Business\Models\Business;
use App\Domain\Identity\Models\User;
use App\Domain\Tenant\Models\BusinessMember;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckInBooking
{
    public function __construct(
        protected BookingStateMachine $stateMachine
    ) {}

    /**
     * Execute check-in for a customer booking.
     * PRD 36, 213, 218.
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
     *
     * @throws AuthorizationException
     * @throws BookingException
     */
    public function execute(
        Tenant $tenant,
        Booking|int|string $bookingOrIdentifier,
        User $actor,
        array $options = []
    ): array {
        // 1. Authorize actor (PRD 36, 212)
        $this->authorizeActor($tenant, $actor);

        // 2. Resolve target booking strictly within tenant
        $booking = $this->resolveBooking($tenant, $bookingOrIdentifier);

        // 3. Idempotency guard: If already checked in, return success without duplicate actions
        if ($booking->status_category === BookingStatusCategory::CHECKED_IN) {
            $formattedTime = $booking->checked_in_at
                ? $booking->checked_in_at->copy()->setTimezone($booking->business_timezone)->format('d M Y, H:i')
                : 'sebelumnya';

            return [
                'success' => true,
                'already_checked_in' => true,
                'booking' => $booking,
                'message' => "Customer {$booking->customer->name} sudah melakukan check-in pada {$formattedTime}.",
            ];
        }

        // 4. Status machine guard: Only CONFIRMED bookings may check in (PRD 213.1)
        if ($booking->status_category !== BookingStatusCategory::CONFIRMED) {
            throw BookingException::checkInNotAllowed(
                $booking->status_category->value,
                "Booking {$booking->code} dengan status {$booking->status_category->value} tidak dapat di-check-in. Hanya booking yang terkonfirmasi (CONFIRMED) yang dapat melakukan check-in."
            );
        }

        // 5. Check-in window validation (unless desk override is active)
        $method = strtoupper((string) ($options['method'] ?? 'MANUAL'));
        if (! in_array($method, ['MANUAL', 'CODE', 'QR'], true)) {
            $method = 'MANUAL';
        }

        $deskOverride = (bool) ($options['desk_override'] ?? false);
        $this->validateCheckInWindow($tenant, $booking, $deskOverride);

        // 6. Execute atomic check-in transition with row lock
        $checkedInBooking = DB::transaction(function () use ($booking, $actor, $method, $deskOverride, $options) {
            /** @var Booking $lockedBooking */
            $lockedBooking = Booking::withoutGlobalScopes()
                ->where('id', $booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Re-check status inside lock
            if ($lockedBooking->status_category === BookingStatusCategory::CHECKED_IN) {
                return $lockedBooking;
            }

            if ($lockedBooking->status_category !== BookingStatusCategory::CONFIRMED) {
                throw BookingException::checkInNotAllowed($lockedBooking->status_category->value);
            }

            $now = Carbon::now();
            $lockedBooking->checked_in_at = $now;
            $lockedBooking->save();

            $reason = $options['notes'] ?? null;
            if ($deskOverride) {
                $reason = trim(($reason ? $reason . ' - ' : '') . 'Check-in override meja depan (di luar jendela standar)');
            }

            // Execute State Machine transition CONFIRMED -> CHECKED_IN
            return $this->stateMachine->transition(
                $lockedBooking,
                BookingStatusCategory::CHECKED_IN,
                [
                    'actor_id' => $actor->id,
                    'actor_type' => 'user',
                    'source' => "check_in_{$method}",
                    'reason' => $reason,
                    'status_id' => $options['status_id'] ?? null,
                ]
            );
        });

        // 7. Dispatch domain event for Workflow Runner & Listeners (PRD 36, 62)
        try {
            event(new BookingCheckedIn(
                $checkedInBooking,
                $actor,
                $method,
                $checkedInBooking->checked_in_at,
                ['desk_override' => $deskOverride]
            ));
        } catch (Throwable $e) {
            Log::warning("BookingCheckedIn event dispatch failed: {$e->getMessage()}");
        }

        // 8. Record audit trail
        Audit::record([
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'actor_type' => 'user',
            'action' => 'booking.check_in',
            'entity_type' => 'booking',
            'entity_id' => $checkedInBooking->id,
            'before' => ['status_category' => BookingStatusCategory::CONFIRMED->value],
            'after' => [
                'status_category' => BookingStatusCategory::CHECKED_IN->value,
                'checked_in_at' => $checkedInBooking->checked_in_at?->toIso8601String(),
                'method' => $method,
                'desk_override' => $deskOverride,
            ],
            'source' => "check_in_{$method}",
        ]);

        return [
            'success' => true,
            'already_checked_in' => false,
            'booking' => $checkedInBooking,
            'message' => "Check-in berhasil untuk customer {$checkedInBooking->customer->name} ({$checkedInBooking->code}).",
        ];
    }

    /**
     * Verify actor has permission to perform customer check-in.
     *
     * @throws AuthorizationException
     */
    protected function authorizeActor(Tenant $tenant, User $actor): void
    {
        $isTenantOwner = (int) $actor->id === (int) $tenant->owner_user_id;
        $isSuperAdmin = (bool) $actor->is_super_admin;

        if ($isTenantOwner || $isSuperAdmin) {
            return;
        }

        // Check member permissions in business_members table
        /** @var BusinessMember|null $member */
        $member = BusinessMember::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $actor->id)
            ->first();

        /** @var array<string> $memberPerms */
        $memberPerms = $member !== null && is_array($member->permissions) ? $member->permissions : [];

        $hasCheckInPermission = in_array('*', $memberPerms, true)
            || in_array('booking.check_in', $memberPerms, true)
            || in_array('bookings.check_in', $memberPerms, true)
            || in_array('booking.update', $memberPerms, true)
            || in_array('bookings.edit', $memberPerms, true)
            || $actor->can('booking.check_in')
            || $actor->can('bookings.check_in');

        if (! $hasCheckInPermission) {
            throw new AuthorizationException('Anda tidak memiliki izin untuk melakukan check-in (membutuhkan permission booking.check_in).');
        }
    }

    /**
     * Resolve booking instance from model, ID, booking code, manage token, or QR payload.
     *
     * @throws BookingException
     */
    public function resolveBooking(Tenant $tenant, Booking|int|string $identifier): Booking
    {
        if ($identifier instanceof Booking) {
            if ((int) $identifier->tenant_id !== (int) $tenant->id) {
                throw new BookingException('BOOKING_NOT_FOUND', 'Booking tidak ditemukan pada tenant ini.', 404);
            }

            return $identifier;
        }

        if (is_int($identifier) || ctype_digit((string) $identifier)) {
            /** @var Booking|null $booking */
            $booking = Booking::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('id', (int) $identifier)
                ->with(['customer', 'service', 'allocations.resource', 'status'])
                ->first();

            if ($booking) {
                return $booking;
            }
        }

        $rawString = trim((string) $identifier);

        // If identifier is a full URL, parse and extract code or manage token
        if (str_starts_with($rawString, 'http://') || str_starts_with($rawString, 'https://')) {
            $path = parse_url($rawString, PHP_URL_PATH) ?? '';
            // Match /booking/manage/{token}
            if (preg_match('~/booking/manage/([^/?#]+)~', $path, $matches)) {
                $rawString = $matches[1];
            } elseif (preg_match('~(BK-[A-Za-z0-9-]+)~i', $path, $matches)) {
                $rawString = $matches[1];
            }
        }

        // Try exact match by booking code (case insensitive)
        /** @var Booking|null $bookingByCode */
        $bookingByCode = Booking::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereRaw('LOWER(code) = ?', [strtolower($rawString)])
            ->with(['customer', 'service', 'allocations.resource', 'status'])
            ->first();

        if ($bookingByCode) {
            return $bookingByCode;
        }

        // Try match by manage token (hashed or plain)
        $hashedToken = hash('sha256', $rawString);
        /** @var Booking|null $bookingByToken */
        $bookingByToken = Booking::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where(function ($query) use ($rawString, $hashedToken) {
                $query->where('manage_token', $rawString)
                    ->orWhere('manage_token', $hashedToken);
            })
            ->with(['customer', 'service', 'allocations.resource', 'status'])
            ->first();

        if ($bookingByToken) {
            return $bookingByToken;
        }

        throw new BookingException(
            'BOOKING_NOT_FOUND',
            "Booking dengan kode atau token '{$rawString}' tidak ditemukan.",
            404
        );
    }

    /**
     * Validate whether current time falls within business check-in window.
     *
     * @throws BookingException
     */
    protected function validateCheckInWindow(Tenant $tenant, Booking $booking, bool $deskOverride): void
    {
        if ($deskOverride) {
            return;
        }

        /** @var Business|null $business */
        $business = Business::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();

        $policies = $business !== null && is_array($business->policies) ? $business->policies : [];
        $settings = $business !== null && is_array($business->settings) ? $business->settings : [];

        // Configurable check-in window (default: 60 minutes before, 60 minutes after start_at)
        $windowBefore = (int) ($policies['check_in']['window_before_minutes']
            ?? $settings['check_in_window_before_minutes']
            ?? 60);

        $windowAfter = (int) ($policies['check_in']['window_after_minutes']
            ?? $settings['check_in_window_after_minutes']
            ?? 60);

        $now = Carbon::now();
        $earliest = $booking->start_at->copy()->subMinutes($windowBefore);
        $latest = $booking->start_at->copy()->addMinutes($windowAfter);

        $tz = $booking->business_timezone ?: 'Asia/Jakarta';

        if ($now->lt($earliest)) {
            $formattedEarliest = $earliest->copy()->setTimezone($tz)->format('d M Y H:i');
            throw BookingException::checkInTooEarly($formattedEarliest);
        }

        if ($now->gt($latest)) {
            $formattedLatest = $latest->copy()->setTimezone($tz)->format('d M Y H:i');
            throw BookingException::checkInTooLate($formattedLatest);
        }
    }
}
