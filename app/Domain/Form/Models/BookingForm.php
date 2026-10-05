<?php

namespace App\Domain\Form\Models;

use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Database\Factories\BookingFormFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $service_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_default
 * @property bool $is_active
 * @property int|null $source_template_id
 * @property int|null $source_template_version_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Service|null $service
 * @property-read \App\Domain\Template\Models\SystemTemplate|null $sourceTemplate
 * @property-read \App\Domain\Template\Models\SystemTemplateVersion|null $sourceTemplateVersion
 * @property-read Collection<int, BookingFormField> $fields
 */
class BookingForm extends Model
{
    /** @use HasFactory<BookingFormFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'booking_forms';

    protected $fillable = [
        'tenant_id',
        'service_id',
        'name',
        'slug',
        'description',
        'is_default',
        'is_active',
        'source_template_id',
        'source_template_version_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'source_template_id' => 'integer',
            'source_template_version_id' => 'integer',
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
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return HasMany<BookingFormField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(BookingFormField::class, 'form_id')->orderBy('sort_order', 'asc');
    }

    /**
     * @return BelongsTo<\App\Domain\Template\Models\SystemTemplate, $this>
     */
    public function sourceTemplate(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Template\Models\SystemTemplate::class, 'source_template_id');
    }

    /**
     * @return BelongsTo<\App\Domain\Template\Models\SystemTemplateVersion, $this>
     */
    public function sourceTemplateVersion(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Template\Models\SystemTemplateVersion::class, 'source_template_version_id');
    }

    /**
     * Scope active forms.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function newFactory(): BookingFormFactory
    {
        return BookingFormFactory::new();
    }
}
