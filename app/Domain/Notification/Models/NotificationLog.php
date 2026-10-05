<?php

namespace App\Domain\Notification\Models;

use App\Domain\Booking\Models\Booking;
use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationEvent;
use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $booking_id
 * @property NotificationChannel $channel
 * @property NotificationEvent $event
 * @property string $recipient
 * @property string|null $subject
 * @property string $body
 * @property NotificationStatus $status
 * @property int $attempts
 * @property int $max_attempts
 * @property string|null $last_error
 * @property Carbon|null $next_retry_at
 * @property Carbon|null $sent_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Booking|null $booking
 */
class NotificationLog extends Model
{
    use BelongsToTenant;

    protected $table = 'notification_logs';

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'channel',
        'event',
        'recipient',
        'subject',
        'body',
        'status',
        'attempts',
        'max_attempts',
        'last_error',
        'next_retry_at',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'event' => NotificationEvent::class,
            'status' => NotificationStatus::class,
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'next_retry_at' => 'datetime',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function isDeadLetter(): bool
    {
        return $this->status === NotificationStatus::DEAD_LETTER;
    }

    public function canRetry(): bool
    {
        return $this->status === NotificationStatus::FAILED || $this->status === NotificationStatus::DEAD_LETTER;
    }

    /**
     * Mark notification as successfully sent.
     */
    public function markSent(): void
    {
        $this->update([
            'status' => NotificationStatus::SENT,
            'sent_at' => Carbon::now(),
            'last_error' => null,
            'next_retry_at' => null,
        ]);
    }

    /**
     * Record a failure attempt and calculate next backoff retry or transition to DEAD_LETTER.
     */
    public function recordFailure(string $errorMessage): void
    {
        $newAttempts = $this->attempts + 1;

        if ($newAttempts >= $this->max_attempts) {
            $this->update([
                'attempts' => $newAttempts,
                'status' => NotificationStatus::DEAD_LETTER,
                'last_error' => $errorMessage,
                'next_retry_at' => null,
            ]);

            return;
        }

        // Exponential backoff: attempt 1 = 1m, attempt 2 = 4m, attempt 3 = 16m, attempt 4 = 64m
        $delayMinutes = (int) min(180, pow(4, $newAttempts - 1));

        $this->update([
            'attempts' => $newAttempts,
            'status' => NotificationStatus::FAILED,
            'last_error' => $errorMessage,
            'next_retry_at' => Carbon::now()->addMinutes($delayMinutes),
        ]);
    }
}
