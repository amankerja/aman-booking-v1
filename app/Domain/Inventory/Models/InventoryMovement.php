<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Booking\Models\Booking;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $inventory_item_id
 * @property int|null $booking_id
 * @property MovementType $type
 * @property int $quantity
 * @property int $stock_before
 * @property int $stock_after
 * @property int|null $unit_cost_idr
 * @property string|null $notes
 * @property int|null $actor_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read InventoryItem|null $inventoryItem
 * @property-read Booking|null $booking
 * @property-read User|null $actor
 * @property-read Tenant|null $tenant
 */
class InventoryMovement extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'inventory_movements';

    protected $fillable = [
        'tenant_id',
        'inventory_item_id',
        'booking_id',
        'type',
        'quantity',
        'stock_before',
        'stock_after',
        'unit_cost_idr',
        'notes',
        'actor_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'quantity' => 'integer',
            'stock_before' => 'integer',
            'stock_after' => 'integer',
            'unit_cost_idr' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
