<?php

namespace App\Domain\Business\Models;

use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $business_id
 * @property int $day_of_week
 * @property bool $is_open
 * @property string $open_time
 * @property string $close_time
 * @property array<int, array<string, mixed>>|null $breaks
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BusinessHour extends Model
{
    use BelongsToTenant;

    protected $table = 'business_hours';

    protected $fillable = [
        'tenant_id',
        'business_id',
        'day_of_week',
        'is_open',
        'open_time',
        'close_time',
        'breaks',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_open' => 'boolean',
            'breaks' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
