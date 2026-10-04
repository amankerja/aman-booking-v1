<?php

namespace App\Domain\Service\Models;

use App\Domain\Business\Models\Business;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $business_id
 * @property int|null $category_id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $image_path
 * @property float|string $price_idr
 * @property string $duration_type
 * @property int $duration_minutes
 * @property array<string, mixed>|null $duration_rule
 * @property int $buffer_before
 * @property int $buffer_after
 * @property int $capacity
 * @property bool $is_active
 * @property bool $is_featured
 * @property array<string, mixed>|null $rules
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read int $total_duration
 * @property-read string|null $image_url
 * @property-read bool $is_archived
 */
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'services';

    protected $fillable = [
        'tenant_id',
        'business_id',
        'category_id',
        'uuid',
        'name',
        'slug',
        'description',
        'image_path',
        'price_idr',
        'duration_type',
        'duration_minutes',
        'duration_rule',
        'buffer_before',
        'buffer_after',
        'capacity',
        'is_active',
        'is_featured',
        'rules',
        'archived_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_idr' => 'decimal:2',
            'duration_minutes' => 'integer',
            'duration_rule' => 'array',
            'buffer_before' => 'integer',
            'buffer_after' => 'integer',
            'capacity' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'rules' => 'array',
            'archived_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Service $service): void {
            if (empty($service->uuid)) {
                $service->uuid = (string) Str::uuid();
            }

            if (empty($service->slug)) {
                $service->slug = Str::slug($service->name);
            }
        });
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

    /**
     * @return BelongsTo<ServiceCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    /**
     * @return HasMany<ServiceVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ServiceVariant::class, 'service_id')->orderBy('order');
    }

    /**
     * @return HasMany<ServiceAddon, $this>
     */
    public function addons(): HasMany
    {
        return $this->hasMany(ServiceAddon::class, 'service_id')->orderBy('order');
    }

    /**
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    public function scopeUnarchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    /**
     * Total duration occupied by the service including preparation & cleanup buffers.
     */
    public function getTotalDurationAttribute(): int
    {
        return (int) $this->duration_minutes + (int) $this->buffer_before + (int) $this->buffer_after;
    }

    /**
     * Check if service is archived.
     */
    public function getIsArchivedAttribute(): bool
    {
        return ! is_null($this->archived_at);
    }

    /**
     * Accessible URL for uploaded service image.
     */
    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return Storage::disk('public')->url($this->image_path);
    }
}
