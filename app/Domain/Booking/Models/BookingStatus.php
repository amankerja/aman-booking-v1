<?php

namespace App\Domain\Booking\Models;

use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Database\Factories\BookingStatusFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $slug
 * @property BookingStatusCategory $category
 * @property string $color
 * @property string|null $badge_bg
 * @property string $icon
 * @property int $sort_order
 * @property bool $is_default
 * @property bool $is_active
 * @property string|null $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Collection<int, Booking> $bookings
 */
class BookingStatus extends Model
{
    /** @use HasFactory<BookingStatusFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'booking_statuses';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'category',
        'color',
        'badge_bg',
        'icon',
        'sort_order',
        'is_default',
        'is_active',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => BookingStatusCategory::class,
            'sort_order' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
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
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'status_id');
    }

    /**
     * Scope active statuses ordered by sort_order.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order', 'asc');
    }

    protected static function newFactory(): BookingStatusFactory
    {
        return BookingStatusFactory::new();
    }
}
