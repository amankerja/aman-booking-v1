<?php

namespace App\Domain\Template\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Platform-wide template (no tenant_id). Tenants only ever receive copies.
 *
 * @property int $id
 * @property string $kind
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property string|null $business_type
 * @property bool $is_active
 * @property int|null $latest_version_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read SystemTemplateVersion|null $latestVersion
 * @property-read Collection<int, SystemTemplateVersion> $versions
 */
class SystemTemplate extends Model
{
    public const KIND_WORKFLOW = 'workflow';

    public const KIND_FORM = 'form';

    protected $table = 'system_templates';

    protected $fillable = [
        'kind',
        'key',
        'name',
        'description',
        'business_type',
        'is_active',
        'latest_version_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'latest_version_id' => 'integer',
        ];
    }

    /**
     * Latest PUBLISHED version. Drafts are never offered to tenants.
     *
     * @return BelongsTo<SystemTemplateVersion, $this>
     */
    public function latestVersion(): BelongsTo
    {
        return $this->belongsTo(SystemTemplateVersion::class, 'latest_version_id');
    }

    /**
     * @return HasMany<SystemTemplateVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(SystemTemplateVersion::class, 'system_template_id');
    }
}
