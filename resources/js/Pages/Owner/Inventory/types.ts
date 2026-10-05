export interface InventoryItem {
    id: number;
    uuid: string;
    sku: string | null;
    name: string;
    description: string | null;
    category: string | null;
    unit: string;
    cost_price_idr: number;
    sale_price_idr: number;
    initial_stock: number;
    current_stock: number;
    reserved_stock: number;
    available_stock: number;
    minimum_stock: number;
    allow_negative_stock: boolean;
    is_active: boolean;
    is_low_stock: boolean;
    created_at: string;
}

export interface InventoryMovement {
    id: number;
    type: string;
    type_label: string;
    multiplier: number;
    quantity: number;
    stock_before: number;
    stock_after: number;
    unit_cost_idr: number | null;
    notes: string | null;
    actor_name: string;
    booking_code: string | null;
    created_at: string;
}

export interface ServiceInventoryMappingItem {
    id: number;
    service_id: number;
    inventory_item_id: number;
    quantity: number;
    deduction_mode: string | null;
    inventory_item?: InventoryItem;
}

export interface ServiceWithInventory {
    id: number;
    name: string;
    duration_minutes: number;
    price_idr: number;
    inventory_items: ServiceInventoryMappingItem[];
}

export interface InventoryStats {
    total_items: number;
    total_valuation_idr: number;
    low_stock_count: number;
    movements_this_month: number;
}
