<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\StockDeductionMode;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $service_id
 * @property int $inventory_item_id
 * @property int $quantity
 * @property StockDeductionMode|null $deduction_mode
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Service|null $service
 * @property-read InventoryItem|null $inventoryItem
 * @property-read Tenant|null $tenant
 */
class ServiceInventoryItem extends Model
{
    use BelongsToTenant;

    protected $table = 'service_inventory_items';

    protected $fillable = [
        'tenant_id',
        'service_id',
        'inventory_item_id',
        'quantity',
        'deduction_mode',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'deduction_mode' => StockDeductionMode::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
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
