import { Link } from '@inertiajs/react';
import {
    Activity,
    AlertCircle,
    ArrowUpRight,
    Building2,
    CalendarCheck,
    CheckCircle2,
    Clock,
    CreditCard,
    Layers,
    Server,
    ShieldAlert,
    TrendingUp,
    Users,
} from 'lucide-react';
import React from 'react';
import { Button } from '../../Components/ui/Button';
import { AdminLayout } from '../../Layouts/AdminLayout';

interface AdminDashboardProps {
    metrics: {
        total_tenants: number;
        active_tenants: number;
        trial_tenants: number;
        expired_tenants: number;
        suspended_tenants: number;
        total_businesses: number;
        total_users: number;
        bookings_today: number;
        bookings_this_month: number;
        subscription_revenue: number;
        failed_payments: number;
        system_health: string;
    };
    recentActivity: Array<{
        id: number;
        action: string;
        entity_type: string;
        entity_id: number | null;
        created_at: string;
        actor?: {
            id: number;
            name: string;
            email: string;
        } | null;
    }>;
    recentTenants: Array<{
        id: number;
        name: string;
        status: string;
        created_at: string;
        owner?: {
            id: number;
            name: string;
            email: string;
        } | null;
        business?: {
            id: number;
            name: string;
            slug: string;
        } | null;
        current_subscription?: {
            id: number;
            status: string;
            plan?: {
                id: number;
                name: string;
            } | null;
        } | null;
    }>;
}

