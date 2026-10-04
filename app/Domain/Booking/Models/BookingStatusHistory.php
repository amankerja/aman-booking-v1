<?php

namespace App\Domain\Booking\Models;

use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $booking_id
 * @property string|null $from_category
 * @property string $to_category
 * @property int|null $actor_id
 * @property string|null $actor_type
 * @property string $source
 * @property string|null $reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BookingStatusHistory extends Model
{
    use BelongsToTenant;

    protected $table = 'booking_status_history';

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'from_category',
        'to_category',
        'actor_id',
        'actor_type',
        'source',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
}
