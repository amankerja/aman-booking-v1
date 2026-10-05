export interface CustomerSummary {
    id: number;
    name: string;
    phone_e164: string;
    email: string | null;
    no_show_count?: number;
    notes?: string | null;
}

export interface ResourceSummary {
    id: number;
    name: string;
    code: string;
    resource_type_id: number;
    state: string;
    resource_type?: {
        id: number;
        name: string;
        code: string;
    };
}

export interface ServiceVariantSummary {
    id: number;
    name: string;
    price_idr: number;
    duration_minutes: number;
}

export interface ServiceAddonSummary {
    id: number;
    name: string;
    price_idr: number;
    duration_minutes: number;
}

export interface ServiceSummary {
    id: number;
    name: string;
    slug: string;
    price_idr: number;
    duration_minutes: number;
    buffer_before: number;
    buffer_after: number;
    variants: ServiceVariantSummary[];
    addons: ServiceAddonSummary[];
}

export interface BookingAllocationSummary {
    id: number;
    booking_id: number;
    resource_id: number;
    role: string | null;
    start_at: string;
    end_at: string;
    status: string;
    resource?: ResourceSummary;
}

export interface BookingStatusHistorySummary {
    id: number;
    booking_id: number;
    from_category: string | null;
    to_category: string;
    actor_id: number | null;
    actor_type: string | null;
    source: string;
    reason: string | null;
    created_at: string;
}

export interface BookingStatusItem {
    id: number;
    tenant_id: number;
    name: string;
    slug: string;
    category:
        | 'PENDING'
        | 'CONFIRMED'
        | 'CHECKED_IN'
        | 'IN_PROGRESS'
        | 'COMPLETED'
        | 'CANCELLED'
        | 'NO_SHOW'
        | 'EXPIRED'
        | 'DRAFT';
    color: string;
    badge_bg?: string | null;
    icon: string;
    sort_order: number;
    is_default: boolean;
    is_active: boolean;
    description?: string | null;
}

export interface BookingItem {
    id: number;
    tenant_id: number;
    code: string;
    customer_id: number;
    service_id: number;
    service_snapshot: {
        name: string;
        duration_minutes: number;
        price_idr: number;
        buffer_before?: number;
        buffer_after?: number;
    };
    start_at: string;
    end_at: string;
    business_timezone: string;
    status_category:
        | 'PENDING'
        | 'CONFIRMED'
        | 'CHECKED_IN'
        | 'IN_PROGRESS'
        | 'COMPLETED'
        | 'CANCELLED'
        | 'NO_SHOW'
        | 'EXPIRED'
        | 'DRAFT';
    status_id: string | number | null;
    status?: BookingStatusItem;
    payment_status: 'UNPAID' | 'PAID' | 'PARTIAL' | 'REFUNDED';
    total_idr: number;
    deposit_idr: number;
    source: string;
    hold_expires_at?: string | null;
    checked_in_at?: string | null;
    reschedule_count: number;
    created_at: string;
    updated_at: string;
    customer?: CustomerSummary;
    service?: ServiceSummary;
    allocations?: BookingAllocationSummary[];
    status_history?: BookingStatusHistorySummary[];
    custom_fields?: BookingCustomFieldItem[];
}

export interface BookingCustomFieldItem {
    id: number;
    booking_id: number;
    field_key: string;
    field_label: string;
    field_type: string;
    value_text: string | null;
    value_json: Record<string, unknown> | unknown[] | null;
    display_value?: string;
    download_url?: string | null;
}

export interface PaginatedBookings {
    data: BookingItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

export interface SlotItem {
    date: string;
    start_time: string;
    end_time: string;
    start_at: string;
    end_at: string;
    duration_minutes: number;
    capacity: number;
    available_capacity: number;
    is_available: boolean;
    reason_code: string | null;
    reason_message: string | null;
    available_staff: Array<{ id: number; name: string }>;
    available_resources: Array<{
        id: number;
        name: string;
        type: string | null;
    }>;
}

export interface BookingFilters {
    view: 'table' | 'calendar' | 'kanban';
    search: string;
    status: string;
    resource_id: number | null;
    service_id: number | null;
    date_from: string | null;
    date_to: string | null;
}

export interface FormFieldOption {
    label: string;
    value: string;
}

export interface FormVisibilityCondition {
    field?: string;
    field_key?: string;
    operator: string;
    value: unknown;
}

export interface FormFieldItem {
    id: number;
    booking_form_id?: number;
    form_id?: number;
    field_key: string;
    label: string;
    type: string;
    placeholder?: string | null;
    help_text?: string | null;
    is_required: boolean;
    sort_order: number;
    options?: FormFieldOption[] | null;
    validation_rules?: Record<string, unknown> | null;
    visibility_conditions?: FormVisibilityCondition[] | FormVisibilityCondition | null;
    is_active?: boolean;
}

export interface FormItem {
    id: number;
    tenant_id?: number;
    name: string;
    slug?: string;
    description?: string | null;
    service_id?: number | null;
    is_default: boolean;
    is_active?: boolean;
    fields: FormFieldItem[];
}

