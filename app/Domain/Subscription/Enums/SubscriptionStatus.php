<?php

namespace App\Domain\Subscription\Enums;

enum SubscriptionStatus: string
{
    case TRIAL = 'TRIAL';
    case ACTIVE = 'ACTIVE';
    case PAST_DUE = 'PAST_DUE';
    case GRACE_PERIOD = 'GRACE_PERIOD';
    case EXPIRED = 'EXPIRED';
    case SUSPENDED = 'SUSPENDED';
    case CANCELLED = 'CANCELLED';

    /**
     * Determines whether the subscription allows modifying operational data.
     */
    public function isWritable(): bool
    {
        return match ($this) {
            self::TRIAL, self::ACTIVE => true,
            self::PAST_DUE, self::GRACE_PERIOD, self::EXPIRED, self::SUSPENDED, self::CANCELLED => false,
        };
    }

    /**
     * Determines whether public bookings are accessible for this tenant.
     */
    public function isPublicBookingAccessible(): bool
    {
        return match ($this) {
            self::TRIAL, self::ACTIVE, self::PAST_DUE, self::GRACE_PERIOD => true,
            self::EXPIRED, self::SUSPENDED, self::CANCELLED => false,
        };
    }

    /**
     * Determines if read-only access to dashboard data is permitted.
     */
    public function isReadOnlyPermitted(): bool
    {
        return match ($this) {
            self::TRIAL, self::ACTIVE, self::PAST_DUE, self::GRACE_PERIOD, self::EXPIRED => true,
            self::SUSPENDED, self::CANCELLED => false,
        };
    }
}
