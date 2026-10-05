<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Business\Models\Business;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $business_id
 * @property string $uuid
 * @property string|null $sku
 * @property string $name
 * @property string|null $description
 * @property string|null $category
 * @property string $unit
 * @property int $cost_price_idr
 * @property int $sale_price_idr
 * @property int $initial_stock
 * @property int $current_stock
 * @property int $reserved_stock
 * @property int $minimum_stock
 * @property bool $allow_negative_stock
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read int $available_stock
 * @property-read bool $is_low_stock
 * @property-read Business|null $business
 * @property-read Tenant|null $tenant
 */
class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'inventory_items';

    protected $fillable = [
        'tenant_id',
        'business_id',
        'uuid',
        'sku',
        'name',
        'description',
        'category',
        'unit',
        'cost_price_idr',
        'sale_price_idr',
        'initial_stock',
        'current_stock',
        'reserved_stock',
        'minimum_stock',
        'allow_negative_stock',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_price_idr' => 'integer',
            'sale_price_idr' => 'integer',
            'initial_stock' => 'integer',
            'current_stock' => 'integer',
            'reserved_stock' => 'integer',
            'minimum_stock' => 'integer',
            'allow_negative_stock' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (InventoryItem $item): void {
            if (empty($item->uuid)) {
                $item->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Calculate available stock for new bookings/sales.
     */
    public function getAvailableStockAttribute(): int
    {
        return $this->current_stock - $this->reserved_stock;
    }

    /**
     * Check if stock is at or below minimum threshold.
     */
    public function getIsLowStockAttribute(): bool
    {
        return $this->minimum_stock > 0 && $this->current_stock <= $this->minimum_stock;
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
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'inventory_item_id')->orderBy('created_at', 'desc');
    }

    /**
     * @return HasMany<ServiceInventoryItem, $this>
     */
    public function serviceMappings(): HasMany
    {
        return $this->hasMany(ServiceInventoryItem::class, 'inventory_item_id');
    }

    /**
     * @return HasMany<BookingInventoryItem, $this>
     */
    public function bookingReservations(): HasMany
    {
        return $this->hasMany(BookingInventoryItem::class, 'inventory_item_id');
    }
}
