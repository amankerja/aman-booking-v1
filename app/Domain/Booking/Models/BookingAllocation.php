<?php

namespace App\Domain\Booking\Models;

use App\Domain\Booking\Enums\AllocationStatus;
use App\Domain\Resource\Models\Resource;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Database\Factories\BookingAllocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $booking_id
 * @property int $resource_id
 * @property string|null $role
 * @property Carbon $start_at
 * @property Carbon $end_at
 * @property AllocationStatus $status
 * @property int $quantity
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BookingAllocation extends Model
{
    /** @use HasFactory<BookingAllocationFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'booking_allocations';

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'resource_id',
        'role',
        'start_at',
        'end_at',
        'status',
        'quantity',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'status' => AllocationStatus::class,
            'quantity' => 'integer',
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

    /**
     * @return BelongsTo<resource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    protected static function newFactory(): BookingAllocationFactory
    {
        return BookingAllocationFactory::new();
    }
}
