<?php

namespace App\Domain\Booking\Enums;

enum BookingStatusCategory: string
{
    case DRAFT = 'DRAFT';
    case PENDING = 'PENDING';
    case CONFIRMED = 'CONFIRMED';
    case CHECKED_IN = 'CHECKED_IN';
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
    case NO_SHOW = 'NO_SHOW';
    case EXPIRED = 'EXPIRED';

    /**
     * Determine if this status category actively holds/occupies slots.
     * PRD 213.2: PENDING (when hold is valid), CONFIRMED, CHECKED_IN, IN_PROGRESS.
     */
    public function holdsSlot(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::CONFIRMED,
            self::CHECKED_IN,
            self::IN_PROGRESS,
        ], true);
    }

    /**
     * Determine if this is a terminal state.
     * PRD 213.2: COMPLETED, CANCELLED, EXPIRED are terminal.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::CANCELLED,
            self::EXPIRED,
        ], true);
    }
}
