<?php

namespace App\Domain\Resource\Models;

use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $resource_id
 * @property int $day_of_week
 * @property bool $is_available
 * @property string $start_time
 * @property string $end_time
 * @property array<array{start: string, end: string, title?: string}>|null $breaks
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \App\Domain\Resource\Models\Resource $resource
 */
class ResourceSchedule extends Model
{
    use BelongsToTenant;

    protected $table = 'resource_schedules';

    protected $fillable = [
        'tenant_id',
        'resource_id',
        'day_of_week',
        'is_available',
        'start_time',
        'end_time',
        'breaks',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_available' => 'boolean',
        'breaks' => 'array',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * @return BelongsTo<\App\Domain\Resource\Models\Resource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'resource_id');
    }
}
