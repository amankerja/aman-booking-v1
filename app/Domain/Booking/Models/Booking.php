<?php

namespace App\Domain\Booking\Models;

use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Customer\Models\Customer;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property int $customer_id
 * @property int $service_id
 * @property array<string, mixed> $service_snapshot
 * @property Carbon $start_at
 * @property Carbon $end_at
 * @property string $business_timezone
 * @property BookingStatusCategory $status_category
 * @property string|null $status_id
 * @property string $payment_status
 * @property int $total_idr
 * @property int $deposit_idr
 * @property string $source
 * @property Carbon|null $hold_expires_at
 * @property Carbon|null $checked_in_at
 * @property int $reschedule_count
 * @property string|null $manage_token
 * @property Carbon|null $manage_token_expires_at
 * @property int|null $workflow_version_id
 * @property string|null $idempotency_key
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Customer $customer
 * @property-read Service $service
 * @property-read BookingStatus|null $status
 * @property-read Collection<int, BookingAllocation> $allocations
 * @property-read Collection<int, BookingCustomField> $customFields
 * @property-read \App\Domain\Workflow\Models\WorkflowVersion|null $workflowVersion
 * @property-read Collection<int, \App\Domain\Workflow\Models\WorkflowRun> $workflowRuns
 */
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Ephemeral unhashed manage token for initial customer confirmation.
     */
    public ?string $raw_manage_token = null;

    protected $table = 'bookings';

    protected $fillable = [
        'tenant_id',
        'code',
        'customer_id',
        'service_id',
        'service_snapshot',
        'start_at',
        'end_at',
        'business_timezone',
        'status_category',
        'status_id',
        'payment_status',
        'total_idr',
        'deposit_idr',
        'source',
        'hold_expires_at',
        'checked_in_at',
        'reschedule_count',
        'manage_token',
        'manage_token_expires_at',
        'workflow_version_id',
        'idempotency_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_snapshot' => 'array',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'hold_expires_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'manage_token_expires_at' => 'datetime',
            'status_category' => BookingStatusCategory::class,
            'total_idr' => 'integer',
            'deposit_idr' => 'integer',
            'reschedule_count' => 'integer',
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<BookingStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(BookingStatus::class, 'status_id');
    }

    /**
     * @return HasMany<BookingAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(BookingAllocation::class);
    }

    /**
     * @return HasMany<BookingStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class);
    }

    /**
     * @return HasMany<BookingCustomField, $this>
     */
    public function customFields(): HasMany
    {
        return $this->hasMany(BookingCustomField::class);
    }

    /**
     * @return BelongsTo<\App\Domain\Workflow\Models\WorkflowVersion, $this>
     */
    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Workflow\Models\WorkflowVersion::class, 'workflow_version_id');
    }

    /**
     * @return HasMany<\App\Domain\Workflow\Models\WorkflowRun, $this>
     */
    public function workflowRuns(): HasMany
    {
        return $this->hasMany(\App\Domain\Workflow\Models\WorkflowRun::class, 'booking_id')->latest('id');
    }

    /**
     * @return HasMany<\App\Domain\Payment\Models\Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(\App\Domain\Payment\Models\Invoice::class, 'booking_id');
    }

    /**
     * @return HasMany<\App\Domain\Payment\Models\Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(\App\Domain\Payment\Models\Payment::class, 'booking_id');
    }

    /**
     * Check if this booking has an active temporary hold (PRD 139, 210 point 4).
     */
    public function isHoldActive(): bool
    {
        return $this->status_category === BookingStatusCategory::PENDING
            && $this->hold_expires_at !== null
            && $this->hold_expires_at->isFuture();
    }

    /**
     * Check if this booking's temporary hold has expired.
     */
    public function isHoldExpired(): bool
    {
        if ($this->status_category === BookingStatusCategory::EXPIRED) {
            return true;
        }

        return $this->status_category === BookingStatusCategory::PENDING
            && $this->hold_expires_at !== null
            && $this->hold_expires_at->isPast();
    }

    /**
     * Get remaining hold duration in seconds (0 if expired or not set).
     */
    public function getHoldRemainingSeconds(): int
    {
        if (! $this->isHoldActive() || ! $this->hold_expires_at) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($this->hold_expires_at, false));
    }

    /**
     * Scope for bookings that have expired holds pending processing.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Booking>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Booking>
     */
    public function scopeExpiredHolds($query)
    {
        return $query->where('status_category', BookingStatusCategory::PENDING->value)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', now());
    }

    /**
     * Check if customer is checked in.
     */
    public function isCheckedIn(): bool
    {
        return $this->status_category === BookingStatusCategory::CHECKED_IN
            || $this->checked_in_at !== null;
    }

    /**
     * Evaluate check-in window status against current time.
     *
     * @param  int  $windowBefore  Minutes before start_at when check-in opens
     * @param  int  $windowAfter  Minutes after start_at when check-in closes
     * @return array{
     *     is_open: bool,
     *     status: string,
     *     earliest_check_in_at: Carbon,
     *     latest_check_in_at: Carbon,
     *     message: string
     * }
     */
    public function getCheckInWindowStatus(int $windowBefore = 60, int $windowAfter = 60): array
    {
        $earliest = $this->start_at->copy()->subMinutes($windowBefore);
        $latest = $this->start_at->copy()->addMinutes($windowAfter);
        $now = now();

        if ($now->lt($earliest)) {
            $diffMins = (int) ceil($now->diffInMinutes($earliest));

            return [
                'is_open' => false,
                'status' => 'NOT_OPEN',
                'earliest_check_in_at' => $earliest,
                'latest_check_in_at' => $latest,
                'message' => "Check-in belum dibuka. Dibuka {$diffMins} menit lagi.",
            ];
        }

        if ($now->gt($latest)) {
            $diffMins = (int) ceil($latest->diffInMinutes($now));

            return [
                'is_open' => false,
                'status' => 'CLOSED',
                'earliest_check_in_at' => $earliest,
                'latest_check_in_at' => $latest,
                'message' => "Jendela check-in telah berakhir ({$diffMins} menit yang lalu). Butuh override meja depan.",
            ];
        }

        return [
            'is_open' => true,
            'status' => $now->gt($this->start_at) ? 'LATE' : 'OPEN',
            'earliest_check_in_at' => $earliest,
            'latest_check_in_at' => $latest,
            'message' => 'Jendela check-in sedang aktif.',
        ];
    }

    protected static function newFactory(): BookingFactory
    {
        return BookingFactory::new();
    }
}
