<?php

namespace App\Domain\Form\Models;

use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Database\Factories\BookingFormFieldFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $form_id
 * @property string $field_key
 * @property string $type
 * @property string $label
 * @property string|null $placeholder
 * @property string|null $help_text
 * @property bool $is_required
 * @property string|null $default_value
 * @property array<string, mixed>|null $options
 * @property array<string, mixed>|null $validation_rules
 * @property array<string, mixed>|null $visibility_conditions
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read BookingForm $form
 */
class BookingFormField extends Model
{
    /** @use HasFactory<BookingFormFieldFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'booking_form_fields';

    protected $fillable = [
        'tenant_id',
        'form_id',
        'field_key',
        'type',
        'label',
        'placeholder',
        'help_text',
        'is_required',
        'default_value',
        'options',
        'validation_rules',
        'visibility_conditions',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'options' => 'array',
            'validation_rules' => 'array',
            'visibility_conditions' => 'array',
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
     * @return BelongsTo<BookingForm, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(BookingForm::class, 'form_id');
    }

    /**
     * Scope active fields.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order', 'asc');
    }

    protected static function newFactory(): BookingFormFieldFactory
    {
        return BookingFormFieldFactory::new();
    }
}
