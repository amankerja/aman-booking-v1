<?php

namespace App\Domain\Workflow\Models;

use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $service_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property bool $is_default
 * @property int|null $current_version_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Service|null $service
 * @property-read WorkflowVersion|null $currentVersion
 * @property-read WorkflowVersion|null $draftVersion
 * @property-read Collection<int, WorkflowVersion> $versions
 */
class Workflow extends Model
{
    use BelongsToTenant;

    protected $table = 'workflows';

    protected $fillable = [
        'tenant_id',
        'service_id',
        'name',
        'description',
        'is_active',
        'is_default',
        'current_version_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'current_version_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * @return HasMany<WorkflowVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class, 'workflow_id');
    }

    /**
     * @return BelongsTo<WorkflowVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'current_version_id');
    }

    /**
     * @return HasOne<WorkflowVersion, $this>
     */
    public function draftVersion(): HasOne
    {
        return $this->hasOne(WorkflowVersion::class, 'workflow_id')
            ->where('status', 'DRAFT')
            ->latest('id');
    }

    /**
     * Scope to active workflows.
     *
     * @param  Builder<Workflow>  $query
     * @return Builder<Workflow>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
