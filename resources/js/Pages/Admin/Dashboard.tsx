import {
    Activity,
    Building,
    CheckCircle2,
    Clock,
    CreditCard,
    Server,
    Users,
} from 'lucide-react';
import React from 'react';
import { AdminLayout } from '../../Layouts/AdminLayout';

interface AdminDashboardProps {
    metrics: {
        total_tenants: number;
        total_businesses: number;
        total_users: number;
        active_subscriptions: number;
        trial_subscriptions: number;
    };
}

export default function AdminDashboard({ metrics }: AdminDashboardProps) {
    return (
        <AdminLayout title="Super Admin Dashboard">
            <div className="space-y-6">
                <div className="rounded-xl border border-slate-200 bg-white p-6">
                    <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div>
                            <h1 className="text-xl font-bold tracking-tight text-slate-900">
                                Ringkasan Platform Global
                            </h1>
                            <p className="mt-1 text-sm text-slate-500">
                                Pemantauan metrik seluruh tenant, lisensi
                                langganan, dan integritas multi-tenant.
                            </p>
                        </div>
                        <div className="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-600">
                            <Server className="h-4 w-4 text-emerald-600" />
                            <span>
                                Status Server: Operasional (InnoDB Isolation
                                Active)
                            </span>
                        </div>
                    </div>
                </div>

                {/* Metrics Grid */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div className="rounded-xl border border-slate-200 bg-white p-5">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500">
                                Total Tenant
                            </span>
                            <span className="rounded-lg bg-blue-50 p-2 text-blue-600">
                                <Building className="h-4 w-4" />
                            </span>
                        </div>
                        <div className="mt-3">
                            <span className="text-2xl font-bold text-slate-900">
                                {metrics.total_tenants}
                            </span>
                            <p className="mt-1 text-[11px] text-slate-400">
                                Entitas tenant terdaftar
                            </p>
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-white p-5">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500">
                                Total Bisnis
                            </span>
                            <span className="rounded-lg bg-indigo-50 p-2 text-indigo-600">
                                <Activity className="h-4 w-4" />
                            </span>
                        </div>
                        <div className="mt-3">
                            <span className="text-2xl font-bold text-slate-900">
                                {metrics.total_businesses}
                            </span>
                            <p className="mt-1 text-[11px] text-slate-400">
                                Unit usaha aktif
                            </p>
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-white p-5">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500">
                                Total Akun User
                            </span>
                            <span className="rounded-lg bg-purple-50 p-2 text-purple-600">
                                <Users className="h-4 w-4" />
                            </span>
                        </div>
                        <div className="mt-3">
                            <span className="text-2xl font-bold text-slate-900">
                                {metrics.total_users}
                            </span>
                            <p className="mt-1 text-[11px] text-slate-400">
                                Pengguna terverifikasi
                            </p>
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-white p-5">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500">
                                Langganan Trial
                            </span>
                            <span className="rounded-lg bg-amber-50 p-2 text-amber-600">
                                <Clock className="h-4 w-4" />
                            </span>
                        </div>
                        <div className="mt-3">
                            <span className="text-2xl font-bold text-slate-900">
                                {metrics.trial_subscriptions}
                            </span>
                            <p className="mt-1 text-[11px] text-slate-400">
                                Masa percobaan 14 hari
                            </p>
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-white p-5">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500">
                                Langganan Berbayar
                            </span>
                            <span className="rounded-lg bg-emerald-50 p-2 text-emerald-600">
                                <CreditCard className="h-4 w-4" />
                            </span>
                        </div>
                        <div className="mt-3">
                            <span className="text-2xl font-bold text-slate-900">
                                {metrics.active_subscriptions}
                            </span>
                            <p className="mt-1 text-[11px] text-slate-400">
                                Paket BASIC/PRO/BIZ
                            </p>
                        </div>
                    </div>
                </div>

                {/* Audit & Compliance Log Placeholder */}
                <div className="rounded-xl border border-slate-200 bg-white p-6">
                    <div className="mb-4 flex items-center justify-between border-b border-slate-100 pb-3">
                        <div>
                            <h2 className="text-base font-bold text-slate-900">
                                Status Keamanan & Isolasi Tenant
                            </h2>
                            <p className="text-xs text-slate-500">
                                Verifikasi global scope dan kebijakan otorisasi
                                Spatie
                            </p>
                        </div>
                        <span className="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                            Semua Scope Aktif
                        </span>
                    </div>

                    <div className="space-y-3">
                        <div className="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 p-3 text-xs">
                            <div className="flex items-center gap-2">
                                <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                                <span className="font-semibold text-slate-900">
                                    Global TenantScope
                                </span>
                            </div>
                            <span className="text-slate-500">
                                Menegakkan penyaringan otomatis WHERE tenant_id
                                = ? di seluruh model tenant
                            </span>
                        </div>

                        <div className="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 p-3 text-xs">
                            <div className="flex items-center gap-2">
                                <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                                <span className="font-semibold text-slate-900">
                                    Spatie Team Permissions
                                </span>
                            </div>
                            <span className="text-slate-500">
                                Role dan hak akses member diisolasi per tenant
                                ID (team_foreign_key = tenant_id)
                            </span>
                        </div>

                        <div className="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 p-3 text-xs">
                            <div className="flex items-center gap-2">
                                <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                                <span className="font-semibold text-slate-900">
                                    Idempotency & Audit Logs
                                </span>
                            </div>
                            <span className="text-slate-500">
                                Tabel audit_logs dan idempotency_keys siap
                                merekam mutasi transaksi
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
