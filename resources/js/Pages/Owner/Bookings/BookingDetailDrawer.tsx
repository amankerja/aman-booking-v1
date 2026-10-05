import {
    AlertCircle,
    Calendar,
    CheckCircle2,
    Clock,
    DollarSign,
    ExternalLink,
    FileText,
    History,
    MessageCircle,
    Paperclip,
    Phone,
    Play,
    User,
    UserCheck,
    X,
    XCircle,
} from 'lucide-react';
import React, { useState } from 'react';
import { Button, Drawer, useToast } from '../../../Components/ui';
import { BookingItem } from './types';

interface BookingDetailDrawerProps {
    isOpen: boolean;
    onClose: () => void;
    booking: BookingItem | null;
    onStatusChanged: (updatedBooking: BookingItem) => void;
    onOpenReschedule: (booking: BookingItem) => void;
}

export const BookingDetailDrawer: React.FC<BookingDetailDrawerProps> = ({
    isOpen,
    onClose,
    booking,
    onStatusChanged,
    onOpenReschedule,
}) => {
    const toast = useToast();
    const [isUpdatingStatus, setIsUpdatingStatus] = useState(false);

    if (!booking) return null;

    const handleTransition = async (
        targetCategory: string,
        reason?: string
    ) => {
        setIsUpdatingStatus(true);
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
                    status: targetCategory,
                    reason: reason || null,
                }),
            });

            const data = await res.json();
            if (res.ok) {
                toast.success(
                    data.message || `Status diubah ke ${targetCategory}`
                );
                onStatusChanged(data.booking);
            } else {
                toast.error(data.message || 'Transisi status ditolak.');
            }
        } catch {
            toast.error('Gagal memperbarui status booking.');
        } finally {
            setIsUpdatingStatus(false);
        }
    };

    const cleanPhone = booking.customer?.phone_e164
        ? booking.customer.phone_e164.replace(/\D/g, '')
        : '';
    const waLink = cleanPhone
        ? `https://wa.me/${cleanPhone}?text=Halo%20${encodeURIComponent(
              booking.customer?.name || ''
          )},%20mengenai%20booking%20${booking.code}%20di%20layanan%20${encodeURIComponent(
              booking.service?.name || ''
          )}.`
        : null;

    return (
        <Drawer
            isOpen={isOpen}
            onClose={onClose}
            title={
                <div className="flex w-full items-center justify-between pr-6">
                    <span className="font-mono text-sm font-semibold text-slate-900">
                        {booking.code}
                    </span>
                    <span
                        className={`rounded-full px-2 py-0.5 text-[10px] font-medium ${
                            booking.status_category === 'COMPLETED'
                                ? 'border border-emerald-200 bg-emerald-50 text-emerald-700'
                                : booking.status_category === 'CONFIRMED'
                                  ? 'border border-blue-200 bg-blue-50 text-blue-700'
                                  : booking.status_category === 'CHECKED_IN'
                                    ? 'border border-indigo-200 bg-indigo-50 text-indigo-700'
                                    : booking.status_category === 'IN_PROGRESS'
                                      ? 'border border-amber-200 bg-amber-50 text-amber-700'
                                      : booking.status_category ===
                                              'CANCELLED' ||
                                          booking.status_category === 'NO_SHOW'
                                        ? 'border border-rose-200 bg-rose-50 text-rose-700'
                                        : 'border border-slate-200 bg-slate-100 text-slate-600'
                        }`}
                    >
                        {booking.status_category}
                    </span>
                </div>
            }
            size="md"
        >
            <div className="space-y-4 text-xs">
                {/* 1. Quick Actions Bar */}
                <div className="rounded-[10px] border border-slate-200 bg-slate-50 p-2.5">
                    <span className="mb-2 block text-[11px] font-semibold tracking-wider text-slate-600 uppercase">
                        Aksi Cepat Transisi Status
                    </span>
                    <div className="flex flex-wrap gap-1.5">
                        {booking.status_category === 'PENDING' && (
                            <>
                                <Button
                                    size="sm"
                                    variant="primary"
                                    onClick={() =>
                                        handleTransition('CONFIRMED')
                                    }
                                    isLoading={isUpdatingStatus}
                                    className="gap-1 text-[11px]"
                                >
                                    <CheckCircle2 className="h-3 w-3" />
                                    Konfirmasi Booking
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        handleTransition(
                                            'CANCELLED',
                                            'Dibatalkan oleh Owner'
                                        )
                                    }
                                    isLoading={isUpdatingStatus}
                                    className="gap-1 text-[11px] text-rose-600"
                                >
                                    <X className="h-3 w-3" />
                                    Tolak / Batalkan
                                </Button>
                            </>
                        )}

                        {booking.status_category === 'CONFIRMED' && (
                            <>
                                <Button
                                    size="sm"
                                    variant="primary"
                                    onClick={() =>
                                        handleTransition('CHECKED_IN')
                                    }
                                    isLoading={isUpdatingStatus}
                                    className="gap-1 text-[11px]"
                                >
                                    <UserCheck className="h-3 w-3" />
                                    Check-In Customer
                                </Button>
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    onClick={() => onOpenReschedule(booking)}
                                    className="gap-1 text-[11px]"
                                >
                                    <History className="h-3 w-3" />
                                    Reschedule
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => handleTransition('NO_SHOW')}
                                    isLoading={isUpdatingStatus}
                                    className="gap-1 text-[11px] text-amber-700"
                                >
                                    <AlertCircle className="h-3 w-3" />
                                    Tandai No-Show
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        handleTransition(
                                            'CANCELLED',
                                            'Dibatalkan customer'
                                        )
                                    }
                                    isLoading={isUpdatingStatus}
                                    className="gap-1 text-[11px] text-rose-600"
                                >
                                    <XCircle className="h-3 w-3" />
                                    Batalkan
                                </Button>
                            </>
                        )}

                        {booking.status_category === 'CHECKED_IN' && (
                            <>
                                <Button
                                    size="sm"
                                    variant="primary"
                                    onClick={() =>
                                        handleTransition('IN_PROGRESS')
                                    }
                                    isLoading={isUpdatingStatus}
                                    className="gap-1 text-[11px]"
                                >
                                    <Play className="h-3 w-3" />
                                    Mulai Layanan
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        handleTransition('CANCELLED')
                                    }
                                    isLoading={isUpdatingStatus}
                                    className="gap-1 text-[11px] text-rose-600"
                                >
                                    Batalkan
                                </Button>
                            </>
                        )}

                        {booking.status_category === 'IN_PROGRESS' && (
                            <Button
                                size="sm"
                                variant="primary"
                                onClick={() => handleTransition('COMPLETED')}
                                isLoading={isUpdatingStatus}
                                className="gap-1 bg-emerald-600 text-[11px] hover:bg-emerald-700"
                            >
                                <CheckCircle2 className="h-3 w-3" />
                                Selesaikan Layanan (Completed)
                            </Button>
                        )}

                        {booking.status_category === 'NO_SHOW' && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    handleTransition(
                                        'CONFIRMED',
                                        'Koreksi status no-show'
                                    )
                                }
                                isLoading={isUpdatingStatus}
                                className="gap-1 text-[11px]"
                            >
                                Koreksi ke Terkonfirmasi
                            </Button>
                        )}

                        {['COMPLETED', 'CANCELLED', 'EXPIRED'].includes(
                            booking.status_category
                        ) && (
                            <span className="text-[11px] text-slate-400 italic">
                                Status ini bersifat final (terminal state).
                            </span>
                        )}
                    </div>
                </div>

                {/* 2. Customer Profile Card */}
                <div className="space-y-2 rounded-[10px] border border-slate-200 bg-white p-3">
                    <span className="flex items-center gap-1.5 text-xs font-semibold text-slate-900">
                        <User className="h-3.5 w-3.5 text-slate-500" />
                        Informasi Pelanggan
                    </span>

                    <div className="grid grid-cols-2 gap-2 text-[11px] text-slate-600">
                        <div>
                            <span className="block text-slate-400">Nama</span>
                            <span className="font-medium text-slate-900">
                                {booking.customer?.name || '-'}
                            </span>
                        </div>
                        <div>
                            <span className="block text-slate-400">
                                No-Show History
                            </span>
                            <span
                                className={`font-medium ${
                                    (booking.customer?.no_show_count || 0) > 0
                                        ? 'text-rose-600'
                                        : 'text-slate-900'
                                }`}
                            >
                                {booking.customer?.no_show_count || 0} kali
                            </span>
                        </div>
                        <div className="col-span-2 flex items-center justify-between pt-1">
                            <span className="flex items-center gap-1 font-mono text-slate-700">
                                <Phone className="h-3 w-3 text-slate-400" />
                                {booking.customer?.phone_e164 || '-'}
                            </span>
                            {waLink && (
                                <a
                                    href={waLink}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-600 hover:underline"
                                >
                                    <MessageCircle className="h-3 w-3" />
                                    WhatsApp
                                    <ExternalLink className="h-2.5 w-2.5" />
                                </a>
                            )}
                        </div>
                    </div>
                </div>

                {/* 3. Service & Schedule Card */}
                <div className="space-y-2 rounded-[10px] border border-slate-200 bg-white p-3">
                    <span className="flex items-center gap-1.5 text-xs font-semibold text-slate-900">
                        <Clock className="h-3.5 w-3.5 text-slate-500" />
                        Layanan & Jadwal
                    </span>

                    <div className="grid grid-cols-2 gap-2 text-[11px] text-slate-600">
                        <div>
                            <span className="block text-slate-400">
                                Nama Layanan
                            </span>
                            <span className="font-medium text-slate-900">
                                {booking.service_snapshot?.name ||
                                    booking.service?.name ||
                                    '-'}
                            </span>
                        </div>
                        <div>
                            <span className="block text-slate-400">Durasi</span>
                            <span className="font-medium text-slate-900">
                                {booking.service_snapshot?.duration_minutes ||
                                    60}{' '}
                                menit
                            </span>
                        </div>
                        <div className="col-span-2">
                            <span className="block text-slate-400">
                                Jadwal Pelaksanaan
                            </span>
                            <span className="flex items-center gap-1.5 font-medium text-slate-900">
                                <Calendar className="h-3 w-3 text-slate-400" />
                                {new Date(booking.start_at).toLocaleDateString(
                                    'id-ID',
                                    {
                                        weekday: 'long',
                                        day: 'numeric',
                                        month: 'long',
                                        year: 'numeric',
                                    }
                                )}{' '}
                                (
                                {new Date(booking.start_at).toLocaleTimeString(
                                    'id-ID',
                                    {
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    }
                                )}{' '}
                                -{' '}
                                {new Date(booking.end_at).toLocaleTimeString(
                                    'id-ID',
                                    {
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    }
                                )}
                                )
                            </span>
                        </div>
                    </div>
                </div>

                {/* 4. Allocated Staff & Resources */}
                <div className="space-y-2 rounded-[10px] border border-slate-200 bg-white p-3">
                    <span className="flex items-center gap-1.5 text-xs font-semibold text-slate-900">
                        <UserCheck className="h-3.5 w-3.5 text-slate-500" />
                        Alokasi Staf & Resource
                    </span>

                    {booking.allocations && booking.allocations.length > 0 ? (
                        <div className="space-y-1.5">
                            {booking.allocations.map((alloc) => (
                                <div
                                    key={alloc.id}
                                    className="flex items-center justify-between rounded border border-slate-100 bg-slate-50 p-2 text-[11px]"
                                >
                                    <div>
                                        <span className="font-medium text-slate-900">
                                            {alloc.resource?.name ||
                                                `Resource #${alloc.resource_id}`}
                                        </span>
                                        <span className="ml-1.5 text-slate-400">
                                            {alloc.resource?.resource_type
                                                ?.name || ''}
                                        </span>
                                    </div>
                                    <span className="rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700">
                                        {alloc.status}
                                    </span>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-[11px] text-slate-400 italic">
                            Belum ada resource spesifik yang dialokasikan
                            (Auto-pool).
                        </p>
                    )}
                </div>

                {/* 5. Payment Details */}
                <div className="space-y-2 rounded-[10px] border border-slate-200 bg-white p-3">
                    <span className="flex items-center gap-1.5 text-xs font-semibold text-slate-900">
                        <DollarSign className="h-3.5 w-3.5 text-slate-500" />
                        Rincian Pembayaran
                    </span>

                    <div className="flex items-center justify-between text-[11px]">
                        <span className="text-slate-500">
                            Status Pembayaran:
                        </span>
                        <span
                            className={`rounded px-2 py-0.5 text-[10px] font-semibold ${
                                booking.payment_status === 'PAID'
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : booking.payment_status === 'PARTIAL'
                                      ? 'bg-blue-50 text-blue-700'
                                      : 'bg-amber-50 text-amber-700'
                            }`}
                        >
                            {booking.payment_status}
                        </span>
                    </div>

                    <div className="flex items-center justify-between border-t border-slate-100 pt-1 text-xs">
                        <span className="font-semibold text-slate-900">
                            Total Biaya:
                        </span>
                        <span className="text-sm font-bold text-slate-900">
                            Rp{' '}
                            {Number(booking.total_idr).toLocaleString('id-ID')}
                        </span>
                    </div>
                </div>

                {/* 6. Custom Form Fields (PRD 26, 27) */}
                {booking.custom_fields && booking.custom_fields.length > 0 && (
                    <div className="space-y-2 rounded-[10px] border border-slate-200 bg-white p-3">
                        <span className="flex items-center gap-1.5 text-xs font-semibold text-slate-900">
                            <FileText className="h-3.5 w-3.5 text-slate-500" />
                            Data Formulir Pemesanan
                        </span>
                        <div className="space-y-2 pt-1 text-[11px]">
                            {booking.custom_fields.map((cf) => (
                                <div
                                    key={cf.id}
                                    className="flex items-start justify-between border-b border-slate-100 pb-1.5 last:border-0 last:pb-0"
                                >
                                    <span className="text-slate-500">
                                        {cf.field_label}:
                                    </span>
                                    <div className="text-right font-medium text-slate-900">
                                        {cf.field_type === 'file' &&
                                        cf.download_url ? (
                                            <a
                                                href={cf.download_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex items-center gap-1 text-blue-600 hover:underline"
                                            >
                                                <Paperclip className="h-3 w-3" />
                                                {cf.display_value || 'Unduh Berkas'}
                                            </a>
                                        ) : (
                                            <span>
                                                {cf.display_value ||
                                                    cf.value_text ||
                                                    '-'}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* 7. Timeline & History */}
                {booking.status_history &&
                    booking.status_history.length > 0 && (
                        <div className="space-y-2 rounded-[10px] border border-slate-200 bg-white p-3">
                            <span className="flex items-center gap-1.5 text-xs font-semibold text-slate-900">
                                <History className="h-3.5 w-3.5 text-slate-500" />
                                Riwayat Transisi Status
                            </span>
                            <div className="max-h-36 space-y-2 overflow-y-auto pr-1">
                                {booking.status_history.map((hist) => (
                                    <div
                                        key={hist.id}
                                        className="border-l-2 border-slate-200 py-0.5 pl-2.5 text-[11px] text-slate-600"
                                    >
                                        <div className="flex items-center justify-between">
                                            <span className="font-medium text-slate-900">
                                                {hist.from_category || 'DIBUAT'}{' '}
                                                → {hist.to_category}
                                            </span>
                                            <span className="text-[10px] text-slate-400">
                                                {new Date(
                                                    hist.created_at
                                                ).toLocaleTimeString('id-ID', {
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                })}
                                            </span>
                                        </div>
                                        {hist.reason && (
                                            <p className="mt-0.5 text-slate-500 italic">
                                                "{hist.reason}"
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
            </div>
        </Drawer>
    );
};
