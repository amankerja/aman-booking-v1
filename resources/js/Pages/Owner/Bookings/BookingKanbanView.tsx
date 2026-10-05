import { Link } from '@inertiajs/react';
import {
    AlertCircle,
    Calendar,
    CheckCircle2,
    CheckCheck,
    CircleDot,
    Clock,
    CreditCard,
    Play,
    SlidersHorizontal,
    User,
    UserCheck,
    UserX,
    XCircle,
} from 'lucide-react';
import React, { useState } from 'react';
import { BookingItem, BookingStatusItem } from './types';

interface BookingKanbanViewProps {
    bookings: BookingItem[];
    statuses?: BookingStatusItem[];
    onOpenDetail: (booking: BookingItem) => void;
    onTransitionStatus: (
        booking: BookingItem,
        targetCategory: string,
        targetStatusId?: number | string
    ) => Promise<boolean>;
}

interface ComputedKanbanColumn {
    id: string | number;
    title: string;
    category: string;
    statusId?: number;
    color: string;
    badgeBg: string;
    icon: string;
}

export const renderKanbanIcon = (
    iconName?: string,
    className = 'h-3.5 w-3.5'
) => {
    switch (iconName?.toLowerCase()) {
        case 'clock':
        case 'menunggu':
            return <Clock className={className} />;
        case 'check-circle':
        case 'checkcircle':
        case 'terkonfirmasi':
            return <CheckCircle2 className={className} />;
        case 'user-check':
        case 'usercheck':
        case 'hadir':
            return <UserCheck className={className} />;
        case 'play':
        case 'in-progress':
        case 'proses':
            return <Play className={className} />;
        case 'check-check':
        case 'checkcheck':
        case 'selesai':
            return <CheckCheck className={className} />;
        case 'x-circle':
        case 'xcircle':
        case 'batal':
            return <XCircle className={className} />;
        case 'user-x':
        case 'userx':
        case 'no-show':
            return <UserX className={className} />;
        case 'alert-circle':
            return <AlertCircle className={className} />;
        case 'calendar':
            return <Calendar className={className} />;
        default:
            return <CircleDot className={className} />;
    }
};

const DEFAULT_FALLBACK_COLUMNS: ComputedKanbanColumn[] = [
    {
        id: 'pending',
        title: 'Menunggu',
        category: 'PENDING',
        color: '#64748b',
        badgeBg: 'bg-slate-100 text-slate-700',
        icon: 'clock',
    },
    {
        id: 'confirmed',
        title: 'Terkonfirmasi',
        category: 'CONFIRMED',
        color: '#2563eb',
        badgeBg: 'bg-blue-100 text-blue-700',
        icon: 'check-circle',
    },
    {
        id: 'in_progress',
        title: 'Dalam Pelayanan',
        category: 'IN_PROGRESS',
        color: '#d97706',
        badgeBg: 'bg-amber-100 text-amber-800',
        icon: 'play',
    },
    {
        id: 'completed',
        title: 'Selesai',
        category: 'COMPLETED',
        color: '#059669',
        badgeBg: 'bg-emerald-100 text-emerald-800',
        icon: 'check-check',
    },
    {
        id: 'cancelled',
        title: 'Batal / No-Show',
        category: 'CANCELLED',
        color: '#dc2626',
        badgeBg: 'bg-rose-100 text-rose-800',
        icon: 'x-circle',
    },
];

