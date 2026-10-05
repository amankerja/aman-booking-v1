import { router } from '@inertiajs/react';
import {
    Calendar as CalendarIcon,
    Kanban as KanbanIcon,
    Plus,
    RefreshCw,
    Search,
    Table as TableIcon,
    X,
} from 'lucide-react';
import React, { useCallback, useEffect, useState } from 'react';
import { Button, useToast } from '../../../Components/ui';
import { OwnerLayout } from '../../../Layouts/OwnerLayout';
import { BookingCalendarView } from './BookingCalendarView';
import { BookingDetailDrawer } from './BookingDetailDrawer';
import { BookingKanbanView } from './BookingKanbanView';
import { BookingTableView } from './BookingTableView';
import { QuickBookingModal } from './QuickBookingModal';
import { RescheduleModal } from './RescheduleModal';
import {
    BookingFilters,
    BookingItem,
    BookingStatusItem,
    CustomerSummary,
    FormItem,
    PaginatedBookings,
    ResourceSummary,
    ServiceSummary,
} from './types';

interface BookingsIndexProps {
    bookings: PaginatedBookings | BookingItem[];
    services: ServiceSummary[];
    resources: ResourceSummary[];
    customers: CustomerSummary[];
    statuses?: BookingStatusItem[];
    statusCounts: Record<string, number>;
    forms?: FormItem[];
    filters: BookingFilters;
}

