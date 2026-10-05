<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Booking\Exceptions\BookingException;
use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Enums\ReservationStatus;
use App\Domain\Inventory\Enums\StockDeductionMode;
use App\Domain\Inventory\Exceptions\InventoryException;
use App\Domain\Inventory\Models\BookingInventoryItem;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Models\ServiceInventoryItem;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Check if inventory module is enabled for given tenant / business.
     */
    public function isModuleActive(int|Tenant $tenant): bool
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        /** @var Business|null $business */
        $business = Business::withoutGlobalScopes()->where('tenant_id', $tenantId)->first();

        if (! $business) {
            return false;
        }

        $settings = $business->settings ?? [];
        if (! empty($settings['modules']['inventory']) || ! empty($settings['inventory_enabled'])) {
            return true;
        }

        return false;
    }

    /**
     * Record a manual stock mutation.
     *
     * @param  array{
     *     tenant_id: int,
     *     inventory_item_id: int,
     *     type: MovementType|string,
     *     quantity: int,
     *     unit_cost_idr?: int|null,
     *     notes?: string|null,
     *     actor_id?: int|null,
     * }  $data
     *
     * @throws InventoryException
     */
    public function recordMovement(array $data): InventoryMovement
    {
        $quantity = (int) $data['quantity'];
        if ($quantity <= 0) {
            throw InventoryException::invalidQuantity();
        }

        $type = $data['type'] instanceof MovementType ? $data['type'] : MovementType::from($data['type']);
        $tenantId = (int) $data['tenant_id'];
        $itemId = (int) $data['inventory_item_id'];

        return DB::transaction(function () use ($tenantId, $itemId, $type, $quantity, $data) {
            /** @var InventoryItem|null $item */
            $item = InventoryItem::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('id', $itemId)
                ->lockForUpdate()
                ->first();

            if (! $item) {
                throw InventoryException::itemNotFound();
            }

            $multiplier = $type->multiplier();
            $stockBefore = $item->current_stock;
            $stockAfter = $stockBefore + ($multiplier * $quantity);

            if ($stockAfter < 0 && ! $item->allow_negative_stock) {
                throw InventoryException::negativeStockNotAllowed($item->name, $stockAfter);
            }

            $item->current_stock = $stockAfter;
            $item->save();

            /** @var InventoryMovement $movement */
            $movement = InventoryMovement::create([
                'tenant_id' => $tenantId,
                'inventory_item_id' => $item->id,
                'type' => $type,
                'quantity' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'unit_cost_idr' => $data['unit_cost_idr'] ?? null,
                'notes' => $data['notes'] ?? null,
                'actor_id' => $data['actor_id'] ?? null,
            ]);

            Audit::record([
                'tenant_id' => $tenantId,
                'actor_id' => $data['actor_id'] ?? null,
                'actor_type' => 'user',
                'action' => 'inventory.movement',
                'entity_type' => 'inventory_item',
                'entity_id' => $item->id,
                'before' => ['current_stock' => $stockBefore],
                'after' => ['current_stock' => $stockAfter],
                'source' => 'inventory_service',
            ]);

            return $movement;
        });
    }

    /**
     * Reserve or prepare inventory allocation during booking creation.
     * MUST be called inside the atomic booking creation transaction.
     *
     * @return array<int, BookingInventoryItem>
     *
     * @throws BookingException
     */
    public function reserveOrDeductForBooking(Booking $booking): array
    {
        if (! $this->isModuleActive($booking->tenant_id)) {
            return [];
        }

        $serviceId = $booking->service_id ?? ($booking->service_snapshot['service_id'] ?? null);
        if (! $serviceId) {
            return [];
        }

        // Find service inventory mappings
        $mappings = ServiceInventoryItem::where('tenant_id', $booking->tenant_id)
            ->where('service_id', $serviceId)
            ->get();

        if ($mappings->isEmpty()) {
            return [];
        }

        $bookingQty = max(1, (int) ($booking->service_snapshot['quantity'] ?? 1));
        /** @var Business|null $business */
        $business = Business::withoutGlobalScopes()->where('tenant_id', $booking->tenant_id)->first();
        $defaultMode = $business?->settings['inventory']['default_mode'] ?? StockDeductionMode::RESERVE_ON_BOOKING->value;

        // Lock inventory items in ascending ID order to prevent deadlocks (PRD 204.1)
        $itemIds = $mappings->pluck('inventory_item_id')->unique()->sort()->values()->all();
        $lockedItems = InventoryItem::withoutGlobalScopes()
            ->where('tenant_id', $booking->tenant_id)
            ->whereIn('id', $itemIds)
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $createdReservations = [];

        foreach ($mappings as $mapping) {
            /** @var InventoryItem|null $item */
            $item = $lockedItems->get($mapping->inventory_item_id);
            if (! $item) {
                continue;
            }

            $neededQty = $mapping->quantity * $bookingQty;
            $modeStr = $mapping->deduction_mode instanceof StockDeductionMode
                ? $mapping->deduction_mode->value
                : (string) $defaultMode;
            $mode = StockDeductionMode::tryFrom($modeStr) ?? StockDeductionMode::RESERVE_ON_BOOKING;

            // Check availability
            $availableStock = $item->current_stock - $item->reserved_stock;
            if ($availableStock < $neededQty && ! $item->allow_negative_stock) {
                throw BookingException::insufficientInventory(
                    "Stok barang '{$item->name}' tidak mencukupi (Tersedia: {$availableStock}, Dibutuhkan: {$neededQty})."
                );
            }

            if ($mode === StockDeductionMode::RESERVE_ON_BOOKING) {
                $item->reserved_stock += $neededQty;
                $item->save();
            }

            /** @var BookingInventoryItem $reservation */
            $reservation = BookingInventoryItem::create([
                'tenant_id' => $booking->tenant_id,
                'booking_id' => $booking->id,
                'inventory_item_id' => $item->id,
                'quantity' => $neededQty,
                'mode' => $mode,
                'status' => ReservationStatus::RESERVED,
            ]);

            $createdReservations[] = $reservation;
        }

        return $createdReservations;
    }

    /**
     * Handle stock mutations and reservation releases on booking status transitions.
     */
    public function handleBookingStatusTransition(Booking $booking, string $fromCategory, string $toCategory): void
    {
        $reservations = BookingInventoryItem::where('booking_id', $booking->id)->get();
        if ($reservations->isEmpty()) {
            return;
        }

        // Lock all affected inventory items
        $itemIds = $reservations->pluck('inventory_item_id')->unique()->sort()->values()->all();
        $lockedItems = InventoryItem::withoutGlobalScopes()
            ->where('tenant_id', $booking->tenant_id)
            ->whereIn('id', $itemIds)
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($reservations as $res) {
            /** @var InventoryItem|null $item */
            $item = $lockedItems->get($res->inventory_item_id);
            if (! $item) {
                continue;
            }

            // 1. Check-in or In Progress -> Deduct if mode is RESERVE_ON_BOOKING or DEDUCT_ON_SERVICE
            if (in_array($toCategory, ['CHECKED_IN', 'IN_PROGRESS'], true)) {
                if ($res->status === ReservationStatus::RESERVED && in_array($res->mode, [StockDeductionMode::RESERVE_ON_BOOKING, StockDeductionMode::DEDUCT_ON_SERVICE], true)) {
                    $stockBefore = $item->current_stock;
                    $stockAfter = $stockBefore - $res->quantity;

                    if ($res->mode === StockDeductionMode::RESERVE_ON_BOOKING) {
                        $item->reserved_stock = max(0, $item->reserved_stock - $res->quantity);
                    }
                    $item->current_stock = $stockAfter;
                    $item->save();

                    InventoryMovement::create([
                        'tenant_id' => $booking->tenant_id,
                        'inventory_item_id' => $item->id,
                        'booking_id' => $booking->id,
                        'type' => MovementType::CONSUMED_BY_BOOKING,
                        'quantity' => $res->quantity,
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockAfter,
                        'notes' => "Konsumsi booking {$booking->code} ({$toCategory})",
                    ]);

                    $res->status = ReservationStatus::CONSUMED;
                    $res->save();
                }
            }

            // 2. Completed -> Deduct if mode is DEDUCT_ON_COMPLETE (or if not yet consumed)
            if ($toCategory === 'COMPLETED') {
                if ($res->status === ReservationStatus::RESERVED) {
                    $stockBefore = $item->current_stock;
                    $stockAfter = $stockBefore - $res->quantity;

                    if ($res->mode === StockDeductionMode::RESERVE_ON_BOOKING) {
                        $item->reserved_stock = max(0, $item->reserved_stock - $res->quantity);
                    }
                    $item->current_stock = $stockAfter;
                    $item->save();

                    InventoryMovement::create([
                        'tenant_id' => $booking->tenant_id,
                        'inventory_item_id' => $item->id,
                        'booking_id' => $booking->id,
                        'type' => MovementType::CONSUMED_BY_BOOKING,
                        'quantity' => $res->quantity,
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockAfter,
                        'notes' => "Konsumsi booking selesai {$booking->code}",
                    ]);

                    $res->status = ReservationStatus::CONSUMED;
                    $res->save();
                }
            }

            // 3. Cancelled, Expired, No Show -> Release reservation or return consumed stock
            if (in_array($toCategory, ['CANCELLED', 'EXPIRED', 'NO_SHOW'], true)) {
                if ($res->status === ReservationStatus::RESERVED) {
                    if ($res->mode === StockDeductionMode::RESERVE_ON_BOOKING) {
                        $item->reserved_stock = max(0, $item->reserved_stock - $res->quantity);
                        $item->save();
                    }
                    $res->status = ReservationStatus::RELEASED;
                    $res->save();
                } elseif ($res->status === ReservationStatus::CONSUMED) {
                    // Item was already consumed (e.g. cancelled after checkin) -> return stock
                    $stockBefore = $item->current_stock;
                    $stockAfter = $stockBefore + $res->quantity;
                    $item->current_stock = $stockAfter;
                    $item->save();

                    InventoryMovement::create([
                        'tenant_id' => $booking->tenant_id,
                        'inventory_item_id' => $item->id,
                        'booking_id' => $booking->id,
                        'type' => MovementType::RETURN,
                        'quantity' => $res->quantity,
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockAfter,
                        'notes' => "Pengembalian stok dari pembatalan booking {$booking->code}",
                    ]);

                    $res->status = ReservationStatus::RELEASED;
                    $res->save();
                }
            }
        }
    }
}
