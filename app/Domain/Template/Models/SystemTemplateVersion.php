<?php

namespace App\Domain\Template\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int $system_template_id
 * @property string $version
 * @property string $status
 * @property string|null $changelog
 * @property array<string, mixed> $payload
 * @property int|null $created_by
 * @property Carbon|null $published_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read SystemTemplate $template
 */
class SystemTemplateVersion extends Model
{
    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_PUBLISHED = 'PUBLISHED';

    protected $table = 'system_template_versions';

    protected $fillable = [
        'system_template_id',
        'version',
        'status',
        'changelog',
        'payload',
        'created_by',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // A published version is a contract with every tenant that installed it.
        static::updating(function (SystemTemplateVersion $version): void {
            if ($version->getOriginal('status') === self::STATUS_PUBLISHED
                && $version->isDirty(['payload', 'version', 'status'])) {
                throw new LogicException('Versi template yang sudah dipublikasikan bersifat immutable.');
            }
        });

        static::deleting(function (SystemTemplateVersion $version): void {
            if ($version->status === self::STATUS_PUBLISHED) {
                throw new LogicException('Versi template yang sudah dipublikasikan tidak dapat dihapus.');
            }
        });
    }

    /**
     * @return BelongsTo<SystemTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(SystemTemplate::class, 'system_template_id');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}
