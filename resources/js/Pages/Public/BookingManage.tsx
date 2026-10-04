import { Head, Link } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import React from 'react';
import { Badge } from '../../Components/ui/Badge';
import { Button } from '../../Components/ui/Button';
import { PublicLayout } from '../../Layouts/PublicLayout';

interface BookingManageProps {
    business: {
        name: string;
        slug: string;
        timezone?: string;
        whatsapp?: string;
    };
    booking: {
        code: string;
        status: string;
        start_at: string;
        end_at: string;
        manage_token: string;
        service?: {
            name: string;
            duration_minutes: number;
        };
        customer?: {
            name: string;
            phone: string;
        };
    };
}

export default function BookingManage({
    business,
    booking,
}: BookingManageProps) {
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
            title="Kelola Reservasi"
            businessName={business.name}
            businessPhone={business.whatsapp}
        >
            <Head title={`Kelola Booking ${booking.code} - ${business.name}`} />

            <div className="mx-auto max-w-lg">
                <div className="rounded-[14px] border border-slate-200 bg-white p-6 shadow-xs sm:p-8">
                    <div className="flex items-center justify-between">
                        <Badge variant="active" size="sm">
                            Status: {booking.status}
                        </Badge>
                        <span className="font-mono text-xs font-bold text-slate-500">
                            {booking.code}
                        </span>
                    </div>

                    <h1 className="mt-4 text-xl font-bold tracking-tight text-slate-900">
                        Detail & Kelola Reservasi
                    </h1>
                    <p className="mt-1 text-xs text-slate-500">
                        Anda dapat melihat rincian jadwal atau mengajukan
                        pembatalan / ubah jadwal.
                    </p>

                    <div className="mt-6 space-y-3 rounded-xl border border-slate-100 bg-slate-50 p-4 text-xs">
                        <div className="flex justify-between">
                            <span className="text-slate-500">Layanan</span>
                            <span className="font-semibold text-slate-900">
                                {booking.service?.name}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-slate-500">Waktu</span>
                            <span className="font-medium text-slate-900">
                                {formattedDate}, {formattedTime} (
                                {business.timezone || 'WIB'})
                            </span>
                        </div>
                    </div>

                    <div className="mt-6 flex flex-col gap-2.5">
                        <Link
                            href={`/${business.slug}/booking`}
                            className="block"
                        >
                            <Button variant="primary" className="w-full">
                                <RotateCcw className="mr-2 h-4 w-4" />
                                Pesan Jadwal Baru
                            </Button>
                        </Link>
                        <Link href={`/${business.slug}`} className="block">
                            <Button variant="secondary" className="w-full">
                                Halaman Utama Bisnis
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}
