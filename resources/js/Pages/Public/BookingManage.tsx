import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import {
    AlertCircle,
    AlertTriangle,
    Calendar,
    CalendarCheck,
    CheckCircle2,
    Clock,
    CreditCard,
    Download,
    HelpCircle,
    Loader2,
    MapPin,
    MessageSquare,
    QrCode,
    RotateCcw,
    User,
    UserCheck,
    X,
    XCircle,
} from 'lucide-react';
import React, { useEffect, useState } from 'react';
import { Badge } from '../../Components/ui/Badge';
import { Button } from '../../Components/ui/Button';
import { PublicLayout } from '../../Layouts/PublicLayout';

interface SlotItem {
    start_time: string;
    end_time: string;
    start_at: string;
    end_at: string;
    available: boolean;
    reason?: string | null;
    message?: string | null;
}

interface BookingManageProps {
    business: {
        name: string;
        slug: string;
        timezone?: string;
        whatsapp?: string;
        phone?: string;
        address?: string;
        booking_rules?: {
            reschedule_deadline_hours: number;
            cancellation_deadline_hours: number;
            max_reschedule_times: number;
        };
    };
    booking: {
        code: string;
        status: string;
        status_category: string;
        start_at: string;
        end_at: string;
        total_idr?: number;
        reschedule_count: number;
        manage_token: string;
        staff_name?: string | null;
        resource_name?: string | null;
        service?: {
            id?: number;
            name: string;
            duration_minutes: number;
            price_idr: number;
        };
        customer?: {
            name: string;
            phone: string;
        };
    };
    policy: {
        can_reschedule: boolean;
        reschedule_disabled_reason?: string | null;
        can_cancel: boolean;
        cancel_disabled_reason?: string | null;
        reschedule_deadline_hours: number;
        cancel_deadline_hours: number;
        max_reschedules: number;
        reschedules_remaining: number;
    };
    invoice?: {
        id: number;
        invoice_number: string;
        payment_model: string;
        amount_total_idr: number;
        amount_due_idr: number;
        amount_paid_idr: number;
        status: string;
        is_paid: boolean;
        payments?: Array<{
            id: number;
            payment_number: string;
            provider: string;
            payment_method: string;
            amount_idr: number;
            status: string;
            paid_at?: string;
        }>;
    } | null;
}

