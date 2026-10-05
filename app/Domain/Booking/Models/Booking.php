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

    protected static function newFactory(): BookingFactory
    {
        return BookingFactory::new();
    }
}
