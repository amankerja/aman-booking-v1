import { Head, Link } from '@inertiajs/react';
import {
    Calendar,
    CalendarCheck,
    CheckCircle2,
    Clock,
    Download,
    MapPin,
    MessageSquare,
    RotateCcw,
    Settings,
    User,
    UserCheck,
} from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import React from 'react';
import { Button } from '../../Components/ui/Button';
import { PublicLayout } from '../../Layouts/PublicLayout';

interface BookingSuccessProps {
    business: {
        name: string;
        slug: string;
        timezone?: string;
        whatsapp?: string;
        phone?: string;
        address?: string;
    };
    booking: {
        code: string;
        start_at: string;
        end_at: string;
        status: string;
        status_category: string;
        total_idr?: number;
        manage_token?: string | null;
        hold_expires_at?: string | null;
        is_hold_active?: boolean;
        is_hold_expired?: boolean;
        hold_remaining_seconds?: number;
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
}

export default function BookingSuccess({
    business,
    booking,
}: BookingSuccessProps) {
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
    const waText = `Halo ${business.name}, saya ingin menanyakan perihal reservasi saya dengan kode: ${booking.code} (${booking.service?.name}) pada ${formattedDate} jam ${formattedTime}.`;
    const waUrl = waNumber
        ? `https://wa.me/${waNumber}?text=${encodeURIComponent(waText)}`
        : null;

    const calendarDownloadUrl = `/${business.slug}/booking/success/${booking.code}/calendar.ics`;

    const repeatBookingUrl = booking.service?.id
        ? `/${business.slug}/booking?service_id=${booking.service.id}&name=${encodeURIComponent(booking.customer?.name || '')}&phone=${encodeURIComponent(booking.customer?.phone || '')}`
        : `/${business.slug}/booking`;

    return (
        <PublicLayout
            title="Reservasi Berhasil"
            businessName={business.name}
            businessPhone={business.whatsapp || business.phone}
        >
            <Head
                title={`Konfirmasi Booking ${booking.code} - ${business.name}`}
            />

            <div className="mx-auto max-w-lg">
                <div className="rounded-[14px] border border-slate-200 bg-white p-6 shadow-xs sm:p-8">
                    {/* Success or Pending Header */}
                    {booking.status_category === 'PENDING' ? (
                        <div className="text-center">
                            <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                                <Clock className="h-8 w-8" />
                            </div>
                            <h1 className="mt-4 text-xl font-bold tracking-tight text-slate-900">
                                Reservasi Sementara Dibuat!
                            </h1>
                            <p className="mt-1 text-xs text-amber-700 font-medium">
                                Jadwal Anda ditahan sementara (Hold Aktif). Selesaikan pembayaran agar reservasi Anda tidak kedaluwarsa.
                            </p>
                        </div>
                    ) : (
                        <div className="text-center">
                            <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                                <CheckCircle2 className="h-8 w-8" />
                            </div>
                            <h1 className="mt-4 text-xl font-bold tracking-tight text-slate-900">
                                Reservasi Berhasil Dibuat!
                            </h1>
                            <p className="mt-1 text-xs text-slate-500">
                                Jadwal Anda telah kami catat dan terkonfirmasi secara instan.
                            </p>
                        </div>
                    )}

                    {/* Booking Code & QR Section (PRD 32, 36) */}
                    <div className="mt-6 rounded-xl border border-blue-100 bg-blue-50/50 p-5 text-center">
                        <span className="text-[11px] font-semibold tracking-wider text-blue-700 uppercase">
                            Kode Reservasi Anda
                        </span>
                        <div className="mt-1 font-mono text-2xl font-black tracking-wider text-blue-900">
                            {booking.code}
                        </div>

                        {/* QR Code Container */}
                        <div className="mt-4 flex flex-col items-center justify-center">
                            <div className="rounded-xl border border-slate-200 bg-white p-3 shadow-xs">
                                <QRCodeSVG
                                    value={booking.code}
                                    size={144}
                                    level="M"
                                    includeMargin={false}
                                />
                            </div>
                            <p className="mt-2.5 max-w-xs text-[11px] text-slate-500">
                                Tunjukkan QR code ini kepada resepsionis saat
                                tiba di lokasi untuk check-in instan.
                            </p>
                        </div>
                    </div>

                    {/* Details List */}
                    <div className="mt-6 space-y-3.5 border-t border-b border-slate-100 py-5 text-xs">
                        <div className="flex items-center justify-between">
                            <span className="flex items-center gap-2 text-slate-500">
                                <Calendar className="h-4 w-4 text-slate-400" />
                                Layanan
                            </span>
                            <span className="font-semibold text-slate-900">
                                {booking.service?.name || 'Layanan Reservasi'}
                            </span>
                        </div>

                        <div className="flex items-center justify-between">
                            <span className="flex items-center gap-2 text-slate-500">
                                <Clock className="h-4 w-4 text-slate-400" />
                                Waktu & Durasi
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
                                <div className="flex items-center justify-between border-t border-slate-100 pt-3">
                                    <span className="font-medium text-slate-700">
                                        Total Biaya
                                    </span>
                                    <span className="font-bold text-slate-900">
                                        {formatCurrency(booking.total_idr)}
                                    </span>
                                </div>
                            )}
                    </div>

                    {/* Primary Actions (PRD 32, 33, 181) */}
                    <div className="mt-6 space-y-2.5">
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

                        {/* Hubungi via WhatsApp */}
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
                                    Hubungi Admin via WhatsApp
                                </Button>
                            </a>
                        )}

                        {/* Kelola Reservasi / Reschedule (if token provided) */}
                        {booking.manage_token && (
                            <Link
                                href={`/${business.slug}/booking/manage/${booking.manage_token}`}
                                className="block"
                            >
                                <Button
                                    variant="primary"
                                    className="w-full justify-center gap-2"
                                >
                                    <Settings className="h-4 w-4" />
                                    Kelola Reservasi / Reschedule
                                </Button>
                            </Link>
                        )}

                        {/* Pesan Jadwal Lagi (PRD 181) */}
                        <Link href={repeatBookingUrl} className="block">
                            <Button
                                variant="secondary"
                                className="w-full justify-center gap-2 border-slate-200 text-slate-700"
                            >
                                <RotateCcw className="h-4 w-4 text-slate-500" />
                                Pesan Jadwal Lagi
                            </Button>
                        </Link>

                        {/* Kembali ke Halaman Bisnis */}
                        <Link href={`/${business.slug}`} className="block">
                            <Button
                                variant="ghost"
                                className="w-full text-slate-500 hover:text-slate-800"
                            >
                                Kembali ke Profil Bisnis
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}