export default function BookingManage({
    business,
    booking: initialBooking,
    policy: initialPolicy,
    invoice,
}: BookingManageProps) {
    const [booking, setBooking] = useState(initialBooking);
    const [policy, setPolicy] = useState(initialPolicy);
    const [payingOnline, setPayingOnline] = useState(false);
    const [onlinePaymentData, setOnlinePaymentData] = useState<{
        checkout_url?: string;
        snap_token?: string;
        qr_string?: string;
        provider?: string;
    } | null>(null);

    // Reschedule state
    const [isRescheduling, setIsRescheduling] = useState(false);
    const [selectedDate, setSelectedDate] = useState<string>('');
    const [slots, setSlots] = useState<SlotItem[]>([]);
    const [loadingSlots, setLoadingSlots] = useState(false);
    const [selectedSlot, setSelectedSlot] = useState<SlotItem | null>(null);
    const [rescheduleReason, setRescheduleReason] = useState('');
    const [submittingReschedule, setSubmittingReschedule] = useState(false);
    const [rescheduleError, setRescheduleError] = useState<string | null>(null);
    const [actionSuccessMsg, setActionSuccessMsg] = useState<string | null>(
        null
    );

    // Cancel state
    const [isCancelling, setIsCancelling] = useState(false);
    const [cancelReason, setCancelReason] = useState('');
    const [submittingCancel, setSubmittingCancel] = useState(false);
    const [cancelError, setCancelError] = useState<string | null>(null);

    // 14-day date options
    const [availableDates, setAvailableDates] = useState<
        {
            date: string;
            dayName: string;
            dayNumber: string;
            monthName: string;
        }[]
    >([]);

    useEffect(() => {
        const dates = [];
        const today = new Date();
        for (let i = 0; i < 14; i++) {
            const d = new Date();
            d.setDate(today.getDate() + i);
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            const dateStr = `${yyyy}-${mm}-${dd}`;

            dates.push({
                date: dateStr,
                dayName: d.toLocaleDateString('id-ID', { weekday: 'short' }),
                dayNumber: String(d.getDate()),
                monthName: d.toLocaleDateString('id-ID', { month: 'short' }),
            });
        }
        setAvailableDates(dates);
        if (dates.length > 0) {
            setSelectedDate(dates[0].date);
        }
    }, []);

    // Fetch availability slots when date changes in reschedule mode
    useEffect(() => {
        if (!isRescheduling || !selectedDate || !booking.service?.id) return;

        setLoadingSlots(true);
        setSelectedSlot(null);
        setRescheduleError(null);

        axios
            .get(`/${business.slug}/availability`, {
                params: {
                    service_id: booking.service.id,
                    date: selectedDate,
                },
            })
            .then((res) => {
                if (res.data && Array.isArray(res.data.slots)) {
                    setSlots(res.data.slots);
                } else {
                    setSlots([]);
                }
            })
            .catch(() => {
                setSlots([]);
                setRescheduleError('Gagal memuat jadwal ketersediaan.');
            })
            .finally(() => {
                setLoadingSlots(false);
            });
    }, [isRescheduling, selectedDate, booking.service?.id, business.slug]);

    const handleExecuteReschedule = async () => {
        if (!selectedSlot) return;

        setSubmittingReschedule(true);
        setRescheduleError(null);
        setActionSuccessMsg(null);

        try {
            const res = await axios.post(
                `/${business.slug}/booking/manage/${booking.manage_token}/reschedule`,
                {
                    new_start_at: selectedSlot.start_at,
                    reason:
                        rescheduleReason || 'Customer reschedule via portal',
                }
            );

            if (res.data.success) {
                setBooking((prev) => ({
                    ...prev,
                    start_at: res.data.booking.start_at,
                    end_at: res.data.booking.end_at,
                    reschedule_count: res.data.booking.reschedule_count,
                }));

                const newRemaining = Math.max(
                    0,
                    policy.max_reschedules - res.data.booking.reschedule_count
                );
                setPolicy((prev) => ({
                    ...prev,
                    reschedules_remaining: newRemaining,
                    can_reschedule: newRemaining > 0,
                    reschedule_disabled_reason:
                        newRemaining <= 0
                            ? `Batas perubahan jadwal (${prev.max_reschedules}x) telah tercapai.`
                            : prev.reschedule_disabled_reason,
                }));

                setIsRescheduling(false);
                setSelectedSlot(null);
                setActionSuccessMsg('Jadwal reservasi Anda berhasil diubah.');
            }
        } catch (err: unknown) {
            if (axios.isAxiosError(err)) {
                const data = err.response?.data as
                    { code?: string; message?: string } | undefined;
                if (data?.code === 'SLOT_TAKEN') {
                    setRescheduleError(
                        'Slot tersebut baru saja dipesan customer lain. Silakan pilih waktu lain.'
                    );
                } else if (data?.message) {
                    setRescheduleError(data.message);
                } else {
                    setRescheduleError(
                        'Terjadi kesalahan saat memproses perubahan jadwal.'
                    );
                }
            } else {
                setRescheduleError(
                    'Terjadi kesalahan saat memproses perubahan jadwal.'
                );
            }
        } finally {
            setSubmittingReschedule(false);
        }
    };

    const handleExecuteCancel = async () => {
        setSubmittingCancel(true);
        setCancelError(null);
        setActionSuccessMsg(null);

        try {
            const res = await axios.post(
                `/${business.slug}/booking/manage/${booking.manage_token}/cancel`,
                {
                    reason: cancelReason || 'Customer requested cancellation',
                }
            );

            if (res.data.success) {
                setBooking((prev) => ({
                    ...prev,
                    status: 'CANCELLED',
                    status_category: 'CANCELLED',
                }));

                setPolicy((prev) => ({
                    ...prev,
                    can_reschedule: false,
                    reschedule_disabled_reason:
                        'Reservasi ini telah dibatalkan.',
                    can_cancel: false,
                    cancel_disabled_reason: 'Reservasi ini telah dibatalkan.',
                }));

                setIsCancelling(false);
                setActionSuccessMsg(
                    'Reservasi Anda telah berhasil dibatalkan.'
                );
            }
        } catch (err: unknown) {
            if (axios.isAxiosError(err)) {
                const data = err.response?.data as
                    { message?: string } | undefined;
                if (data?.message) {
                    setCancelError(data.message);
                } else {
                    setCancelError(
                        'Terjadi kesalahan saat memproses pembatalan reservasi.'
                    );
                }
            } else {
                setCancelError(
                    'Terjadi kesalahan saat memproses pembatalan reservasi.'
                );
            }
        } finally {
            setSubmittingCancel(false);
        }
    };

    const handleOnlinePayment = async () => {
        setPayingOnline(true);
        try {
            const res = await axios.post(
                `/${business.slug}/booking/manage/${booking.manage_token}/pay`
            );
            if (res.data.checkout_url) {
                window.location.href = res.data.checkout_url;
            } else if (res.data.snap_token) {
                const win = window as unknown as { snap?: { pay: (token: string) => void } };
                if (typeof win.snap !== 'undefined') {
                    win.snap.pay(res.data.snap_token);
                } else {
                    setOnlinePaymentData(res.data);
                }
            } else {
                setOnlinePaymentData(res.data);
            }
        } catch (err: unknown) {
            if (axios.isAxiosError(err)) {
                const data = err.response?.data as { message?: string } | undefined;
                alert(data?.message || 'Gagal memproses sesi pembayaran online.');
            } else {
                alert('Gagal memproses sesi pembayaran online.');
            }
        } finally {
            setPayingOnline(false);
        }
    };

    const startDate = new Date(booking.start_at);
    const formattedDate = startDate.toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    const formattedTime = startDate.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    });

    const formatCurrency = (val: number) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(val);
    };

    // Format WA link
    const waNumber = (business.whatsapp || business.phone || '')
        .replace(/\D/g, '')
        .replace(/^0/, '62');
    const waText = `Halo ${business.name}, saya ingin menanyakan perihal kelola reservasi saya dengan kode: ${booking.code} (${booking.service?.name}).`;
    const waUrl = waNumber
        ? `https://wa.me/${waNumber}?text=${encodeURIComponent(waText)}`
        : null;

    const calendarDownloadUrl = `/${business.slug}/booking/manage/${booking.manage_token}/calendar.ics`;

    // Semantic status styling
    const renderStatusBadge = (status: string) => {
        const s = status.toUpperCase();
        if (s === 'CONFIRMED') {
            return (
                <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                    <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" />
                    Terkonfirmasi
                </span>
            );
        }
        if (s === 'PENDING') {
            return (
                <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                    <Clock className="h-3.5 w-3.5 text-amber-600" />
                    Menunggu Pembayaran
                </span>
            );
        }
        if (s === 'CANCELLED') {
            return (
                <span className="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">
                    <XCircle className="h-3.5 w-3.5 text-rose-600" />
                    Dibatalkan
                </span>
            );
        }
        if (s === 'COMPLETED') {
            return (
                <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                    Selesai
                </span>
            );
        }
        return (
            <Badge variant="neutral" size="sm">
                {status}
            </Badge>
        );
    };

    return (
        <PublicLayout
            title="Kelola Reservasi"
            businessName={business.name}
            businessPhone={business.whatsapp || business.phone}
        >
            <Head title={`Kelola Booking ${booking.code} - ${business.name}`} />

            <div className="mx-auto max-w-lg space-y-4">
                {/* Global Notification Banner */}
                {actionSuccessMsg && (
                    <div className="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-xs text-emerald-800">
                        <CheckCircle2 className="h-5 w-5 shrink-0 text-emerald-600" />
                        <div>
                            <div className="font-semibold">Berhasil!</div>
                            <div>{actionSuccessMsg}</div>
                        </div>
                    </div>
                )}

                {/* Main Booking Card */}
                <div className="rounded-[14px] border border-slate-200 bg-white p-6 shadow-xs sm:p-8">
                    {/* Header with Status and Code */}
                    <div className="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div>{renderStatusBadge(booking.status)}</div>
                        <div className="font-mono text-xs font-bold text-slate-500">
                            {booking.code}
                        </div>
                    </div>

                    <h1 className="mt-4 text-xl font-bold tracking-tight text-slate-900">
                        Rincian Reservasi
                    </h1>
                    <p className="mt-1 text-xs text-slate-500">
                        Kelola jadwal, ubah waktu, atau batalkan reservasi Anda
                        secara mandiri sesuai kebijakan layanan.
                    </p>

                    {/* Booking Details */}
                    <div className="mt-5 space-y-3.5 rounded-xl border border-slate-100 bg-slate-50/70 p-4 text-xs">
                        <div className="flex items-center justify-between">
                            <span className="flex items-center gap-2 text-slate-500">
                                <Calendar className="h-4 w-4 text-slate-400" />
                                Layanan
                            </span>
                            <span className="font-semibold text-slate-900">
                                {booking.service?.name}
                            </span>
                        </div>

                        <div className="flex items-center justify-between">
                            <span className="flex items-center gap-2 text-slate-500">
                                <Clock className="h-4 w-4 text-slate-400" />
                                Waktu
                            </span>
                            <div className="text-right">
                                <span className="font-semibold text-slate-900">
                                    {formattedDate}, {formattedTime} (
                                    {business.timezone || 'WIB'})
                                </span>
                                {booking.service?.duration_minutes && (
                                    <div className="text-[11px] text-slate-500">
                                        Durasi:{' '}
                                        {booking.service.duration_minutes} menit
                                    </div>
                                )}
                            </div>
                        </div>

                        {booking.staff_name && (
                            <div className="flex items-center justify-between">
                                <span className="flex items-center gap-2 text-slate-500">
                                    <UserCheck className="h-4 w-4 text-slate-400" />
                                    Staf / Petugas
                                </span>
                                <span className="font-medium text-slate-900">
                                    {booking.staff_name}
                                </span>
                            </div>
                        )}

                        {booking.resource_name && (
                            <div className="flex items-center justify-between">
                                <span className="flex items-center gap-2 text-slate-500">
                                    <CalendarCheck className="h-4 w-4 text-slate-400" />
                                    Ruangan / Resource
                                </span>
                                <span className="font-medium text-slate-900">
                                    {booking.resource_name}
                                </span>
                            </div>
                        )}

                        {booking.customer && (
                            <div className="flex items-center justify-between">
                                <span className="flex items-center gap-2 text-slate-500">
                                    <User className="h-4 w-4 text-slate-400" />
                                    Atas Nama
                                </span>
                                <span className="font-medium text-slate-900">
                                    {booking.customer.name} (
                                    {booking.customer.phone})
                                </span>
                            </div>
                        )}

                        {business.address && (
                            <div className="flex items-start justify-between">
                                <span className="flex items-center gap-2 text-slate-500">
                                    <MapPin className="h-4 w-4 shrink-0 text-slate-400" />
                                    Lokasi
                                </span>
                                <span className="max-w-[220px] text-right text-slate-700">
                                    {business.address}
                                </span>
                            </div>
                        )}

                        {typeof booking.total_idr === 'number' &&
                            booking.total_idr > 0 && (
                                <div className="flex items-center justify-between border-t border-slate-200/70 pt-2.5">
                                    <span className="font-medium text-slate-700">
                                        Total Biaya
                                    </span>
                                    <span className="font-bold text-slate-900">
                                        {formatCurrency(booking.total_idr)}
                                    </span>
                                </div>
                            )}
                    </div>

                    {/* Invoice & Online Payment Section (Phase 4.1) */}
                    {invoice && (
                        <div className="mt-4 rounded-xl border border-blue-100 bg-blue-50/50 p-4 text-xs">
                            <div className="flex items-center justify-between border-b border-blue-100 pb-2.5">
                                <div className="flex items-center gap-1.5 font-semibold text-slate-900">
                                    <CreditCard className="h-4 w-4 text-blue-600" />
                                    <span>Informasi Tagihan & Pembayaran</span>
                                </div>
                                <span className="font-mono text-[11px] font-bold text-slate-600">
                                    {invoice.invoice_number}
                                </span>
                            </div>

                            <div className="mt-3 space-y-2">
                                <div className="flex justify-between">
                                    <span className="text-slate-500">Skema Pembayaran:</span>
                                    <span className="font-medium text-slate-800 capitalize">
                                        {invoice.payment_model.replace('_', ' ')}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-slate-500">Total Biaya:</span>
                                    <span className="font-semibold text-slate-900">
                                        {formatCurrency(invoice.amount_total_idr)}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-slate-500">Sudah Dibayar:</span>
                                    <span className="font-semibold text-emerald-600">
                                        {formatCurrency(invoice.amount_paid_idr)}
                                    </span>
                                </div>
                                <div className="flex justify-between border-t border-blue-100 pt-2">
                                    <span className="font-semibold text-slate-700">Sisa Tagihan / DP:</span>
                                    <span className="font-bold text-slate-900">
                                        {formatCurrency(Math.max(0, invoice.amount_due_idr - invoice.amount_paid_idr))}
                                    </span>
                                </div>
                            </div>

                            <div className="mt-4 flex items-center justify-between pt-2 border-t border-blue-100">
                                <div>
                                    {invoice.is_paid ? (
                                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                            <CheckCircle2 className="h-3 w-3 text-emerald-600" />
                                            Lunas
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                                            <Clock className="h-3 w-3 text-amber-600" />
                                            Menunggu Pembayaran
                                        </span>
                                    )}
                                </div>

                                {!invoice.is_paid && (
                                    <Button
                                        size="sm"
                                        variant="primary"
                                        onClick={handleOnlinePayment}
                                        isLoading={payingOnline}
                                    >
                                        <CreditCard className="mr-1.5 h-3.5 w-3.5" />
                                        Bayar Sekarang
                                    </Button>
                                )}
                            </div>

                            {onlinePaymentData && !invoice.is_paid && (
                                <div className="mt-3 rounded-lg border border-blue-200 bg-white p-3 text-center">
                                    <div className="font-semibold text-slate-800 mb-1">
                                        Sesi Pembayaran Online
                                    </div>
                                    {onlinePaymentData.checkout_url && (
                                        <a
                                            href={onlinePaymentData.checkout_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 underline"
                                        >
                                            Buka Halaman Pembayaran Midtrans / Xendit &rarr;
                                        </a>
                                    )}
                                    {onlinePaymentData.qr_string && (
                                        <div className="mt-2 flex flex-col items-center">
                                            <div className="text-[11px] text-slate-500 mb-1">
                                                Scan QRIS melalui GoPay / BCA / OVO / Dana / ShopeePay:
                                            </div>
                                            <div className="p-2 border border-slate-200 rounded-lg bg-white">
                                                <QrCode className="h-28 w-28 text-slate-800" />
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    )}

                    {/* Policy Summary Card */}
                    <div className="mt-4 rounded-xl border border-slate-200 bg-white p-4 text-xs text-slate-600">
                        <div className="flex items-center gap-2 font-semibold text-slate-800">
                            <HelpCircle className="h-4 w-4 text-blue-600" />
                            Ketentuan Perubahan & Pembatalan
                        </div>
                        <ul className="mt-2 space-y-1.5 text-[11px] text-slate-500">
                            <li>
                                • Reschedule maksimal{' '}
                                <strong className="text-slate-700">
                                    {policy.max_reschedules} kali
                                </strong>{' '}
                                (sisa:{' '}
                                <strong className="text-slate-800">
                                    {policy.reschedules_remaining} kali
                                </strong>
                                ) hingga{' '}
                                <strong className="text-slate-700">
                                    {policy.reschedule_deadline_hours} jam
                                </strong>{' '}
                                sebelum jadwal.
                            </li>
                            <li>
                                • Pembatalan dapat dilakukan hingga{' '}
                                <strong className="text-slate-700">
                                    {policy.cancel_deadline_hours} jam
                                </strong>{' '}
                                sebelum jadwal.
                            </li>
                        </ul>
                    </div>

                    {/* Action Buttons: Reschedule & Cancel */}
                    {booking.status_category !== 'CANCELLED' &&
                        booking.status_category !== 'COMPLETED' && (
                            <div className="mt-6 flex flex-col gap-2.5 border-t border-slate-100 pt-5">
                                {/* Reschedule CTA */}
                                <div>
                                    <Button
                                        variant="primary"
                                        className="w-full justify-center gap-2"
                                        disabled={!policy.can_reschedule}
                                        onClick={() =>
                                            setIsRescheduling(!isRescheduling)
                                        }
                                    >
                                        <RotateCcw className="h-4 w-4" />
                                        Ubah Jadwal (Reschedule)
                                    </Button>
                                    {!policy.can_reschedule &&
                                        policy.reschedule_disabled_reason && (
                                            <p className="mt-1.5 text-center text-[11px] text-amber-600">
                                                {
                                                    policy.reschedule_disabled_reason
                                                }
                                            </p>
                                        )}
                                </div>

                                {/* Cancel CTA */}
                                <div>
                                    <Button
                                        variant="ghost"
                                        className="w-full justify-center gap-2 text-rose-600 hover:bg-rose-50 hover:text-rose-700"
                                        disabled={!policy.can_cancel}
                                        onClick={() => setIsCancelling(true)}
                                    >
                                        <XCircle className="h-4 w-4 text-rose-500" />
                                        Batalkan Reservasi
                                    </Button>
                                    {!policy.can_cancel &&
                                        policy.cancel_disabled_reason && (
                                            <p className="mt-1 text-center text-[11px] text-slate-500">
                                                {policy.cancel_disabled_reason}
                                            </p>
                                        )}
                                </div>
                            </div>
                        )}

                    {/* Reschedule Interactive Panel */}
                    {isRescheduling && (
                        <div className="mt-6 rounded-xl border border-blue-200 bg-blue-50/20 p-4 sm:p-5">
                            <div className="flex items-center justify-between border-b border-blue-100 pb-3">
                                <div>
                                    <h3 className="text-sm font-bold text-slate-900">
                                        Pilih Waktu Baru
                                    </h3>
                                    <p className="text-[11px] text-slate-500">
                                        Pilih tanggal dan jam baru yang tersedia
                                    </p>
                                </div>
                                <button
                                    onClick={() => setIsRescheduling(false)}
                                    className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                                >
                                    <X className="h-5 w-5" />
                                </button>
                            </div>

                            {/* Date Selector Strip */}
                            <div className="mt-4">
                                <label className="text-[11px] font-semibold text-slate-700">
                                    Pilih Tanggal:
                                </label>
                                <div className="no-scrollbar mt-2 flex gap-2 overflow-x-auto pb-1">
                                    {availableDates.map((item) => {
                                        const isSelected =
                                            selectedDate === item.date;
                                        return (
                                            <button
                                                key={item.date}
                                                type="button"
                                                onClick={() =>
                                                    setSelectedDate(item.date)
                                                }
                                                className={`flex min-w-[62px] flex-col items-center rounded-xl border p-2 text-center transition-all ${
                                                    isSelected
                                                        ? 'border-blue-600 bg-blue-600 text-white shadow-xs'
                                                        : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'
                                                }`}
                                            >
                                                <span
                                                    className={`text-[10px] font-medium uppercase ${isSelected ? 'text-blue-100' : 'text-slate-400'}`}
                                                >
                                                    {item.dayName}
                                                </span>
                                                <span className="text-base font-bold">
                                                    {item.dayNumber}
                                                </span>
                                                <span
                                                    className={`text-[9px] ${isSelected ? 'text-blue-100' : 'text-slate-500'}`}
                                                >
                                                    {item.monthName}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            {/* Slot Picker */}
                            <div className="mt-4">
                                <label className="text-[11px] font-semibold text-slate-700">
                                    Pilih Jam ({business.timezone || 'WIB'}):
                                </label>

                                {loadingSlots ? (
                                    <div className="mt-4 flex items-center justify-center py-6 text-xs text-slate-500">
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin text-blue-600" />
                                        Memeriksa ketersediaan slot...
                                    </div>
                                ) : slots.length === 0 ? (
                                    <div className="mt-3 rounded-lg border border-slate-200 bg-white p-4 text-center text-xs text-slate-500">
                                        Tidak ada slot tersedia pada tanggal
                                        ini. Silakan pilih tanggal lain.
                                    </div>
                                ) : (
                                    <div className="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-4">
                                        {slots.map((slot) => {
                                            const isSelected =
                                                selectedSlot?.start_at ===
                                                slot.start_at;
                                            return (
                                                <button
                                                    key={slot.start_at}
                                                    type="button"
                                                    disabled={!slot.available}
                                                    onClick={() =>
                                                        setSelectedSlot(slot)
                                                    }
                                                    className={`rounded-lg border px-2 py-2 text-center text-xs font-semibold transition-all ${
                                                        !slot.available
                                                            ? 'cursor-not-allowed border-slate-100 bg-slate-100 text-slate-400'
                                                            : isSelected
                                                              ? 'border-blue-600 bg-blue-600 text-white shadow-xs'
                                                              : 'border-slate-200 bg-white text-slate-800 hover:border-blue-400'
                                                    }`}
                                                >
                                                    {slot.start_time}
                                                </button>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>

                            {/* Reschedule Reason Input */}
                            <div className="mt-4">
                                <label className="text-[11px] font-semibold text-slate-700">
                                    Alasan perubahan jadwal (opsional):
                                </label>
                                <input
                                    type="text"
                                    placeholder="Contoh: Ada keperluan mendadak"
                                    value={rescheduleReason}
                                    onChange={(e) =>
                                        setRescheduleReason(e.target.value)
                                    }
                                    className="mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:outline-hidden"
                                />
                            </div>

                            {/* Reschedule Error message */}
                            {rescheduleError && (
                                <div className="mt-3 flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 p-2.5 text-xs text-rose-700">
                                    <AlertCircle className="h-4 w-4 shrink-0 text-rose-500" />
                                    <span>{rescheduleError}</span>
                                </div>
                            )}

                            {/* Submission Confirmation */}
                            {selectedSlot && (
                                <div className="mt-4 rounded-xl border border-blue-200 bg-white p-3 text-xs">
                                    <div className="text-[11px] font-medium text-slate-500">
                                        Konfirmasi Perubahan:
                                    </div>
                                    <div className="mt-1 font-semibold text-slate-900">
                                        Pindah ke{' '}
                                        {new Date(
                                            selectedSlot.start_at
                                        ).toLocaleDateString('id-ID', {
                                            weekday: 'long',
                                            day: 'numeric',
                                            month: 'long',
                                        })}{' '}
                                        jam {selectedSlot.start_time}
                                    </div>
                                </div>
                            )}

                            <div className="mt-4 flex gap-2">
                                <Button
                                    variant="ghost"
                                    className="w-1/3 text-xs text-slate-600"
                                    onClick={() => setIsRescheduling(false)}
                                >
                                    Batal
                                </Button>
                                <Button
                                    variant="primary"
                                    className="w-2/3 text-xs"
                                    disabled={
                                        !selectedSlot || submittingReschedule
                                    }
                                    onClick={handleExecuteReschedule}
                                >
                                    {submittingReschedule ? (
                                        <>
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            Memproses...
                                        </>
                                    ) : (
                                        'Simpan Perubahan'
                                    )}
                                </Button>
                            </div>
                        </div>
                    )}

                    {/* Cancellation Confirmation Dialog / Modal */}
                    {isCancelling && (
                        <div className="mt-6 rounded-xl border border-rose-200 bg-rose-50/40 p-4 sm:p-5">
                            <div className="flex items-start gap-3">
                                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600">
                                    <AlertTriangle className="h-5 w-5" />
                                </div>
                                <div className="flex-1">
                                    <h3 className="text-sm font-bold text-slate-900">
                                        Batalkan Reservasi?
                                    </h3>
                                    <p className="mt-1 text-xs text-slate-600">
                                        Apakah Anda yakin ingin membatalkan
                                        reservasi ini? Slot waktu Anda akan
                                        dilepaskan untuk customer lain. Tindakan
                                        ini tidak dapat diurungkan.
                                    </p>

                                    {/* Reason quick select & input */}
                                    <div className="mt-3">
                                        <label className="text-[11px] font-semibold text-slate-700">
                                            Alasan pembatalan (opsional):
                                        </label>
                                        <div className="mt-1.5 flex flex-wrap gap-1.5">
                                            {[
                                                'Keperluan mendadak',
                                                'Ingin ganti hari',
                                                'Kendala transportasi',
                                                'Lainnya',
                                            ].map((r) => (
                                                <button
                                                    key={r}
                                                    type="button"
                                                    onClick={() =>
                                                        setCancelReason(r)
                                                    }
                                                    className={`rounded-full border px-2.5 py-1 text-[11px] transition-colors ${
                                                        cancelReason === r
                                                            ? 'border-rose-400 bg-rose-100 text-rose-800'
                                                            : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'
                                                    }`}
                                                >
                                                    {r}
                                                </button>
                                            ))}
                                        </div>
                                        <input
                                            type="text"
                                            placeholder="Tulis alasan..."
                                            value={cancelReason}
                                            onChange={(e) =>
                                                setCancelReason(e.target.value)
                                            }
                                            className="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-900 focus:border-rose-500 focus:outline-hidden"
                                        />
                                    </div>

                                    {cancelError && (
                                        <div className="mt-2.5 text-xs text-rose-600">
                                            {cancelError}
                                        </div>
                                    )}

                                    <div className="mt-4 flex gap-2">
                                        <Button
                                            variant="ghost"
                                            className="w-1/2 text-xs text-slate-600"
                                            onClick={() =>
                                                setIsCancelling(false)
                                            }
                                            disabled={submittingCancel}
                                        >
                                            Kembali
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            className="w-1/2 text-xs"
                                            onClick={handleExecuteCancel}
                                            disabled={submittingCancel}
                                        >
                                            {submittingCancel ? (
                                                <>
                                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                    Membatalkan...
                                                </>
                                            ) : (
                                                'Ya, Batalkan'
                                            )}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Additional Help & Secondary Navigation */}
                    <div className="mt-6 space-y-2 border-t border-slate-100 pt-5">
                        {/* Simpan ke Kalender (.ics) */}
                        <a
                            href={calendarDownloadUrl}
                            download
                            className="block"
                        >
                            <Button
                                variant="secondary"
                                className="w-full justify-center gap-2 border-slate-300 text-slate-700 hover:bg-slate-50"
                            >
                                <Download className="h-4 w-4 text-slate-500" />
                                Simpan ke Kalender (.ics)
                            </Button>
                        </a>

                        {/* WhatsApp support */}
                        {waUrl && (
                            <a
                                href={waUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="block"
                            >
                                <Button
                                    variant="secondary"
                                    className="w-full justify-center gap-2 border-emerald-200 bg-emerald-50/40 text-emerald-800 hover:bg-emerald-100/60"
                                >
                                    <MessageSquare className="h-4 w-4 text-emerald-600" />
                                    Bantuan via WhatsApp
                                </Button>
                            </a>
                        )}

                        {/* Pesan Jadwal Baru */}
                        <Link
                            href={`/${business.slug}/booking`}
                            className="block"
                        >
                            <Button
                                variant="ghost"
                                className="w-full justify-center gap-2 text-slate-600"
                            >
                                <RotateCcw className="h-4 w-4 text-slate-400" />
                                Pesan Jadwal Baru
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}
