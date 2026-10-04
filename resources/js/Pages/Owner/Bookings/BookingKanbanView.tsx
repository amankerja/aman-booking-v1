import { Clock } from 'lucide-react';
import React, { useState } from 'react';
import { BookingItem } from './types';

interface BookingKanbanViewProps {
    bookings: BookingItem[];
    onOpenDetail: (booking: BookingItem) => void;
    onTransitionStatus: (
        booking: BookingItem,
        targetStatus: string
    ) => Promise<boolean>;
}

interface KanbanColumn {
    id: string;
    title: string;
    categories: string[];
    targetCategory: string;
    color: string;
    badgeBg: string;
}

const KANBAN_COLUMNS: KanbanColumn[] = [
    {
        id: 'pending',
        title: 'Menunggu',
        categories: ['PENDING'],
        targetCategory: 'PENDING',
        color: 'border-slate-300 bg-slate-50',
        badgeBg: 'bg-slate-200 text-slate-700',
    },
    {
        id: 'confirmed',
        title: 'Terkonfirmasi',
        categories: ['CONFIRMED'],
        targetCategory: 'CONFIRMED',
        color: 'border-blue-300 bg-blue-50/30',
        badgeBg: 'bg-blue-100 text-blue-700',
    },
    {
        id: 'in_progress',
        title: 'Dalam Pelayanan',
        categories: ['CHECKED_IN', 'IN_PROGRESS'],
        targetCategory: 'IN_PROGRESS',
        color: 'border-amber-300 bg-amber-50/30',
        badgeBg: 'bg-amber-100 text-amber-800',
    },
    {
        id: 'completed',
        title: 'Selesai',
        categories: ['COMPLETED'],
        targetCategory: 'COMPLETED',
        color: 'border-emerald-300 bg-emerald-50/30',
        badgeBg: 'bg-emerald-100 text-emerald-800',
    },
    {
        id: 'cancelled',
        title: 'Batal / No-Show',
        categories: ['CANCELLED', 'NO_SHOW'],
        targetCategory: 'CANCELLED',
        color: 'border-rose-300 bg-rose-50/30',
        badgeBg: 'bg-rose-100 text-rose-800',
    },
];

export const BookingKanbanView: React.FC<BookingKanbanViewProps> = ({
    bookings,
    onOpenDetail,
    onTransitionStatus,
}) => {
    // Mobile active column selector (PRD: "Kanban satu kolom aktif di HP")
    const [mobileActiveCol, setMobileActiveCol] = useState('pending');
    const [draggingId, setDraggingId] = useState<number | null>(null);

    const handleDragStart = (e: React.DragEvent, b: BookingItem) => {
        e.dataTransfer.setData('text/plain', String(b.id));
        setDraggingId(b.id);
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
    };

    const handleDrop = async (e: React.DragEvent, col: KanbanColumn) => {
        e.preventDefault();
        const bookingIdStr = e.dataTransfer.getData('text/plain');
        setDraggingId(null);

        if (!bookingIdStr) return;
        const b = bookings.find((item) => item.id === Number(bookingIdStr));
        if (!b) return;

        // If dropping into column that already contains booking's status category, do nothing
        if (col.categories.includes(b.status_category)) return;

        // Execute transition with validation guard and optimistic rollback if rejected
        await onTransitionStatus(b, col.targetCategory);
    };

    return (
        <div className="space-y-3">
            {/* Mobile Column Switcher (Tab pills) */}
            <div className="flex gap-1 overflow-x-auto rounded-[10px] border border-slate-200 bg-white p-1 sm:hidden">
                {KANBAN_COLUMNS.map((col) => {
                    const count = bookings.filter((b) =>
                        col.categories.includes(b.status_category)
                    ).length;
                    const isActive = mobileActiveCol === col.id;
                    return (
                        <button
                            key={col.id}
                            type="button"
                            onClick={() => setMobileActiveCol(col.id)}
                            className={`flex items-center gap-1.5 rounded-[8px] px-2.5 py-1.5 text-xs font-medium whitespace-nowrap transition-colors ${
                                isActive
                                    ? 'bg-blue-600 text-white'
                                    : 'text-slate-600 hover:bg-slate-100'
                            }`}
                        >
                            <span>{col.title}</span>
                            <span
                                className={`py-0.2 rounded-full px-1.5 text-[10px] ${
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

            {/* Kanban Columns Grid */}
            <div className="grid grid-cols-1 items-start gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5">
                {KANBAN_COLUMNS.map((col) => {
                    const colBookings = bookings.filter((b) =>
                        col.categories.includes(b.status_category)
                    );
                    const isHiddenOnMobile = mobileActiveCol !== col.id;

                    return (
                        <div
                            key={col.id}
                            onDragOver={handleDragOver}
                            onDrop={(e) => handleDrop(e, col)}
                            className={`flex min-h-[400px] flex-col rounded-[12px] border border-slate-200 bg-white p-2.5 shadow-none transition-colors ${
                                isHiddenOnMobile ? 'hidden sm:flex' : 'flex'
                            }`}
                        >
                            {/* Column Header */}
                            <div className="mb-2 flex items-center justify-between border-b border-slate-100 pb-2">
                                <span className="text-xs font-semibold text-slate-800">
                                    {col.title}
                                </span>
                                <span
                                    className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${col.badgeBg}`}
                                >
                                    {colBookings.length}
                                </span>
                            </div>

                            {/* Cards Container */}
                            <div className="max-h-[680px] flex-1 space-y-2 overflow-y-auto pr-0.5">
                                {colBookings.length === 0 ? (
                                    <div className="my-auto rounded-[8px] border border-dashed border-slate-200 p-4 text-center text-[11px] text-slate-400">
                                        Kosong
                                    </div>
                                ) : (
                                    colBookings.map((b) => (
                                        <div
                                            key={b.id}
                                            draggable={
                                                ![
                                                    'COMPLETED',
                                                    'CANCELLED',
                                                    'EXPIRED',
                                                ].includes(b.status_category)
                                            }
                                            onDragStart={(e) =>
                                                handleDragStart(e, b)
                                            }
                                            onClick={() => onOpenDetail(b)}
                                            className={`cursor-pointer rounded-[10px] border border-slate-200 bg-white p-2.5 text-xs shadow-none transition-all hover:border-blue-500 ${
                                                draggingId === b.id
                                                    ? 'opacity-40 ring-2 ring-blue-500'
                                                    : ''
                                            }`}
                                        >
                                            <div className="mb-1 flex items-center justify-between text-[11px]">
                                                <span className="font-mono font-semibold text-slate-900">
                                                    {b.code}
                                                </span>
                                                <span
                                                    className={`py-0.2 rounded px-1.5 text-[9px] font-semibold ${
                                                        b.payment_status ===
                                                        'PAID'
                                                            ? 'bg-emerald-50 text-emerald-700'
                                                            : 'bg-amber-50 text-amber-700'
                                                    }`}
                                                >
                                                    {b.payment_status}
                                                </span>
                                            </div>

                                            <div className="truncate font-medium text-slate-900">
                                                {b.customer?.name || 'Walk-in'}
                                            </div>

                                            <div className="mt-0.5 truncate text-[11px] text-slate-600">
                                                {b.service_snapshot?.name ||
                                                    b.service?.name}
                                            </div>

                                            <div className="mt-2 flex items-center justify-between border-t border-slate-100 pt-2 text-[10px] text-slate-400">
                                                <span className="flex items-center gap-1 font-mono">
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
                                                    ).toLocaleString('id-ID')}
                                                </span>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
};
