<?php

namespace App\Domain\Resource\Models;

use App\Domain\Business\Models\Business;
use App\Domain\Identity\Models\User;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Database\Factories\ResourceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $business_id
 * @property int $resource_type_id
 * @property int|null $group_id
 * @property int|null $user_id
 * @property string $uuid
 * @property string $name
 * @property string|null $code
 * @property int $capacity
 * @property string $visibility
 * @property string $state
 * @property array<string>|null $skills
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read bool $is_archived
 * @property-read ResourceType|null $resourceType
 * @property-read Business|null $business
 * @property-read ResourceGroup|null $group
 * @property-read User|null $user
 */
class Resource extends Model
{
    /** @use HasFactory<ResourceFactory> */
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'resources';

    protected $fillable = [
        'tenant_id',
        'business_id',
        'resource_type_id',
        'group_id',
        'user_id',
        'uuid',
        'name',
        'code',
        'capacity',
        'visibility',
        'state',
        'skills',
        'metadata',
        'archived_at',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'skills' => 'array',
        'metadata' => 'array',
        'archived_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Resource $resource) {
            if (empty($resource->uuid)) {
                $resource->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    /**
     * @return BelongsTo<ResourceType, $this>
     */
    public function resourceType(): BelongsTo
    {
        return $this->belongsTo(ResourceType::class, 'resource_type_id');
    }

    /**
     * @return BelongsTo<ResourceGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ResourceGroup::class, 'group_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<ResourceSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(ResourceSchedule::class, 'resource_id');
    }

    /**
     * @return HasMany<TimeBlock, $this>
     */
    public function timeBlocks(): HasMany
    {
        return $this->hasMany(TimeBlock::class, 'resource_id');
    }

    /**
     * @return HasMany<ServiceResourceRule, $this>
     */
    public function serviceRules(): HasMany
    {
        return $this->hasMany(ServiceResourceRule::class, 'resource_id');
    }

    public function getIsArchivedAttribute(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Check if this resource possesses a given skill (case-insensitive).
     */
    public function hasSkill(string $skill): bool
    {
        if (empty($this->skills)) {
            return false;
        }

        $needle = strtolower(trim($skill));
        foreach ($this->skills as $s) {
            if (strtolower(trim((string) $s)) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if this resource has all given skills.
     *
     * @param  array<string>  $requiredSkills
     */
    public function hasAllSkills(array $requiredSkills): bool
    {
        foreach ($requiredSkills as $skill) {
            if (! $this->hasSkill($skill)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Scope for active resources (not archived and not INACTIVE state).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at')
            ->where('state', '!=', 'INACTIVE');
    }

    /**
     * Scope for available resources (AVAILABLE state and not archived).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereNull('archived_at')
            ->where('state', 'AVAILABLE');
    }

    /**
     * Scope for public visible resources.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublicVisible(Builder $query): Builder
    {
        return $query->where('visibility', 'PUBLIC');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ResourceFactory
    {
        return ResourceFactory::new();
    }
}