export const BookingKanbanView: React.FC<BookingKanbanViewProps> = ({
    bookings,
    statuses,
    onOpenDetail,
    onTransitionStatus,
}) => {
    // Build active columns from custom statuses or fallback defaults
    const activeColumns: ComputedKanbanColumn[] =
        statuses && statuses.length > 0
            ? statuses
                  .filter((s) => s.is_active)
                  .map((s) => ({
                      id: s.id,
                      statusId: s.id,
                      title: s.name,
                      category: s.category,
                      color: s.color || '#2563eb',
                      badgeBg: s.badge_bg || 'bg-blue-100 text-blue-700',
                      icon: s.icon || 'circle',
                  }))
            : DEFAULT_FALLBACK_COLUMNS;

    // Mobile active column selector (PRD: "Kanban satu kolom aktif di HP + segmented control")
    const [mobileActiveColId, setMobileActiveColId] = useState<string | number>(
        activeColumns[0]?.id ?? 'pending'
    );
    const [draggingId, setDraggingId] = useState<number | null>(null);

    const handleDragStart = (e: React.DragEvent, b: BookingItem) => {
        e.dataTransfer.setData('text/plain', String(b.id));
        setDraggingId(b.id);
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
    };

    const handleDrop = async (
        e: React.DragEvent,
        col: ComputedKanbanColumn
    ) => {
        e.preventDefault();
        const bookingIdStr = e.dataTransfer.getData('text/plain');
        setDraggingId(null);

        if (!bookingIdStr) return;
        const b = bookings.find((item) => item.id === Number(bookingIdStr));
        if (!b) return;

        // Check if already in the target column
        if (col.statusId && b.status_id === col.statusId) return;
        if (!col.statusId && b.status_category === col.category) return;

        // Execute transition with validation guard and optimistic rollback if rejected
        await onTransitionStatus(b, col.category, col.statusId);
    };

    const getBookingsForColumn = (col: ComputedKanbanColumn): BookingItem[] => {
        return bookings.filter((b) => {
            if (col.statusId) {
                if (b.status_id !== null && b.status_id !== undefined) {
                    return String(b.status_id) === String(col.statusId);
                }
                // Fallback: If booking has no status_id yet, map by system category
                return b.status_category === col.category;
            }

            // Fallback column (if no custom status id)
            if (col.id === 'cancelled') {
                return (
                    b.status_category === 'CANCELLED' ||
                    b.status_category === 'NO_SHOW'
                );
            }
            if (col.id === 'in_progress') {
                return (
                    b.status_category === 'IN_PROGRESS' ||
                    b.status_category === 'CHECKED_IN'
                );
            }
            return b.status_category === col.category;
        });
    };

    return (
        <div className="space-y-3">
            {/* Top Toolbar: Kanban Info & Quick Link to Custom Status Settings */}
            <div className="flex items-center justify-between px-1">
                <div className="flex items-center gap-1.5 text-xs text-slate-500">
                    <span className="font-medium text-slate-700">
                        Papan Alur Kanban:
                    </span>
                    <span>
                        Geser (drag & drop) kartu untuk memperbarui status alur.
                    </span>
                </div>
                <Link
                    href={route('owner.settings.statuses.index')}
                    className="flex items-center gap-1 rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600 transition-colors hover:border-blue-500 hover:text-blue-600"
                >
                    <SlidersHorizontal className="h-3 w-3" />
                    <span>Kustomisasi Status</span>
                </Link>
            </div>

            {/* Mobile Column Segmented Control (PRD 3.1: "Kanban mobile: satu kolom aktif + segmented control") */}
            <div className="flex gap-1 overflow-x-auto rounded-[10px] border border-slate-200 bg-white p-1 sm:hidden">
                {activeColumns.map((col) => {
                    const count = getBookingsForColumn(col).length;
                    const isActive =
                        String(mobileActiveColId) === String(col.id);

                    return (
                        <button
                            key={String(col.id)}
                            type="button"
                            onClick={() => setMobileActiveColId(col.id)}
                            className={`flex min-h-[36px] items-center gap-1.5 rounded-[8px] px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors ${
                                isActive
                                    ? 'bg-blue-600 text-white shadow-xs'
                                    : 'text-slate-600 hover:bg-slate-100'
                            }`}
                        >
                            <span
                                className={
                                    isActive ? 'text-white' : 'text-slate-500'
                                }
                            >
                                {renderKanbanIcon(col.icon, 'h-3.5 w-3.5')}
                            </span>
                            <span>{col.title}</span>
                            <span
                                className={`py-0.2 rounded-full px-1.5 text-[10px] font-semibold ${
                                    isActive
                                        ? 'bg-blue-700 text-white'
                                        : 'bg-slate-200 text-slate-700'
                                }`}
                            >
                                {count}
                            </span>
                        </button>
                    );
                })}
            </div>

            {/* Kanban Columns Grid (Responsive: 1 col on mobile, multi-col on larger screens) */}
            <div
                className="grid grid-cols-1 items-start gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5"
                style={{
                    gridTemplateColumns:
                        activeColumns.length > 5
                            ? `repeat(${activeColumns.length}, minmax(260px, 1fr))`
                            : undefined,
                }}
            >
                {activeColumns.map((col) => {
                    const colBookings = getBookingsForColumn(col);
                    const isHiddenOnMobile =
                        String(mobileActiveColId) !== String(col.id);

                    return (
                        <div
                            key={String(col.id)}
                            onDragOver={handleDragOver}
                            onDrop={(e) => handleDrop(e, col)}
                            className={`flex min-h-[460px] flex-col rounded-[12px] border border-slate-200 bg-white p-2.5 shadow-none transition-colors ${
                                isHiddenOnMobile ? 'hidden sm:flex' : 'flex'
                            }`}
                        >
                            {/* Column Header: Icon + Title + Count Pill + Accent Border */}
                            <div
                                className="mb-2 flex items-center justify-between border-b pb-2"
                                style={{ borderBottomColor: `${col.color}30` }}
                            >
                                <div className="flex items-center gap-1.5">
                                    <span style={{ color: col.color }}>
                                        {renderKanbanIcon(
                                            col.icon,
                                            'h-3.5 w-3.5'
                                        )}
                                    </span>
                                    <span className="text-xs font-semibold text-slate-900">
                                        {col.title}
                                    </span>
                                </div>
                                <span
                                    className="rounded-full px-2 py-0.5 text-[10px] font-bold"
                                    style={{
                                        backgroundColor: `${col.color}15`,
                                        color: col.color,
                                    }}
                                >
                                    {colBookings.length}
                                </span>
                            </div>

                            {/* Cards Container */}
                            <div className="max-h-[700px] flex-1 space-y-2 overflow-y-auto pr-0.5">
                                {colBookings.length === 0 ? (
                                    <div className="my-auto rounded-[8px] border border-dashed border-slate-200 p-6 text-center text-xs text-slate-400">
                                        Tidak ada booking
                                    </div>
                                ) : (
                                    colBookings.map((b) => {
                                        // Terminal statuses cannot be dragged out (PRD 213.2)
                                        const isTerminal = [
                                            'COMPLETED',
                                            'CANCELLED',
                                            'EXPIRED',
                                        ].includes(b.status_category);

                                        return (
                                            <div
                                                key={b.id}
                                                draggable={!isTerminal}
                                                onDragStart={(e) =>
                                                    handleDragStart(e, b)
                                                }
                                                onClick={() => onOpenDetail(b)}
                                                className={`cursor-pointer rounded-[10px] border border-slate-200 bg-white p-2.5 text-xs shadow-none transition-all hover:border-blue-500 ${
                                                    draggingId === b.id
                                                        ? 'opacity-40 ring-2 ring-blue-500'
                                                        : ''
                                                } ${isTerminal ? 'cursor-default' : 'cursor-grab'}`}
                                            >
                                                {/* Top Row: Code & Payment Status */}
                                                <div className="mb-1.5 flex items-center justify-between text-[11px]">
                                                    <span className="font-mono font-semibold text-slate-900">
                                                        {b.code}
                                                    </span>
                                                    <span
                                                        className={`py-0.2 flex items-center gap-1 rounded px-1.5 text-[9px] font-semibold ${
                                                            b.payment_status ===
                                                            'PAID'
                                                                ? 'bg-emerald-50 text-emerald-700'
                                                                : b.payment_status ===
                                                                    'PARTIAL'
                                                                  ? 'bg-blue-50 text-blue-700'
                                                                  : 'bg-amber-50 text-amber-700'
                                                        }`}
                                                    >
                                                        <CreditCard className="h-2.5 w-2.5" />
                                                        {b.payment_status}
                                                    </span>
                                                </div>

                                                {/* Customer Name */}
                                                <div className="flex items-center gap-1 truncate font-semibold text-slate-900">
                                                    <User className="h-3 w-3 shrink-0 text-slate-400" />
                                                    <span className="truncate">
                                                        {b.customer?.name ||
                                                            'Walk-in Customer'}
                                                    </span>
                                                </div>

                                                {/* Service & Staff */}
                                                <div className="mt-1 truncate text-[11px] text-slate-600">
                                                    {b.service_snapshot?.name ||
                                                        b.service?.name}
                                                </div>

                                                {/* Staff / Resource Assignment Badge */}
                                                {b.allocations &&
                                                    b.allocations.length >
                                                        0 && (
                                                        <div className="mt-1 flex flex-wrap gap-1">
                                                            {b.allocations.map(
                                                                (alloc) => (
                                                                    <span
                                                                        key={
                                                                            alloc.id
                                                                        }
                                                                        className="rounded bg-slate-100 px-1.5 py-0.5 text-[9px] text-slate-600"
                                                                    >
                                                                        {alloc
                                                                            .resource
                                                                            ?.name ||
                                                                            'Resource'}
                                                                    </span>
                                                                )
                                                            )}
                                                        </div>
                                                    )}

                                                {/* Time & Price Bottom Row */}
                                                <div className="mt-2.5 flex items-center justify-between border-t border-slate-100 pt-2 text-[10px]">
                                                    <span className="flex items-center gap-1 font-mono text-slate-500">
                                                        <Clock className="h-3 w-3 text-slate-400" />
                                                        {new Date(
                                                            b.start_at
                                                        ).toLocaleTimeString(
                                                            'id-ID',
                                                            {
                                                                hour: '2-digit',
                                                                minute: '2-digit',
                                                            }
                                                        )}
                                                    </span>
                                                    <span className="font-semibold text-slate-900">
                                                        Rp{' '}
                                                        {Number(
                                                            b.total_idr
                                                        ).toLocaleString(
                                                            'id-ID'
                                                        )}
                                                    </span>
                                                </div>
                                            </div>
                                        );
                                    })
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
};
