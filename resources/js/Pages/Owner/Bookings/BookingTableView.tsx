import { Link } from '@inertiajs/react';
import { Calendar, Clock, Eye, History, Phone, UserCheck } from 'lucide-react';
import React from 'react';
import { DataTable } from '../../../Components/ui';
import { BookingItem, PaginatedBookings } from './types';

interface BookingTableViewProps {
    bookings: PaginatedBookings;
    onOpenDetail: (booking: BookingItem) => void;
    onOpenReschedule: (booking: BookingItem) => void;
    onTransitionStatus?: (booking: BookingItem, targetStatus: string) => void;
    onCheckIn?: (booking: BookingItem) => void;
}

export const BookingTableView: React.FC<BookingTableViewProps> = ({
    bookings,
    onOpenDetail,
    onOpenReschedule,
    onTransitionStatus: _onTransitionStatus,
    onCheckIn,
}) => {
    const columns = [
        {
            key: 'code',
            header: 'KODE BOOKING',
            render: (b: BookingItem) => (
                <div>
                    <button
                        type="button"
                        onClick={() => onOpenDetail(b)}
                        className="block text-left font-mono text-xs font-semibold text-blue-600 hover:underline"
                    >
                        {b.code}
                    </button>
                    <span className="text-[10px] text-slate-400">
                        {b.source}
                    </span>
                </div>
            ),
        },
        {
            key: 'customer',
            header: 'PELANGGAN',
            render: (b: BookingItem) => (
                <div className="min-w-0">
                    <span className="block truncate text-xs font-medium text-slate-900">
                        {b.customer?.name || 'Walk-in Customer'}
                    </span>
                    <span className="flex items-center gap-1 font-mono text-[11px] text-slate-500">
                        <Phone className="h-2.5 w-2.5 text-slate-400" />
                        {b.customer?.phone_e164 || '-'}
                    </span>
                </div>
            ),
        },
        {
            key: 'service',
            header: 'LAYANAN',
            render: (b: BookingItem) => (
                <div>
                    <span className="block text-xs font-medium text-slate-900">
                        {b.service_snapshot?.name || b.service?.name || '-'}
                    </span>
                    <span className="flex items-center gap-1 text-[11px] text-slate-500">
                        <Clock className="h-3 w-3 text-slate-400" />
                        {b.service_snapshot?.duration_minutes || 60} menit
                    </span>
                </div>
            ),
        },
        {
            key: 'schedule',
            header: 'JADWAL WAKTU',
            render: (b: BookingItem) => (
                <div>
                    <span className="flex items-center gap-1 text-xs font-medium text-slate-900">
                        <Calendar className="h-3 w-3 text-slate-400" />
                        {new Date(b.start_at).toLocaleDateString('id-ID', {
                            day: 'numeric',
                            month: 'short',
                            year: 'numeric',
                        })}
                    </span>
                    <span className="font-mono text-[11px] text-slate-500">
                        {new Date(b.start_at).toLocaleTimeString('id-ID', {
                            hour: '2-digit',
                            minute: '2-digit',
                        })}{' '}
                        -{' '}
                        {new Date(b.end_at).toLocaleTimeString('id-ID', {
                            hour: '2-digit',
                            minute: '2-digit',
                        })}
                    </span>
                </div>
            ),
        },
        {
            key: 'staff',
            header: 'STAF / RESOURCE',
            render: (b: BookingItem) => {
                const allocs = b.allocations || [];
                if (allocs.length === 0) {
                    return (
                        <span className="text-[11px] text-slate-400 italic">
                            Auto-assign
                        </span>
                    );
                }
                return (
                    <div className="flex flex-col gap-0.5">
                        {allocs.slice(0, 2).map((a) => (
                            <span
                                key={a.id}
                                className="inline-flex items-center gap-1 text-[11px] text-slate-700"
                            >
                                <UserCheck className="h-3 w-3 text-slate-400" />
                                {a.resource?.name || `ID #${a.resource_id}`}
                            </span>
                        ))}
                    </div>
                );
            },
        },
        {
            key: 'status',
            header: 'STATUS',
            render: (b: BookingItem) => (
                <span
                    className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-medium ${
                        b.status_category === 'COMPLETED'
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                            : b.status_category === 'CONFIRMED'
                              ? 'border border-blue-200 bg-blue-50 text-blue-700'
                              : b.status_category === 'CHECKED_IN'
                                ? 'border border-indigo-200 bg-indigo-50 text-indigo-700'
                                : b.status_category === 'IN_PROGRESS'
                                  ? 'border border-amber-200 bg-amber-50 text-amber-700'
                                  : b.status_category === 'CANCELLED' ||
                                      b.status_category === 'NO_SHOW'
                                    ? 'border border-rose-200 bg-rose-50 text-rose-700'
                                    : 'border border-slate-200 bg-slate-100 text-slate-600'
                    }`}
                >
                    {b.status_category}
                </span>
            ),
        },
        {
            key: 'total',
            header: 'TOTAL BIAYA',
            align: 'right' as const,
            render: (b: BookingItem) => (
                <div>
                    <span className="block text-xs font-semibold text-slate-900">
                        Rp {Number(b.total_idr).toLocaleString('id-ID')}
                    </span>
                    <span
                        className={`text-[10px] font-medium ${
                            b.payment_status === 'PAID'
                                ? 'text-emerald-600'
                                : 'text-slate-400'
                        }`}
                    >
                        {b.payment_status}
                    </span>
                </div>
            ),
        },
        {
            key: 'actions',
            header: 'AKSI',
            align: 'right' as const,
            render: (b: BookingItem) => (
                <div className="flex items-center justify-end gap-1">
                    {b.status_category === 'CONFIRMED' && (
                        <button
                            type="button"
                            onClick={() => onCheckIn?.(b)}
                            title="Check-In Customer (Hadir)"
                            className="rounded p-1 text-emerald-600 transition-colors hover:bg-emerald-50 hover:text-emerald-700"
                        >
                            <UserCheck className="h-3.5 w-3.5" />
                        </button>
                    )}
                    <button
                        type="button"
                        onClick={() => onOpenDetail(b)}
                        title="Lihat Detail"
                        className="rounded p-1 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900"
                    >
                        <Eye className="h-3.5 w-3.5" />
                    </button>
                    {['CONFIRMED', 'PENDING'].includes(b.status_category) && (
                        <button
                            type="button"
                            onClick={() => onOpenReschedule(b)}
                            title="Reschedule / Pindahkan Jadwal"
                            className="rounded p-1 text-slate-500 transition-colors hover:bg-slate-100 hover:text-blue-600"
                        >
                            <History className="h-3.5 w-3.5" />
                        </button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <div className="space-y-3">
            <DataTable
                columns={columns}
                data={bookings.data}
                keyExtractor={(item) => item.id}
                emptyText="Belum ada transaksi booking yang cocok dengan filter pencarian."
            />

            {/* Pagination */}
            {bookings.total > bookings.per_page && (
                <div className="flex items-center justify-between rounded-[12px] border border-slate-200 bg-white px-4 py-2.5 text-xs text-slate-500">
                    <div>
                        Menampilkan{' '}
                        <span className="font-medium text-slate-900">
                            {bookings.from || 0}
                        </span>{' '}
                        -{' '}
                        <span className="font-medium text-slate-900">
                            {bookings.to || 0}
                        </span>{' '}
                        dari{' '}
                        <span className="font-medium text-slate-900">
                            {bookings.total}
                        </span>{' '}
                        booking
                    </div>

                    <div className="flex items-center gap-1">
                        {bookings.links.map((link, idx) => {
                            if (!link.url) {
                                return (
                                    <span
                                        key={idx}
                                        className="px-2 py-1 text-slate-300 select-none"
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                );
                            }
                            return (
                                <Link
                                    key={idx}
                                    href={link.url}
                                    preserveState
                                    className={`rounded px-2.5 py-1 text-xs font-medium transition-colors ${
                                        link.active
                                            ? 'bg-blue-600 text-white'
                                            : 'text-slate-600 hover:bg-slate-100'
                                    }`}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
};
