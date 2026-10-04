<?php

namespace App\Domain\Resource\Models;

use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $service_id
 * @property int|null $resource_type_id
 * @property int|null $resource_id
 * @property int|null $group_id
 * @property array<string>|null $required_skills
 * @property bool $is_required
 * @property string $assignment_mode
 * @property int $quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Service $service
 * @property-read ResourceType|null $resourceType
 * @property-read \App\Domain\Resource\Models\Resource|null $resource
 * @property-read ResourceGroup|null $group
 */
class ServiceResourceRule extends Model
{
    use BelongsToTenant;

    protected $table = 'service_resource_rules';

    public const MODE_CUSTOMER_CHOICE = 'CUSTOMER_CHOICE';

    public const MODE_AUTO_ASSIGN = 'AUTO_ASSIGN';

    public const MODE_OWNER_ASSIGN = 'OWNER_ASSIGN';

    public const MODE_POOL = 'POOL';

    protected $fillable = [
        'tenant_id',
        'service_id',
        'resource_type_id',
        'resource_id',
        'group_id',
        'required_skills',
        'is_required',
        'assignment_mode',
        'quantity',
    ];

    protected $casts = [
        'required_skills' => 'array',
        'is_required' => 'boolean',
        'quantity' => 'integer',
    ];

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
     * @return BelongsTo<ResourceType, $this>
     */
    public function resourceType(): BelongsTo
    {
        return $this->belongsTo(ResourceType::class, 'resource_type_id');
    }

    /**
     * @return BelongsTo<\App\Domain\Resource\Models\Resource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'resource_id');
    }

    /**
     * @return BelongsTo<ResourceGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ResourceGroup::class, 'group_id');
    }
}
