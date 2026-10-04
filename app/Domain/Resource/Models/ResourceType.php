<?php

namespace App\Domain\Resource\Models;

use App\Domain\Tenant\Models\Tenant;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $tenant_id
 * @property string $code
 * @property string $name
 * @property string $icon
 * @property bool $is_staff
 * @property bool $is_space
 * @property bool $is_equipment
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ResourceType extends Model
{
    protected $table = 'resource_types';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'icon',
        'is_staff',
        'is_space',
        'is_equipment',
        'is_active',
    ];

    protected $casts = [
        'is_staff' => 'boolean',
        'is_space' => 'boolean',
        'is_equipment' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * @return HasMany<\App\Domain\Resource\Models\Resource, $this>
     */
    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class, 'resource_type_id');
    }

    /**
     * Scope to retrieve global default types and tenant custom types.
     *
     * @param  Builder<ResourceType>  $query
     * @return Builder<ResourceType>
     */
    public function scopeForCurrentTenant(Builder $query, ?int $tenantId = null): Builder
    {
        $tenantId = $tenantId ?? TenantContext::getTenantId();

        return $query->where(function (Builder $q) use ($tenantId) {
            $q->whereNull('tenant_id');
            if ($tenantId) {
                $q->orWhere('tenant_id', $tenantId);
            }
        })->where('is_active', true);
    }
}
