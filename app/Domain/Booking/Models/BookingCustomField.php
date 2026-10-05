<?php

namespace App\Domain\Booking\Models;

use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $booking_id
 * @property string $field_key
 * @property string $field_label
 * @property string $field_type
 * @property string|null $value_text
 * @property array<string, mixed>|null $value_json
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Booking $booking
 */
class BookingCustomField extends Model
{
    use BelongsToTenant;

    protected $table = 'booking_custom_fields';

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'field_key',
        'field_label',
        'field_type',
        'value_text',
        'value_json',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'display_value',
        'download_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_json' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getDisplayValueAttribute(): string
    {
        if ($this->value_json !== null) {
            return implode(', ', array_map(function ($val) {
                return is_scalar($val) ? (string) $val : (string) json_encode($val);
            }, $this->value_json));
        }

        if ($this->field_type === 'file' && $this->value_text) {
            return basename($this->value_text);
        }

        return (string) ($this->value_text ?? '-');
    }

    public function getDownloadUrlAttribute(): ?string
    {
        if ($this->field_type === 'file' && ! empty($this->value_text)) {
            try {
                return route('owner.booking-files.download', ['id' => $this->id]);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
