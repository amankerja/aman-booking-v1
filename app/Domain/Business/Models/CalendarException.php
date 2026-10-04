<?php

namespace App\Domain\Business\Models;

use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $business_id
 * @property string $type
 * @property CarbonInterface|string $date
 * @property string $title
 * @property bool $is_closed
 * @property string|null $open_time
 * @property string|null $close_time
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CalendarException extends Model
{
    use BelongsToTenant;

    protected $table = 'calendar_exceptions';

    protected $fillable = [
        'tenant_id',
        'business_id',
        'type',
        'date',
        'title',
        'is_closed',
        'open_time',
        'close_time',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'is_closed' => 'boolean',
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
