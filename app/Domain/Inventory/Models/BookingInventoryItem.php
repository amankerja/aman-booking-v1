<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Booking\Models\Booking;
use App\Domain\Inventory\Enums\ReservationStatus;
use App\Domain\Inventory\Enums\StockDeductionMode;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $booking_id
 * @property int $inventory_item_id
 * @property int $quantity
 * @property StockDeductionMode $mode
 * @property ReservationStatus $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking|null $booking
 * @property-read InventoryItem|null $inventoryItem
 * @property-read Tenant|null $tenant
 */
class BookingInventoryItem extends Model
{
    use BelongsToTenant;

    protected $table = 'booking_inventory_items';

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'inventory_item_id',
        'quantity',
        'mode',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'mode' => StockDeductionMode::class,
            'status' => ReservationStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
