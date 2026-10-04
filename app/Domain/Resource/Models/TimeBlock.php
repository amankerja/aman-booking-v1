<?php

namespace App\Domain\Resource\Models;

use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $resource_id
 * @property Carbon $start_at
 * @property Carbon $end_at
 * @property string $reason
 * @property bool $is_all_resources
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \App\Domain\Resource\Models\Resource|null $resource
 */
class TimeBlock extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'time_blocks';

    protected $fillable = [
        'tenant_id',
        'resource_id',
        'start_at',
        'end_at',
        'reason',
        'is_all_resources',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'is_all_resources' => 'boolean',
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

    /**
     * Scope to find blocks that overlap with given datetime interval [start, end].
     * (block.start < end AND block.end > start)
     *
     * @param  Builder<TimeBlock>  $query
     * @return Builder<TimeBlock>
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $startAt, CarbonInterface $endAt): Builder
    {
        return $query->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt);
    }

    /**
     * Scope to filter blocks affecting a specific resource (specifically assigned or is_all_resources).
     *
     * @param  Builder<TimeBlock>  $query
     * @return Builder<TimeBlock>
     */
    public function scopeAffectingResource(Builder $query, int $resourceId): Builder
    {
        return $query->where(function (Builder $q) use ($resourceId) {
            $q->where('resource_id', $resourceId)
                ->orWhere('is_all_resources', true)
                ->orWhereNull('resource_id');
        });
    }
}
