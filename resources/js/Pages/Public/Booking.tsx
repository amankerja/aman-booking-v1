import React from 'react';
import { Badge } from '../../Components/ui/Badge';
import { Button } from '../../Components/ui/Button';
import { PublicLayout } from '../../Layouts/PublicLayout';

interface BookingProps {
    business?: {
        name: string;
        slug: string;
        timezone?: string;
    };
}

export default function Booking({ business }: BookingProps) {
    const businessName = business?.name || 'AMAN BOOKING';

    return (
        <PublicLayout title="Reservasi Layanan" businessName={businessName}>
            <div className="mx-auto max-w-lg rounded-[14px] border border-slate-200 bg-white p-6 sm:p-8">
                <div className="mb-4">
                    <Badge variant="active" size="sm">
                        Portal Reservasi Publik
                    </Badge>
                </div>

                <h1 className="text-xl font-bold tracking-tight text-slate-900">
                    Formulir Pemesanan Layanan
                </h1>
                <p className="mt-1.5 text-xs leading-relaxed text-slate-500">
                    Pilih layanan dan waktu reservasi Anda di {businessName}{' '}
                    dengan mudah, terkonfirmasi otomatis tanpa ribet.
                </p>

                <div className="mt-6 rounded-lg border border-slate-100 bg-slate-50 p-4 text-xs text-slate-600">
                    <p className="mb-1 font-semibold text-slate-900">
                        Reservasi Cepat & Aman
                    </p>
                    <p className="text-slate-500">
                        Katalog layanan interaktif dan pemilihan slot jadwal
                        akan aktif di Fase 1 (Booking Engine).
                    </p>
                </div>

                <div className="mt-6">
                    <Button variant="primary" className="w-full">
                        Mulai Reservasi
                    </Button>
                </div>
            </div>
        </PublicLayout>
    );
}