export default function AdminDashboard({
    metrics,
    recentActivity,
    recentTenants,
}: AdminDashboardProps) {
    return (
        <AdminLayout title="Super Admin Dashboard">
            <div className="space-y-6">
                {/* Header Banner */}
                <div className="rounded-[14px] border border-slate-200 bg-white p-6 shadow-none">
                    <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-bold tracking-tight text-slate-900">
                                    Pusat Kendali Platform (Super Admin)
                                </h1>
                                <span className="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700">
                                    PRD 73
                                </span>
                            </div>
                            <p className="mt-1 text-xs text-slate-500">
                                Pemantauan metrik seluruh tenant, lisensi langganan, arus transaksi, dan integritas multi-tenant.
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="inline-flex items-center gap-1.5 rounded-[8px] border border-slate-200 bg-[#f8fafc] px-3 py-1.5 text-xs text-slate-700">
                                <Server className="h-3.5 w-3.5 text-emerald-600" />
                                <span>Sistem: <strong>{metrics.system_health}</strong></span>
                            </div>
                            <Link href="/admin/tenants">
                                <Button variant="primary" size="sm" className="gap-1.5 text-xs">
                                    <Building2 className="h-3.5 w-3.5" />
                                    <span>Kelola Tenant</span>
                                </Button>
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Tenant & License KPIs */}
                <div>
                    <h3 className="mb-2 text-xs font-semibold tracking-wider text-slate-500 uppercase">
                        Metrik Langganan & Tenant
                    </h3>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                        <div className="rounded-[12px] border border-slate-200 bg-white p-4">
                            <div className="flex items-center justify-between text-xs text-slate-500">
                                <span>Total Tenant</span>
                                <Building2 className="h-4 w-4 text-slate-400" />
                            </div>
                            <div className="mt-2 text-2xl font-bold text-slate-900">
                                {metrics.total_tenants}
                            </div>
                            <p className="mt-1 text-[11px] text-slate-400">
                                {metrics.total_businesses} unit usaha
                            </p>
                        </div>

                        <div className="rounded-[12px] border border-slate-200 bg-white p-4">
                            <div className="flex items-center justify-between text-xs text-slate-500">
                                <span>Tenant Aktif</span>
                                <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                            </div>
                            <div className="mt-2 text-2xl font-bold text-emerald-600">
                                {metrics.active_tenants}
                            </div>
                            <p className="mt-1 text-[11px] text-slate-400">
                                Langganan berbayar aktif
                            </p>
                        </div>

                        <div className="rounded-[12px] border border-slate-200 bg-white p-4">
                            <div className="flex items-center justify-between text-xs text-slate-500">
                                <span>Masa Trial</span>
                                <Clock className="h-4 w-4 text-amber-500" />
                            </div>
                            <div className="mt-2 text-2xl font-bold text-amber-600">
                                {metrics.trial_tenants}
                            </div>
                            <p className="mt-1 text-[11px] text-slate-400">
                                Masa percobaan 14 hari
                            </p>
                        </div>

                        <div className="rounded-[12px] border border-slate-200 bg-white p-4">
                            <div className="flex items-center justify-between text-xs text-slate-500">
                                <span>Kedaluwarsa</span>
                                <AlertCircle className="h-4 w-4 text-rose-500" />
                            </div>
                            <div className="mt-2 text-2xl font-bold text-rose-600">
                                {metrics.expired_tenants}
                            </div>
                            <p className="mt-1 text-[11px] text-slate-400">
                                Mode read-only aktif
                            </p>
                        </div>

                        <div className="rounded-[12px] border border-slate-200 bg-white p-4">
                            <div className="flex items-center justify-between text-xs text-slate-500">
                                <span>Suspended</span>
                                <ShieldAlert className="h-4 w-4 text-rose-600" />
                            </div>
                            <div className="mt-2 text-2xl font-bold text-slate-900">
                                {metrics.suspended_tenants}
                            </div>
                            <p className="mt-1 text-[11px] text-slate-400">
                                Operasional ditutup
                            </p>
                        </div>
                    </div>
                </div>

                {/* Operational Activity & Revenue KPIs */}
                <div>
                    <h3 className="mb-2 text-xs font-semibold tracking-wider text-slate-500 uppercase">
                        Aktivitas Booking & Transaksi Platform
                    </h3>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="rounded-[12px] border border-slate-200 bg-white p-4">
                            <div className="flex items-center justify-between text-xs text-slate-500">
                                <span>Booking Hari Ini</span>
                                <CalendarCheck className="h-4 w-4 text-blue-600" />
                            </div>
                            <div className="mt-2 text-2xl font-bold text-slate-900">
                                {metrics.bookings_today}
                            </div>
                            <p className="mt-1 text-[11px] text-slate-400">
                                Lintas seluruh tenant
                            </p>
                        </div>

                        <div className="rounded-[12px] border border-slate-200 bg-white p-4">
                            <div className="flex items-center justify-between text-xs text-slate-500">
                                <span>Booking Bulan Ini</span>
                                <TrendingUp className="h-4 w-4 text-blue-600" />
                            </div>
                            <div className="mt-2 text-2xl font-bold text-slate-900">
                                {metrics.bookings_this_month}
                            </div>
                            <p className="mt-1 text-[11px] text-slate-400">
                                Akumulasi bulan berjalan
                            </p>
                        </div>

                        <div className="rounded-[12px] border border-slate-200 bg-white p-4">
                            <div className="flex items-center justify-between text-xs text-slate-500">
                                <span>Pendapatan Langganan</span>
                                <CreditCard className="h-4 w-4 text-emerald-600" />
                            </div>
                            <div className="mt-2 text-2xl font-bold text-slate-900">
                                Rp {metrics.subscription_revenue.toLocaleString('id-ID')}
                            </div>
                            <p className="mt-1 text-[11px] text-slate-400">
                                Total invoice lunas
                            </p>
                        </div>

                        <div className="rounded-[12px] border border-slate-200 bg-white p-4">
                            <div className="flex items-center justify-between text-xs text-slate-500">
                                <span>Gagal Bayar</span>
                                <AlertCircle className="h-4 w-4 text-rose-500" />
                            </div>
                            <div className="mt-2 text-2xl font-bold text-rose-600">
                                {metrics.failed_payments}
                            </div>
                            <p className="mt-1 text-[11px] text-slate-400">
                                Pembayaran gagal/expired
                            </p>
                        </div>
                    </div>
                </div>

                {/* Quick Navigation Cards */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <Link
                        href="/admin/tenants"
                        className="group flex items-center justify-between rounded-[12px] border border-slate-200 bg-white p-4 transition-colors hover:border-blue-600 hover:bg-blue-50/20"
                    >
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white">
                                <Building2 className="h-5 w-5" />
                            </div>
                            <div>
                                <span className="block text-sm font-semibold text-slate-900">
                                    Kelola Data Tenant
                                </span>
                                <span className="text-xs text-slate-500">
                                    Tabel tenant, suspend/activate, ubah plan
                                </span>
                            </div>
                        </div>
                        <ArrowUpRight className="h-4 w-4 text-slate-400 group-hover:text-blue-600" />
                    </Link>

                    <Link
                        href="/admin/plans"
                        className="group flex items-center justify-between rounded-[12px] border border-slate-200 bg-white p-4 transition-colors hover:border-blue-600 hover:bg-blue-50/20"
                    >
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white">
                                <CreditCard className="h-5 w-5" />
                            </div>
                            <div>
                                <span className="block text-sm font-semibold text-slate-900">
                                    Paket Langganan
                                </span>
                                <span className="text-xs text-slate-500">
                                    Konfigurasi kuota, harga & fitur (PRD 75)
                                </span>
                            </div>
                        </div>
                        <ArrowUpRight className="h-4 w-4 text-slate-400 group-hover:text-blue-600" />
                    </Link>

                    <Link
                        href="/admin/templates"
                        className="group flex items-center justify-between rounded-[12px] border border-slate-200 bg-white p-4 transition-colors hover:border-blue-600 hover:bg-blue-50/20"
                    >
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white">
                                <Layers className="h-5 w-5" />
                            </div>
                            <div>
                                <span className="block text-sm font-semibold text-slate-900">
                                    Katalog Template
                                </span>
                                <span className="text-xs text-slate-500">
                                    Template workflow & form lintas industri
                                </span>
                            </div>
                        </div>
                        <ArrowUpRight className="h-4 w-4 text-slate-400 group-hover:text-blue-600" />
                    </Link>
                </div>

                {/* Tables Grid: Recent Tenants & Activity */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Recent Tenants */}
                    <div className="rounded-[12px] border border-slate-200 bg-white p-5 shadow-none">
                        <div className="mb-3 flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 className="text-sm font-semibold text-slate-900">
                                Tenant Terbaru Terdaftar
                            </h3>
                            <Link
                                href="/admin/tenants"
                                className="text-xs font-medium text-blue-600 hover:underline"
                            >
                                Lihat Semua ({metrics.total_tenants})
                            </Link>
                        </div>

                        <div className="divide-y divide-slate-100 text-xs">
                            {recentTenants.length === 0 ? (
                                <p className="py-4 text-center text-slate-400">
                                    Belum ada data tenant.
                                </p>
                            ) : (
                                recentTenants.map((t) => (
                                    <div
                                        key={t.id}
                                        className="flex items-center justify-between py-2.5"
                                    >
                                        <div>
                                            <Link
                                                href={`/admin/tenants/${t.id}`}
                                                className="font-medium text-slate-900 hover:text-blue-600"
                                            >
                                                {t.name}
                                            </Link>
                                            <div className="text-[11px] text-slate-400">
                                                {t.owner?.name} • {t.owner?.email}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span
                                                className={`rounded-full px-2 py-0.5 text-[10px] font-medium ${
                                                    t.status === 'ACTIVE'
                                                        ? 'bg-emerald-50 text-emerald-700'
                                                        : t.status === 'SUSPENDED'
                                                          ? 'bg-rose-50 text-rose-700'
                                                          : 'bg-slate-100 text-slate-600'
                                                }`}
                                            >
                                                {t.status}
                                            </span>
                                            <Link
                                                href={`/admin/tenants/${t.id}`}
                                                className="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                            >
                                                <ArrowUpRight className="h-3.5 w-3.5" />
                                            </Link>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    {/* Platform Activity Logs */}
                    <div className="rounded-[12px] border border-slate-200 bg-white p-5 shadow-none">
                        <div className="mb-3 flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 className="text-sm font-semibold text-slate-900">
                                Log Aktivitas Platform
                            </h3>
                            <span className="text-[11px] text-slate-400">
                                Multi-Tenant Audit Trail
                            </span>
                        </div>

                        <div className="divide-y divide-slate-100 text-xs">
                            {recentActivity.length === 0 ? (
                                <p className="py-4 text-center text-slate-400">
                                    Belum ada log aktivitas.
                                </p>
                            ) : (
                                recentActivity.map((log) => (
                                    <div
                                        key={log.id}
                                        className="flex items-center justify-between py-2.5"
                                    >
                                        <div className="min-w-0 pr-3">
                                            <span className="font-mono font-medium text-slate-900">
                                                {log.action}
                                            </span>
                                            <div className="truncate text-[11px] text-slate-400">
                                                Oleh: {log.actor?.name || 'Sistem'} ({log.actor?.email || 'System'})
                                            </div>
                                        </div>
                                        <span className="shrink-0 text-[10px] text-slate-400">
                                            {new Date(log.created_at).toLocaleTimeString('id-ID', {
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })}
                                        </span>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
