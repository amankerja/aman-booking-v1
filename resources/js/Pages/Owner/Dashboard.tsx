import {
    Activity,
    Building2,
    CalendarCheck,
    CheckCircle2,
    Clock,
    CreditCard,
    DollarSign,
    ExternalLink,
    UserCheck,
    Users,
} from 'lucide-react';
import React from 'react';
import { OwnerLayout } from '../../Layouts/OwnerLayout';

interface OwnerDashboardProps {
    tenant: {
        id: number;
        uuid: string;
        name: string;
        status: string;
    } | null;
    business: {
        id: number;
        name: string;
        slug: string;
        timezone: string;
    } | null;
    subscription: {
        status: string;
        plan_name: string;
        trial_ends_at?: string;
        current_period_end?: string;
    } | null;
}

export default function OwnerDashboard({
    tenant,
    business,
    subscription: _subscription,
}: OwnerDashboardProps) {
    return (
        <OwnerLayout
            title="Owner Dashboard"
            breadcrumbs={[
                { label: 'Workspace' },
                { label: business?.name || 'Dashboard' },
            ]}
        >
            <div className="space-y-6">
                {/* Status Notice / Welcome Banner */}
                <div className="rounded-xl border border-slate-200 bg-white p-6">
                    <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-bold tracking-tight text-slate-900">
                                    Selamat Datang di Workspace {business?.name}
                                </h1>
                                <span className="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                                    Sistem Siap
                                </span>
                            </div>
                            <p className="mt-1 text-sm text-slate-500">
                                Akun usaha Anda telah berhasil diinisialisasi
                                dengan isolasi tenant penuh.
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <div className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-700">
                                <Clock className="h-4 w-4 text-slate-500" />
                                <span>
                                    Zona Waktu:{' '}
                                    {business?.timezone || 'Asia/Jakarta'}
                                </span>
                            </div>

                            <a
                                href={`/b/${business?.slug}`}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3.5 py-2 text-xs font-semibold text-white transition-colors hover:bg-blue-700"
                            >
                                <ExternalLink className="h-3.5 w-3.5" />
                                <span>Lihat Laman Publik</span>
                            </a>
                        </div>
                    </div>
                </div>

                {/* Empty State Metric Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="rounded-xl border border-slate-200 bg-white p-5">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500">
                                Reservasi Hari Ini
                            </span>
                            <span className="rounded-lg bg-blue-50 p-2 text-blue-600">
                                <CalendarCheck className="h-4 w-4" />
                            </span>
                        </div>
                        <div className="mt-3">
                            <span className="text-2xl font-bold text-slate-900">
                                0
                            </span>
                            <p className="mt-1 text-[11px] text-slate-400">
                                Belum ada booking terjadwal
                            </p>
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-white p-5">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500">
                                Estimasi Omset
                            </span>
                            <span className="rounded-lg bg-emerald-50 p-2 text-emerald-600">
                                <DollarSign className="h-4 w-4" />
                            </span>
                        </div>
                        <div className="mt-3">
                            <span className="text-2xl font-bold text-slate-900">
                                Rp 0
                            </span>
                            <p className="mt-1 text-[11px] text-slate-400">
                                Bulan berjalan
                            </p>
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-white p-5">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500">
                                Total Pelanggan
                            </span>
                            <span className="rounded-lg bg-amber-50 p-2 text-amber-600">
                                <Users className="h-4 w-4" />
                            </span>
                        </div>
                        <div className="mt-3">
                            <span className="text-2xl font-bold text-slate-900">
                                0
                            </span>
                            <p className="mt-1 text-[11px] text-slate-400">
                                Kontak tersimpan
                            </p>
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-white p-5">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500">
                                Tingkat Utilisasi
                            </span>
                            <span className="rounded-lg bg-indigo-50 p-2 text-indigo-600">
                                <Activity className="h-4 w-4" />
                            </span>
                        </div>
                        <div className="mt-3">
                            <span className="text-2xl font-bold text-slate-900">
                                0%
                            </span>
                            <p className="mt-1 text-[11px] text-slate-400">
                                Kapasitas slot terpakai
                            </p>
                        </div>
                    </div>
                </div>

                {/* Onboarding Checklist for Empty State */}
                <div className="rounded-xl border border-slate-200 bg-white p-6">
                    <div className="mb-4 flex items-center justify-between border-b border-slate-100 pb-3">
                        <div>
                            <h2 className="text-base font-bold text-slate-900">
                                Panduan Memulai Cepat
                            </h2>
                            <p className="text-xs text-slate-500">
                                Langkah-langkah penting untuk mempersiapkan
                                bisnis Anda menerima reservasi online
                            </p>
                        </div>
                        <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                            Fase Fondasi
                        </span>
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div className="flex items-start gap-3 rounded-lg border border-slate-200 p-4">
                            <div className="mt-0.5 rounded-full bg-blue-50 p-1.5 text-blue-600">
                                <CheckCircle2 className="h-4 w-4" />
                            </div>
                            <div>
                                <h3 className="text-sm font-semibold text-slate-900">
                                    1. Registrasi Akun & Tenant
                                </h3>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    Identitas tenant Anda{' '}
                                    <span className="font-mono text-slate-700">
                                        {tenant?.name}
                                    </span>{' '}
                                    telah aktif dengan masa percobaan 14 hari.
                                </p>
                                <span className="mt-2 inline-block rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-medium text-emerald-700">
                                    Selesai
                                </span>
                            </div>
                        </div>

                        <div className="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50/50 p-4">
                            <div className="mt-0.5 rounded-full bg-slate-200 p-1.5 text-slate-600">
                                <Building2 className="h-4 w-4" />
                            </div>
                            <div>
                                <h3 className="text-sm font-semibold text-slate-900">
                                    2. Atur Profil & Layanan Usaha
                                </h3>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    Konfigurasi jam operasional, kategori
                                    layanan, durasi waktu, dan harga.
                                </p>
                                <span className="mt-2 inline-block rounded bg-slate-200 px-2 py-0.5 text-[10px] font-medium text-slate-700">
                                    Fase 1 (Katalog & Lokasi)
                                </span>
                            </div>
                        </div>

                        <div className="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50/50 p-4">
                            <div className="mt-0.5 rounded-full bg-slate-200 p-1.5 text-slate-600">
                                <UserCheck className="h-4 w-4" />
                            </div>
                            <div>
                                <h3 className="text-sm font-semibold text-slate-900">
                                    3. Undang Tim & Tentukan Role
                                </h3>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    Delegasikan hak akses untuk Manager, Front
                                    Desk, Staff, atau Viewer.
                                </p>
                                <span className="mt-2 inline-block rounded bg-slate-200 px-2 py-0.5 text-[10px] font-medium text-slate-700">
                                    Role Terisolasi Spatie
                                </span>
                            </div>
                        </div>

                        <div className="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50/50 p-4">
                            <div className="mt-0.5 rounded-full bg-slate-200 p-1.5 text-slate-600">
                                <CreditCard className="h-4 w-4" />
                            </div>
                            <div>
                                <h3 className="text-sm font-semibold text-slate-900">
                                    4. Konfigurasi Pembayaran Online
                                </h3>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    Aktifkan gateway pembayaran (Midtrans /
                                    Xendit) untuk DP atau lunas instan.
                                </p>
                                <span className="mt-2 inline-block rounded bg-slate-200 px-2 py-0.5 text-[10px] font-medium text-slate-700">
                                    Fase 3 (Pembayaran)
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </OwnerLayout>
    );
}
