import { Head, Link } from '@inertiajs/react';
import { Calendar, CheckCircle2, Clock, User } from 'lucide-react';
import React from 'react';
import { Button } from '../../Components/ui/Button';
import { PublicLayout } from '../../Layouts/PublicLayout';

interface BookingSuccessProps {
    business: {
        name: string;
        slug: string;
        timezone?: string;
        whatsapp?: string;
    };
    booking: {
        code: string;
        start_at: string;
        end_at: string;
        status: string;
        manage_token?: string;
        service?: {
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
    const formattedDate = new Date(booking.start_at).toLocaleDateString(
        'id-ID',
        {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }
    );

    const formattedTime = new Date(booking.start_at).toLocaleTimeString(
        'id-ID',
        {
            hour: '2-digit',
            minute: '2-digit',
        }
    );

    return (
        <PublicLayout
            title="Reservasi Berhasil"
            businessName={business.name}
            businessPhone={business.whatsapp}
        >
            <Head
                title={`Konfirmasi Booking ${booking.code} - ${business.name}`}
            />

            <div className="mx-auto max-w-lg">
                <div className="rounded-[14px] border border-slate-200 bg-white p-6 shadow-xs sm:p-8">
                    {/* Success Header */}
                    <div className="text-center">
                        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                            <CheckCircle2 className="h-8 w-8" />
                        </div>
                        <h1 className="mt-4 text-xl font-bold tracking-tight text-slate-900">
                            Reservasi Berhasil Dibuat!
                        </h1>
                        <p className="mt-1 text-xs text-slate-500">
                            Jadwal Anda telah kami catat dan terkonfirmasi
                            secara instan.
                        </p>
                    </div>

                    {/* Booking Code Card */}
                    <div className="mt-6 rounded-xl border border-blue-100 bg-blue-50/60 p-4 text-center">
                        <span className="text-[11px] font-medium tracking-wide text-blue-700 uppercase">
                            Kode Reservasi
                        </span>
                        <div className="mt-0.5 font-mono text-2xl font-black tracking-wider text-blue-900">
                            {booking.code}
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
                                Waktu
                            </span>
                            <span className="font-medium text-slate-900">
                                {formattedDate}, {formattedTime} (
                                {business.timezone || 'WIB'})
                            </span>
                        </div>

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
                    </div>

                    {/* Actions */}
                    <div className="mt-6 space-y-2.5">
                        {booking.manage_token && (
                            <Link
                                href={`/${business.slug}/booking/manage/${booking.manage_token}`}
                                className="block"
                            >
                                <Button variant="secondary" className="w-full">
                                    Kelola Reservasi / Reschedule
                                </Button>
                            </Link>
                        )}

                        <Link href={`/${business.slug}`} className="block">
                            <Button
                                variant="ghost"
                                className="w-full text-slate-600"
                            >
                                Kembali ke Halaman Bisnis
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}