export default function BookingsIndex({
    bookings,
    services,
    resources,
    customers,
    statuses,
    statusCounts,
    forms,
    filters,
}: BookingsIndexProps) {
    const toast = useToast();

    // Active view mode
    const [viewMode, setViewMode] = useState<'table' | 'calendar' | 'kanban'>(
        filters.view || 'table'
    );

    // Filters
    const [search, setSearch] = useState(filters.search || '');
    const [selectedStatus, setSelectedStatus] = useState(
        filters.status || 'ALL'
    );
    const [selectedServiceId, setSelectedServiceId] = useState(
        filters.service_id ? String(filters.service_id) : ''
    );
    const [selectedResourceId, setSelectedResourceId] = useState(
        filters.resource_id ? String(filters.resource_id) : ''
    );

    // Local copy of bookings to allow optimistic updates and seamless polling
    const [bookingsState, setBookingsState] = useState<BookingItem[]>(() => {
        if (Array.isArray(bookings)) return bookings;
        return bookings.data || [];
    });

    // Modals & Drawers
    const [isQuickBookingOpen, setIsQuickBookingOpen] = useState(false);
    const [isDetailOpen, setIsDetailOpen] = useState(false);
    const [activeBooking, setActiveBooking] = useState<BookingItem | null>(
        null
    );
    const [isRescheduleOpen, setIsRescheduleOpen] = useState(false);
    const [reschedulingBooking, setReschedulingBooking] =
        useState<BookingItem | null>(null);

    // Polling indicator state (PRD 204.4: 30-60 detik)
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [lastSyncTime, setLastSyncTime] = useState<Date>(new Date());

    // Sync state when Inertia props change
    useEffect(() => {
        if (Array.isArray(bookings)) {
            setBookingsState(bookings);
        } else if (bookings && bookings.data) {
            setBookingsState(bookings.data);
        }
    }, [bookings]);

    const handleFeedRefresh = useCallback(
        async (silent = false) => {
            if (!silent) setIsRefreshing(true);
            try {
                const params = new URLSearchParams({
                    view: viewMode,
                    status: selectedStatus !== 'ALL' ? selectedStatus : '',
                });
                if (selectedResourceId)
                    params.append('resource_id', selectedResourceId);

                const res = await fetch(
                    `/app/bookings/feed?${params.toString()}`,
                    {
                        headers: { Accept: 'application/json' },
                    }
                );
                if (res.ok) {
                    const data = await res.json();
                    if (Array.isArray(data.bookings)) {
                        setBookingsState(data.bookings);
                    } else if (data.bookings && data.bookings.data) {
                        setBookingsState(data.bookings.data);
                    }
                    setLastSyncTime(new Date());
                    if (!silent) toast.success('Data booking diperbarui.');
                }
            } catch {
                if (!silent) toast.error('Gagal memperbarui data.');
            } finally {
                if (!silent) setIsRefreshing(false);
            }
        },
        [viewMode, selectedStatus, selectedResourceId, toast]
    );

    // Background polling (every 45 seconds)
    useEffect(() => {
        const interval = setInterval(() => {
            handleFeedRefresh(true);
        }, 45000);

        return () => clearInterval(interval);
    }, [handleFeedRefresh]);

    const handleFilterSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/app/bookings',
            {
                view: viewMode,
                search: search.trim() || undefined,
                status: selectedStatus !== 'ALL' ? selectedStatus : undefined,
                service_id: selectedServiceId || undefined,
                resource_id: selectedResourceId || undefined,
            },
            { preserveState: true, replace: true }
        );
    };

    const handleViewModeChange = (newView: 'table' | 'calendar' | 'kanban') => {
        setViewMode(newView);
        router.get(
            '/app/bookings',
            {
                view: newView,
                search: search.trim() || undefined,
                status: selectedStatus !== 'ALL' ? selectedStatus : undefined,
                service_id: selectedServiceId || undefined,
                resource_id: selectedResourceId || undefined,
            },
            { preserveState: true, replace: true }
        );
    };

    const handleResetFilters = () => {
        setSearch('');
        setSelectedStatus('ALL');
        setSelectedServiceId('');
        setSelectedResourceId('');
        router.get(
            '/app/bookings',
            { view: viewMode },
            { preserveState: true, replace: true }
        );
    };

    // Open detail drawer
    const handleOpenDetail = (booking: BookingItem) => {
        setActiveBooking(booking);
        setIsDetailOpen(true);
    };

    // Open reschedule modal
    const handleOpenReschedule = (booking: BookingItem) => {
        setReschedulingBooking(booking);
        setIsRescheduleOpen(true);
    };

    // Optimistic status transition with automatic rollback
    const handleTransitionStatus = async (
        booking: BookingItem,
        targetStatus: string,
        targetStatusId?: number | string
    ): Promise<boolean> => {
        const originalStatus = booking.status_category;
        const originalStatusId = booking.status_id;

        // Optimistically update local state
        setBookingsState((prev) =>
            prev.map((b) =>
                b.id === booking.id
                    ? {
                          ...b,
                          status_category:
                              targetStatus as BookingItem['status_category'],
                          status_id: targetStatusId ?? b.status_id,
                      }
                    : b
            )
        );

        try {
            const res = await fetch(`/app/bookings/${booking.id}/status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]'
                            ) as HTMLMetaElement
                        )?.content || '',
                },
                body: JSON.stringify({
                    status: targetStatus,
                    status_id: targetStatusId,
                }),
            });

            const data = await res.json();
            if (res.ok) {
                toast.success(
                    data.message ||
                        `Status ${booking.code} berhasil diperbarui.`
                );
                // Update with server truth
                if (data.booking) {
                    setBookingsState((prev) =>
                        prev.map((b) =>
                            b.id === booking.id ? data.booking : b
                        )
                    );
                    if (activeBooking?.id === booking.id) {
                        setActiveBooking(data.booking);
                    }
                }
                return true;
            } else {
                // Rollback on rejection
                toast.error(data.message || 'Transisi status ditolak.');
                setBookingsState((prev) =>
                    prev.map((b) =>
                        b.id === booking.id
                            ? {
                                  ...b,
                                  status_category: originalStatus,
                                  status_id: originalStatusId,
                              }
                            : b
                    )
                );
                return false;
            }
        } catch {
            // Rollback on network failure
            toast.error(
                'Gagal menghubungi server. Mengembalikan status sebelumnya.'
            );
            setBookingsState((prev) =>
                prev.map((b) =>
                    b.id === booking.id
                        ? {
                              ...b,
                              status_category: originalStatus,
                              status_id: originalStatusId,
                          }
                        : b
                )
            );
            return false;
        }
    };

    // Optimistic reschedule with rollback
    const handleRescheduleSlot = async (
        booking: BookingItem,
        newStartAt: string
    ): Promise<boolean> => {
        const originalStart = booking.start_at;
        const originalEnd = booking.end_at;

        // Optimistically update local state
        setBookingsState((prev) =>
            prev.map((b) =>
                b.id === booking.id ? { ...b, start_at: newStartAt } : b
            )
        );

        try {
            const res = await fetch(`/app/bookings/${booking.id}/reschedule`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]'
                            ) as HTMLMetaElement
                        )?.content || '',
                },
                body: JSON.stringify({ new_start_at: newStartAt }),
            });

            const data = await res.json();
            if (res.ok) {
                toast.success(
                    data.message ||
                        `Jadwal ${booking.code} berhasil dipindahkan.`
                );
                if (data.booking) {
                    setBookingsState((prev) =>
                        prev.map((b) =>
                            b.id === booking.id ? data.booking : b
                        )
                    );
                }
                return true;
            } else {
                // Rollback on conflict or rejection
                toast.error(
                    data.message ||
                        'Slot bentrok atau tidak tersedia. Jadwal dikembalikan.'
                );
                setBookingsState((prev) =>
                    prev.map((b) =>
                        b.id === booking.id
                            ? {
                                  ...b,
                                  start_at: originalStart,
                                  end_at: originalEnd,
                              }
                            : b
                    )
                );
                return false;
            }
        } catch {
            toast.error('Gagal menghubungi server. Mengembalikan jadwal.');
            setBookingsState((prev) =>
                prev.map((b) =>
                    b.id === booking.id
                        ? { ...b, start_at: originalStart, end_at: originalEnd }
                        : b
                )
            );
            return false;
        }
    };

    const breadcrumbs = [
        { label: 'Dashboard', href: '/app/dashboard' },
        { label: 'Booking' },
    ];

    const actions = (
        <div className="flex items-center gap-2">
            <button
                type="button"
                onClick={() => handleFeedRefresh(false)}
                title="Perbarui data booking sekarang"
                className="flex items-center gap-1.5 rounded-[8px] border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-600 shadow-none transition-colors hover:text-slate-900"
            >
                <RefreshCw
                    className={`h-3.5 w-3.5 ${isRefreshing ? 'animate-spin text-blue-600' : 'text-slate-400'}`}
                />
                <span className="hidden sm:inline">
                    Sync (
                    {lastSyncTime.toLocaleTimeString('id-ID', {
                        hour: '2-digit',
                        minute: '2-digit',
                    })}
                    )
                </span>
            </button>

            <Button
                variant="primary"
                size="sm"
                onClick={() => setIsQuickBookingOpen(true)}
                className="gap-1.5"
            >
                <Plus className="h-3.5 w-3.5" />
                <span>Quick Booking</span>
            </Button>
        </div>
    );

    return (
        <OwnerLayout
            title="Booking & Reservasi"
            breadcrumbs={breadcrumbs}
            actions={actions}
        >
            <div className="space-y-4">
                {/* View Switchers & Filters Header */}
                <div className="flex flex-col gap-3 rounded-[12px] border border-slate-200 bg-white p-3 shadow-none">
                    <div className="flex flex-col gap-3 border-b border-slate-100 pb-2 sm:flex-row sm:items-center sm:justify-between">
                        {/* View Switcher Tabs */}
                        <div className="inline-flex rounded-[8px] border border-slate-200 bg-slate-100 p-0.5 text-xs">
                            <button
                                type="button"
                                onClick={() => handleViewModeChange('table')}
                                className={`flex items-center gap-1.5 rounded-[6px] px-3 py-1.5 font-medium transition-colors ${
                                    viewMode === 'table'
                                        ? 'bg-white text-slate-900 shadow-sm'
                                        : 'text-slate-600 hover:text-slate-900'
                                }`}
                            >
                                <TableIcon className="h-3.5 w-3.5" />
                                <span>Semua Booking</span>
                                <span className="py-0.2 rounded-full bg-slate-200 px-1.5 text-[10px] font-semibold text-slate-700">
                                    {statusCounts.ALL || 0}
                                </span>
                            </button>

                            <button
                                type="button"
                                onClick={() => handleViewModeChange('calendar')}
                                className={`flex items-center gap-1.5 rounded-[6px] px-3 py-1.5 font-medium transition-colors ${
                                    viewMode === 'calendar'
                                        ? 'bg-white text-slate-900 shadow-sm'
                                        : 'text-slate-600 hover:text-slate-900'
                                }`}
                            >
                                <CalendarIcon className="h-3.5 w-3.5" />
                                <span>Kalender</span>
                            </button>

                            <button
                                type="button"
                                onClick={() => handleViewModeChange('kanban')}
                                className={`flex items-center gap-1.5 rounded-[6px] px-3 py-1.5 font-medium transition-colors ${
                                    viewMode === 'kanban'
                                        ? 'bg-white text-slate-900 shadow-sm'
                                        : 'text-slate-600 hover:text-slate-900'
                                }`}
                            >
                                <KanbanIcon className="h-3.5 w-3.5" />
                                <span>Kanban</span>
                            </button>
                        </div>

                        {/* Status Badges Summary */}
                        <div className="flex items-center gap-1.5 overflow-x-auto text-[11px] text-slate-600">
                            <span className="text-slate-400">Hari ini:</span>
                            <span className="rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 font-semibold text-blue-700 text-slate-900">
                                {statusCounts.TODAY || 0} booking
                            </span>
                            <span className="ml-1 text-slate-400">
                                Menunggu:
                            </span>
                            <span className="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 font-semibold text-amber-700 text-slate-900">
                                {statusCounts.PENDING || 0}
                            </span>
                        </div>
                    </div>

                    {/* Filter Bar */}
                    <form
                        onSubmit={handleFilterSubmit}
                        className="grid grid-cols-1 gap-2 text-xs sm:grid-cols-2 md:grid-cols-5"
                    >
                        {/* Search Input */}
                        <div className="relative md:col-span-2">
                            <Search className="absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari kode (BK-...), nama, WhatsApp, email..."
                                className="h-8 w-full rounded-[8px] border border-slate-200 bg-[#f8fafc] pr-3 pl-8 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:bg-white focus:outline-none"
                            />
                        </div>

                        {/* Status Dropdown */}
                        <div>
                            <select
                                value={selectedStatus}
                                onChange={(e) =>
                                    setSelectedStatus(e.target.value)
                                }
                                className="h-8 w-full rounded-[8px] border border-slate-200 bg-[#f8fafc] px-2 text-xs text-slate-800 focus:border-blue-600 focus:bg-white focus:outline-none"
                            >
                                <option value="ALL">Semua Status</option>
                                <option value="PENDING">
                                    PENDING (Menunggu)
                                </option>
                                <option value="CONFIRMED">
                                    CONFIRMED (Terkonfirmasi)
                                </option>
                                <option value="CHECKED_IN">
                                    CHECKED_IN (Check-In)
                                </option>
                                <option value="IN_PROGRESS">
                                    IN_PROGRESS (Pelayanan)
                                </option>
                                <option value="COMPLETED">
                                    COMPLETED (Selesai)
                                </option>
                                <option value="CANCELLED">
                                    CANCELLED (Dibatalkan)
                                </option>
                                <option value="NO_SHOW">
                                    NO_SHOW (Tidak Hadir)
                                </option>
                            </select>
                        </div>

                        {/* Resource / Staff Dropdown */}
                        <div>
                            <select
                                value={selectedResourceId}
                                onChange={(e) =>
                                    setSelectedResourceId(e.target.value)
                                }
                                className="h-8 w-full rounded-[8px] border border-slate-200 bg-[#f8fafc] px-2 text-xs text-slate-800 focus:border-blue-600 focus:bg-white focus:outline-none"
                            >
                                <option value="">Semua Staf & Resource</option>
                                {resources.map((r) => (
                                    <option key={r.id} value={r.id}>
                                        {r.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Action Buttons */}
                        <div className="flex items-center gap-1.5">
                            <Button
                                type="submit"
                                variant="secondary"
                                size="sm"
                                className="h-8 flex-1"
                            >
                                Filter
                            </Button>
                            {(search ||
                                selectedStatus !== 'ALL' ||
                                selectedServiceId ||
                                selectedResourceId) && (
                                <button
                                    type="button"
                                    onClick={handleResetFilters}
                                    title="Reset filter"
                                    className="h-8 rounded-[8px] border border-slate-200 px-2 text-slate-500 transition-colors hover:bg-slate-100"
                                >
                                    <X className="h-3.5 w-3.5" />
                                </button>
                            )}
                        </div>
                    </form>
                </div>

                {/* Subview Content */}
                {viewMode === 'table' && (
                    <BookingTableView
                        bookings={
                            Array.isArray(bookings)
                                ? {
                                      data: bookingsState,
                                      current_page: 1,
                                      last_page: 1,
                                      per_page: bookingsState.length,
                                      total: bookingsState.length,
                                      from: 1,
                                      to: bookingsState.length,
                                      links: [],
                                  }
                                : (bookings as PaginatedBookings)
                        }
                        onOpenDetail={handleOpenDetail}
                        onOpenReschedule={handleOpenReschedule}
                        onTransitionStatus={handleTransitionStatus}
                    />
                )}

                {viewMode === 'calendar' && (
                    <BookingCalendarView
                        bookings={bookingsState}
                        resources={resources}
                        onOpenDetail={handleOpenDetail}
                        onOpenReschedule={handleOpenReschedule}
                        onRescheduleSlot={handleRescheduleSlot}
                    />
                )}

                {viewMode === 'kanban' && (
                    <BookingKanbanView
                        bookings={bookingsState}
                        statuses={statuses}
                        onOpenDetail={handleOpenDetail}
                        onTransitionStatus={handleTransitionStatus}
                    />
                )}
            </div>

            {/* Quick Booking Modal (PRD 158) */}
            <QuickBookingModal
                isOpen={isQuickBookingOpen}
                onClose={() => setIsQuickBookingOpen(false)}
                services={services}
                resources={resources}
                customers={customers}
                forms={forms || []}
                onBookingCreated={() => handleFeedRefresh(false)}
            />

            {/* Booking Detail Drawer (PRD 67) */}
            <BookingDetailDrawer
                isOpen={isDetailOpen}
                onClose={() => {
                    setIsDetailOpen(false);
                    setActiveBooking(null);
                }}
                booking={activeBooking}
                onStatusChanged={(updated) => {
                    setActiveBooking(updated);
                    setBookingsState((prev) =>
                        prev.map((b) => (b.id === updated.id ? updated : b))
                    );
                }}
                onOpenReschedule={(b) => {
                    setIsDetailOpen(false);
                    handleOpenReschedule(b);
                }}
            />

            {/* Reschedule Modal (PRD 42, 213.1) */}
            <RescheduleModal
                isOpen={isRescheduleOpen}
                onClose={() => {
                    setIsRescheduleOpen(false);
                    setReschedulingBooking(null);
                }}
                booking={reschedulingBooking}
                onRescheduled={(updated) => {
                    setBookingsState((prev) =>
                        prev.map((b) => (b.id === updated.id ? updated : b))
                    );
                }}
            />
        </OwnerLayout>
    );
}
