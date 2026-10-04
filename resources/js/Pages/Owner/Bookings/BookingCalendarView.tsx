import { ChevronLeft, ChevronRight, Clock, UserCheck } from 'lucide-react';
import React, { useMemo, useState } from 'react';
import { Button } from '../../../Components/ui';
import { BookingItem, ResourceSummary } from './types';

interface BookingCalendarViewProps {
    bookings: BookingItem[];
    resources: ResourceSummary[];
    onOpenDetail: (booking: BookingItem) => void;
    onOpenReschedule?: (booking: BookingItem) => void;
    onRescheduleSlot: (
        booking: BookingItem,
        newStartAt: string
    ) => Promise<boolean>;
}

export const BookingCalendarView: React.FC<BookingCalendarViewProps> = ({
    bookings,
    resources,
    onOpenDetail,
    onOpenReschedule: _onOpenReschedule,
    onRescheduleSlot,
}) => {
    // Mode: Day, Week, Agenda
    const [calMode, setCalMode] = useState<'day' | 'week' | 'agenda'>('week');
    const [currentDate, setCurrentDate] = useState<Date>(new Date());
    const [selectedResourceId, setSelectedResourceId] = useState<string>('');

    // Drag-and-drop state for reschedule (PRD 42)
    const [draggingBookingId, setDraggingBookingId] = useState<number | null>(
        null
    );

    // Filter bookings by resource if selected
    const filteredBookings = useMemo(() => {
        if (!selectedResourceId) return bookings;
        const resId = Number(selectedResourceId);
        return bookings.filter((b) =>
            b.allocations?.some((a) => a.resource_id === resId)
        );
    }, [bookings, selectedResourceId]);

    // Navigation helpers
    const handlePrev = () => {
        const next = new Date(currentDate);
        if (calMode === 'day') next.setDate(next.getDate() - 1);
        else if (calMode === 'week') next.setDate(next.getDate() - 7);
        else next.setDate(next.getDate() - 7);
        setCurrentDate(next);
    };

    const handleNext = () => {
        const next = new Date(currentDate);
        if (calMode === 'day') next.setDate(next.getDate() + 1);
        else if (calMode === 'week') next.setDate(next.getDate() + 7);
        else next.setDate(next.getDate() + 7);
        setCurrentDate(next);
    };

    const handleToday = () => {
        setCurrentDate(new Date());
    };

    // Week days calculation (Monday to Sunday)
    const weekDays = useMemo(() => {
        const curr = new Date(currentDate);
        const day = curr.getDay();
        const diff = curr.getDate() - day + (day === 0 ? -6 : 1); // Monday as first day
        const monday = new Date(curr.setDate(diff));

        const days: Date[] = [];
        for (let i = 0; i < 7; i++) {
            const next = new Date(monday);
            next.setDate(monday.getDate() + i);
            days.push(next);
        }
        return days;
    }, [currentDate]);

    // Hours list (08:00 to 20:00)
    const hours = useMemo(() => {
        const h: number[] = [];
        for (let i = 8; i <= 20; i++) h.push(i);
        return h;
    }, []);

    // Drag and drop handlers
    const handleDragStart = (e: React.DragEvent, b: BookingItem) => {
        e.dataTransfer.setData('text/plain', String(b.id));
        setDraggingBookingId(b.id);
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
    };

    const handleDrop = async (
        e: React.DragEvent,
        targetDate: Date,
        targetHour: number
    ) => {
        e.preventDefault();
        const bookingIdStr = e.dataTransfer.getData('text/plain');
        setDraggingBookingId(null);

        if (!bookingIdStr) return;
        const b = bookings.find((item) => item.id === Number(bookingIdStr));
        if (!b) return;

        // Build new start ISO string
        const newStart = new Date(targetDate);
        newStart.setHours(targetHour, 0, 0, 0);

        // Execute reschedule via parent handler with automatic rollback if rejected
        await onRescheduleSlot(b, newStart.toISOString());
    };

    return (
        <div className="space-y-3">
            {/* Calendar Controls */}
            <div className="flex flex-col gap-3 rounded-[12px] border border-slate-200 bg-white p-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <Button variant="secondary" size="sm" onClick={handleToday}>
                        Hari Ini
                    </Button>
                    <div className="flex items-center rounded-[8px] border border-slate-200 bg-slate-50">
                        <button
                            type="button"
                            onClick={handlePrev}
                            className="p-1.5 text-slate-600 transition-colors hover:text-slate-900"
                        >
                            <ChevronLeft className="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            onClick={handleNext}
                            className="p-1.5 text-slate-600 transition-colors hover:text-slate-900"
                        >
                            <ChevronRight className="h-4 w-4" />
                        </button>
                    </div>
                    <span className="ml-1 text-xs font-semibold text-slate-900">
                        {calMode === 'day' &&
                            currentDate.toLocaleDateString('id-ID', {
                                weekday: 'long',
                                day: 'numeric',
                                month: 'long',
                                year: 'numeric',
                            })}
                        {calMode === 'week' && (
                            <>
                                {weekDays[0].toLocaleDateString('id-ID', {
                                    day: 'numeric',
                                    month: 'short',
                                })}{' '}
                                -{' '}
                                {weekDays[6].toLocaleDateString('id-ID', {
                                    day: 'numeric',
                                    month: 'short',
                                    year: 'numeric',
                                })}
                            </>
                        )}
                        {calMode === 'agenda' && 'Daftar Agenda Reservasi'}
                    </span>
                </div>

                <div className="flex items-center gap-2">
                    {/* Staff filter */}
                    <select
                        value={selectedResourceId}
                        onChange={(e) => setSelectedResourceId(e.target.value)}
                        className="rounded-[8px] border border-slate-200 bg-[#f8fafc] px-2.5 py-1 text-xs text-slate-800 focus:border-blue-600 focus:outline-none"
                    >
                        <option value="">Semua Staf & Resource</option>
                        {resources.map((r) => (
                            <option key={r.id} value={r.id}>
                                {r.name}
                            </option>
                        ))}
                    </select>

                    {/* View mode switcher */}
                    <div className="inline-flex rounded-[8px] border border-slate-200 bg-slate-100 p-0.5 text-xs">
                        <button
                            type="button"
                            onClick={() => setCalMode('day')}
                            className={`rounded px-2.5 py-1 font-medium transition-colors ${
                                calMode === 'day'
                                    ? 'bg-white text-slate-900 shadow-sm'
                                    : 'text-slate-600 hover:text-slate-900'
                            }`}
                        >
                            Hari
                        </button>
                        <button
                            type="button"
                            onClick={() => setCalMode('week')}
                            className={`rounded px-2.5 py-1 font-medium transition-colors ${
                                calMode === 'week'
                                    ? 'bg-white text-slate-900 shadow-sm'
                                    : 'text-slate-600 hover:text-slate-900'
                            }`}
                        >
                            Minggu
                        </button>
                        <button
                            type="button"
                            onClick={() => setCalMode('agenda')}
                            className={`rounded px-2.5 py-1 font-medium transition-colors ${
                                calMode === 'agenda'
                                    ? 'bg-white text-slate-900 shadow-sm'
                                    : 'text-slate-600 hover:text-slate-900'
                            }`}
                        >
                            Agenda
                        </button>
                    </div>
                </div>
            </div>

            {/* View Mode: WEEK */}
            {calMode === 'week' && (
                <div className="overflow-x-auto rounded-[12px] border border-slate-200 bg-white shadow-none">
                    <div className="min-w-[800px]">
                        {/* Days Header */}
                        <div className="grid grid-cols-8 border-b border-slate-200 bg-slate-50 text-center text-xs font-semibold text-slate-700">
                            <div className="border-r border-slate-200 py-2.5 text-slate-400">
                                Jam
                            </div>
                            {weekDays.map((d, idx) => {
                                const isToday =
                                    d.toDateString() ===
                                    new Date().toDateString();
                                return (
                                    <div
                                        key={idx}
                                        className={`border-r border-slate-200 py-2 last:border-r-0 ${
                                            isToday
                                                ? 'bg-blue-50/50 text-blue-700'
                                                : ''
                                        }`}
                                    >
                                        <div className="text-[11px] font-normal text-slate-500">
                                            {d.toLocaleDateString('id-ID', {
                                                weekday: 'short',
                                            })}
                                        </div>
                                        <div className="font-semibold">
                                            {d.getDate()}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        {/* Grid Rows */}
                        <div className="divide-y divide-slate-100 text-xs">
                            {hours.map((hour) => (
                                <div
                                    key={hour}
                                    className="grid min-h-[56px] grid-cols-8"
                                >
                                    <div className="border-r border-slate-100 bg-slate-50/50 px-2 py-1 text-center font-mono text-[11px] text-slate-400">
                                        {String(hour).padStart(2, '0')}:00
                                    </div>

                                    {weekDays.map((d, dayIdx) => {
                                        const dateStr = d
                                            .toISOString()
                                            .split('T')[0];
                                        // Match bookings in this date & hour
                                        const cellBookings =
                                            filteredBookings.filter((b) => {
                                                const bDate = new Date(
                                                    b.start_at
                                                );
                                                const bDateStr = bDate
                                                    .toISOString()
                                                    .split('T')[0];
                                                const bHour = bDate.getHours();
                                                return (
                                                    bDateStr === dateStr &&
                                                    bHour === hour
                                                );
                                            });

                                        return (
                                            <div
                                                key={dayIdx}
                                                onDragOver={handleDragOver}
                                                onDrop={(e) =>
                                                    handleDrop(e, d, hour)
                                                }
                                                className="group relative border-r border-slate-100 p-1 transition-colors last:border-r-0 hover:bg-slate-50"
                                            >
                                                {cellBookings.map((b) => (
                                                    <div
                                                        key={b.id}
                                                        draggable={[
                                                            'CONFIRMED',
                                                            'PENDING',
                                                        ].includes(
                                                            b.status_category
                                                        )}
                                                        onDragStart={(e) =>
                                                            handleDragStart(
                                                                e,
                                                                b
                                                            )
                                                        }
                                                        onClick={() =>
                                                            onOpenDetail(b)
                                                        }
                                                        className={`mb-1 cursor-pointer rounded-[6px] border p-1.5 text-[11px] transition-all ${
                                                            b.status_category ===
                                                            'COMPLETED'
                                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                                                : b.status_category ===
                                                                    'CONFIRMED'
                                                                  ? 'border-blue-200 bg-blue-50 text-blue-800 hover:border-blue-500'
                                                                  : b.status_category ===
                                                                      'IN_PROGRESS'
                                                                    ? 'border-amber-200 bg-amber-50 text-amber-800'
                                                                    : 'border-slate-200 bg-slate-100 text-slate-800'
                                                        } ${
                                                            draggingBookingId ===
                                                            b.id
                                                                ? 'opacity-40 ring-2 ring-blue-500'
                                                                : ''
                                                        }`}
                                                    >
                                                        <div className="truncate font-semibold">
                                                            {b.customer?.name ||
                                                                'Walk-in'}
                                                        </div>
                                                        <div className="truncate text-[10px] text-slate-600">
                                                            {b.service_snapshot
                                                                ?.name ||
                                                                b.service?.name}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        );
                                    })}
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {/* View Mode: DAY */}
            {calMode === 'day' && (
                <div className="space-y-2 rounded-[12px] border border-slate-200 bg-white p-3">
                    <div className="divide-y divide-slate-100 text-xs">
                        {hours.map((hour) => {
                            const dateStr = currentDate
                                .toISOString()
                                .split('T')[0];
                            const hourBookings = filteredBookings.filter(
                                (b) => {
                                    const bDate = new Date(b.start_at);
                                    const bDateStr = bDate
                                        .toISOString()
                                        .split('T')[0];
                                    return (
                                        bDateStr === dateStr &&
                                        bDate.getHours() === hour
                                    );
                                }
                            );

                            return (
                                <div
                                    key={hour}
                                    onDragOver={handleDragOver}
                                    onDrop={(e) =>
                                        handleDrop(e, currentDate, hour)
                                    }
                                    className="flex min-h-[48px] items-start gap-3 py-2 transition-colors hover:bg-slate-50"
                                >
                                    <span className="w-14 shrink-0 pt-1 font-mono text-xs text-slate-400">
                                        {String(hour).padStart(2, '0')}:00
                                    </span>
                                    <div className="flex flex-1 flex-wrap gap-2">
                                        {hourBookings.map((b) => (
                                            <div
                                                key={b.id}
                                                draggable={[
                                                    'CONFIRMED',
                                                    'PENDING',
                                                ].includes(b.status_category)}
                                                onDragStart={(e) =>
                                                    handleDragStart(e, b)
                                                }
                                                onClick={() => onOpenDetail(b)}
                                                className="min-w-[200px] cursor-pointer rounded-[8px] border border-slate-200 bg-white p-2 text-xs shadow-none hover:border-blue-600"
                                            >
                                                <div className="mb-0.5 flex items-center justify-between font-medium text-slate-900">
                                                    <span>
                                                        {b.customer?.name ||
                                                            'Walk-in'}
                                                    </span>
                                                    <span className="font-mono text-[10px] text-blue-600">
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
                                                </div>
                                                <div className="text-[11px] text-slate-500">
                                                    {b.service_snapshot?.name ||
                                                        b.service?.name}{' '}
                                                    (
                                                    {
                                                        b.service_snapshot
                                                            ?.duration_minutes
                                                    }
                                                    m)
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}

            {/* View Mode: AGENDA */}
            {calMode === 'agenda' && (
                <div className="space-y-3">
                    {filteredBookings.length === 0 ? (
                        <div className="rounded-[12px] border border-slate-200 bg-white p-8 text-center text-xs text-slate-400">
                            Tidak ada jadwal reservasi pada filter saat ini.
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {filteredBookings.map((b) => (
                                <div
                                    key={b.id}
                                    onClick={() => onOpenDetail(b)}
                                    className="flex cursor-pointer flex-col justify-between gap-3 rounded-[10px] border border-slate-200 bg-white p-3 text-xs transition-colors hover:border-blue-500 sm:flex-row sm:items-center"
                                >
                                    <div className="flex items-start gap-3">
                                        <div className="flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-[8px] bg-blue-50 text-xs font-bold text-blue-700">
                                            <span>
                                                {new Date(b.start_at).getDate()}
                                            </span>
                                            <span className="text-[9px] font-normal uppercase">
                                                {new Date(
                                                    b.start_at
                                                ).toLocaleDateString('id-ID', {
                                                    month: 'short',
                                                })}
                                            </span>
                                        </div>

                                        <div>
                                            <div className="flex items-center gap-2">
                                                <span className="font-semibold text-slate-900">
                                                    {b.customer?.name ||
                                                        'Walk-in'}
                                                </span>
                                                <span className="font-mono text-[11px] text-slate-400">
                                                    {b.code}
                                                </span>
                                            </div>
                                            <div className="mt-0.5 text-[11px] text-slate-600">
                                                {b.service_snapshot?.name ||
                                                    b.service?.name}
                                            </div>
                                            <div className="mt-1 flex items-center gap-3 text-[11px] text-slate-400">
                                                <span className="flex items-center gap-1">
                                                    <Clock className="h-3 w-3" />
                                                    {new Date(
                                                        b.start_at
                                                    ).toLocaleTimeString(
                                                        'id-ID',
                                                        {
                                                            hour: '2-digit',
                                                            minute: '2-digit',
                                                        }
                                                    )}{' '}
                                                    -{' '}
                                                    {new Date(
                                                        b.end_at
                                                    ).toLocaleTimeString(
                                                        'id-ID',
                                                        {
                                                            hour: '2-digit',
                                                            minute: '2-digit',
                                                        }
                                                    )}
                                                </span>
                                                {b.allocations &&
                                                    b.allocations.length >
                                                        0 && (
                                                        <span className="flex items-center gap-1 text-slate-600">
                                                            <UserCheck className="h-3 w-3 text-slate-400" />
                                                            {
                                                                b.allocations[0]
                                                                    .resource
                                                                    ?.name
                                                            }
                                                        </span>
                                                    )}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex items-center justify-between gap-3 border-t border-slate-100 pt-2 sm:justify-end sm:border-t-0 sm:pt-0">
                                        <span
                                            className={`rounded-full border px-2 py-0.5 text-[10px] font-medium ${
                                                b.status_category ===
                                                'COMPLETED'
                                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                    : b.status_category ===
                                                        'CONFIRMED'
                                                      ? 'border border-blue-200 bg-blue-50 text-blue-700'
                                                      : 'border border-slate-200 bg-slate-100 text-slate-600'
                                            }`}
                                        >
                                            {b.status_category}
                                        </span>
                                        <span className="font-semibold text-slate-900">
                                            Rp{' '}
                                            {Number(b.total_idr).toLocaleString(
                                                'id-ID'
                                            )}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};
