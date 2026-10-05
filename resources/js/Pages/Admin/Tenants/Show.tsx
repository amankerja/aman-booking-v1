import { Link, router } from '@inertiajs/react';
import {
    Activity,
    AlertCircle,
    ArrowLeft,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    CreditCard,
    FileText,
    KeyRound,
    Layers,
    Save,
    ShieldAlert,
    UserCheck,
    Users,
} from 'lucide-react';
import React, { useState } from 'react';
import { Button, Modal, Textarea, useToast } from '../../../Components/ui';
import { AdminLayout } from '../../../Layouts/AdminLayout';

interface TenantDetailProps {
    tenant: {
        id: number;
        uuid: string;
        name: string;
        status: string;
        owner_user_id: number;
        support_notes: string | null;
        created_at: string;
        updated_at: string;
        owner?: {
            id: number;
            name: string;
            email: string;
        } | null;
        businesses?: Array<{
            id: number;
            name: string;
            slug: string;
            created_at: string;
        }>;
        members?: Array<{
            id: number;
            role: string;
            user?: {
                id: number;
                name: string;
                email: string;
            } | null;
        }>;
        current_subscription?: {
            id: number;
            status: string;
            trial_ends_at: string | null;
            current_period_start: string | null;
            current_period_end: string | null;
            plan?: {
                id: number;
                code: string;
                name: string;
                price_idr: number;
                billing_cycle: string;
                limits?: Record<string, any> | null;
                features?: Record<string, any> | null;
            } | null;
        } | null;
    };
    services: Array<{
        id: number;
        name: string;
        duration_minutes: number;
        price_idr: number;
        is_active: boolean;
    }>;
    recentBookings: Array<{
        id: number;
        code: string;
        status_category: string;
        start_at: string;
        total_idr: number;
        customer?: {
            name: string;
            phone_e164: string;
        } | null;
        service?: {
            name: string;
        } | null;
    }>;
    invoices: Array<{
        id: number;
        invoice_number: string;
        status: string;
        amount_idr: number;
        due_at: string;
        paid_at: string | null;
    }>;
    auditLogs: Array<{
        id: number;
        action: string;
        entity_type: string;
        created_at: string;
        actor?: {
            name: string;
            email: string;
        } | null;
        after?: Record<string, any> | null;
    }>;
    usage: {
        members_count: number;
        services_count: number;
        bookings_month_count: number;
        bookings_total_count: number;
    };
    availablePlans: Array<{
        id: number;
        code: string;
        name: string;
        price_idr: number;
    }>;
}

export default function AdminTenantShow({
    tenant,
    services,
    recentBookings,
    invoices,
    auditLogs,
    usage,
    availablePlans,
}: TenantDetailProps) {
    const toast = useToast();
    const [activeTab, setActiveTab] = useState<
        'overview' | 'subscription' | 'usage' | 'audit' | 'notes'
    >('overview');

    // Notes state
    const [supportNotes, setSupportNotes] = useState(
        tenant.support_notes || ''
    );
    const [isSavingNotes, setIsSavingNotes] = useState(false);

    // Support access modal
    const [isSupportAccessOpen, setIsSupportAccessOpen] = useState(false);
    const [supportReason, setSupportReason] = useState('');
    const [isSubmittingSupport, setIsSubmittingSupport] = useState(false);

    // Change plan modal
    const [isChangePlanOpen, setIsChangePlanOpen] = useState(false);
    const [selectedPlanId, setSelectedPlanId] = useState<number | string>(
        tenant.current_subscription?.plan?.id || availablePlans[0]?.id || ''
    );
    const [isSubmittingPlan, setIsSubmittingPlan] = useState(false);

    const handleSaveNotes = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSavingNotes(true);
        router.post(
            `/admin/tenants/${tenant.id}/notes`,
            { support_notes: supportNotes },
            {
                onSuccess: () =>
                    toast.success('Catatan dukungan berhasil disimpan.'),
                onError: () => toast.error('Gagal menyimpan catatan.'),
                onFinish: () => setIsSavingNotes(false),
            }
        );
    };

    const handleSupportAccessSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!supportReason.trim()) return;
        setIsSubmittingSupport(true);

        router.post(
            `/admin/tenants/${tenant.id}/support-access`,
            { reason: supportReason.trim() },
            {
                onSuccess: () => setIsSupportAccessOpen(false),
                onError: () => {
                    toast.error('Gagal memulai sesi dukungan.');
                    setIsSubmittingSupport(false);
                },
            }
        );
    };

    const handleChangePlanSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmittingPlan(true);

        router.post(
            `/admin/tenants/${tenant.id}/change-plan`,
            { plan_id: Number(selectedPlanId) },
            {
                onSuccess: () => {
                    setIsChangePlanOpen(false);
                    toast.success('Paket langganan berhasil diperbarui.');
                },
                onError: () => toast.error('Gagal mengubah paket.'),
                onFinish: () => setIsSubmittingPlan(false),
            }
        );
    };

    const handleToggleSuspend = () => {
        const isSuspended = tenant.status === 'SUSPENDED';
        const action = isSuspended ? 'activate' : 'suspend';
        const confirmMsg = isSuspended
            ? `Aktifkan kembali tenant '${tenant.name}'?`
            : `Tangguhkan (SUSPEND) tenant '${tenant.name}'? Halaman booking publik akan ditutup.`;

        if (!confirm(confirmMsg)) return;

        router.post(
            `/admin/tenants/${tenant.id}/${action}`,
            {},
            {
                onSuccess: () =>
                    toast.success(
                        isSuspended
                            ? 'Tenant berhasil diaktifkan kembali.'
                            : 'Tenant berhasil dinonaktifkan.'
                    ),
                onError: () => toast.error('Gagal memproses status tenant.'),
            }
        );
    };

    const sub = tenant.current_subscription;
    const plan = sub?.plan;

    return (
        <AdminLayout title={`Detail Tenant: ${tenant.name}`}>
            <div className="space-y-6">
                {/* Back button & Title Header */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div className="flex items-center gap-3">
                        <Link
                            href="/admin/tenants"
                            className="rounded-lg border border-slate-200 bg-white p-2 text-slate-500 hover:bg-slate-50 hover:text-slate-900"
                        >
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-bold tracking-tight text-slate-900">
                                    {tenant.name}
                                </h1>
                                <span
                                    className={`rounded-full px-2.5 py-0.5 text-[10px] font-semibold ${
                                        tenant.status === 'ACTIVE'
                                            ? 'border border-emerald-200 bg-emerald-50 text-emerald-700'
                                            : tenant.status === 'SUSPENDED'
                                              ? 'border border-rose-200 bg-rose-50 text-rose-700'
                                              : 'border border-slate-200 bg-slate-100 text-slate-600'
                                    }`}
                                >
                                    {tenant.status}
                                </span>
                            </div>
                            <p className="mt-0.5 font-mono text-[11px] text-slate-400">
                                UUID: {tenant.uuid}
                            </p>
                        </div>
                    </div>

                    {/* Quick Action Buttons */}
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                setSupportReason('');
                                setIsSupportAccessOpen(true);
                            }}
                            className="gap-1.5 text-xs text-blue-600 hover:text-blue-700"
                        >
                            <KeyRound className="h-3.5 w-3.5" />
                            <span>Support Access (PRD 74)</span>
                        </Button>

                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            onClick={() => setIsChangePlanOpen(true)}
                            className="gap-1.5 text-xs"
                        >
                            <CreditCard className="h-3.5 w-3.5" />
                            <span>Ubah Paket</span>
                        </Button>

                        <Button
                            type="button"
                            variant={
                                tenant.status === 'SUSPENDED'
                                    ? 'primary'
                                    : 'danger'
                            }
                            size="sm"
                            onClick={handleToggleSuspend}
                            className="gap-1.5 text-xs"
                        >
                            <ShieldAlert className="h-3.5 w-3.5" />
                            <span>
                                {tenant.status === 'SUSPENDED'
                                    ? 'Aktifkan Tenant'
                                    : 'Suspend Tenant'}
                            </span>
                        </Button>
                    </div>
                </div>

                {/* Navigation Tabs */}
                <div className="border-b border-slate-200">
                    <nav className="flex space-x-6 text-xs font-medium">
                        {[
                            { id: 'overview', label: 'Ringkasan & Bisnis' },
                            { id: 'subscription', label: 'Langganan & Billing' },
                            { id: 'usage', label: 'Penggunaan Kuota' },
                            { id: 'notes', label: 'Catatan Dukungan' },
                            { id: 'audit', label: 'Log Audit & Aktivitas' },
                        ].map((t) => (
                            <button
                                key={t.id}
                                type="button"
                                onClick={() => setActiveTab(t.id as any)}
                                className={`border-b-2 py-3 transition-colors ${
                                    activeTab === t.id
                                        ? 'border-blue-600 font-bold text-blue-600'
                                        : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'
                                }`}
                            >
                                {t.label}
                            </button>
                        ))}
                    </nav>
                </div>

                {/* Tab 1: Overview */}
                {activeTab === 'overview' && (
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <div className="space-y-6 lg:col-span-2">
                            {/* Business Profile */}
                            <div className="rounded-[12px] border border-slate-200 bg-white p-5 shadow-none">
                                <h3 className="mb-3 text-sm font-semibold text-slate-900">
                                    Profil Unit Usaha
                                </h3>
                                {tenant.businesses &&
                                tenant.businesses.length > 0 ? (
                                    <div className="divide-y divide-slate-100 text-xs">
                                        {tenant.businesses.map((biz) => (
                                            <div
                                                key={biz.id}
                                                className="flex items-center justify-between py-2"
                                            >
                                                <div>
                                                    <span className="font-semibold text-slate-900">
                                                        {biz.name}
                                                    </span>
                                                    <div className="text-[11px] text-slate-400">
                                                        Tautan Publik:{' '}
                                                        <a
                                                            href={`/${biz.slug}`}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="text-blue-600 hover:underline"
                                                        >
                                                            /{biz.slug}
                                                        </a>
                                                    </div>
                                                </div>
                                                <span className="text-[10px] text-slate-400">
                                                    Dibuat:{' '}
                                                    {new Date(
                                                        biz.created_at
                                                    ).toLocaleDateString(
                                                        'id-ID'
                                                    )}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-xs text-slate-400">
                                        Belum ada unit bisnis yang terdaftar.
                                    </p>
                                )}
                            </div>

                            {/* Recent Bookings preview */}
                            <div className="rounded-[12px] border border-slate-200 bg-white p-5 shadow-none">
                                <h3 className="mb-3 text-sm font-semibold text-slate-900">
                                    Transaksi Booking Terbaru
                                </h3>
                                <div className="divide-y divide-slate-100 text-xs">
                                    {recentBookings.length === 0 ? (
                                        <p className="py-4 text-center text-slate-400">
                                            Belum ada booking tercatat.
                                        </p>
                                    ) : (
                                        recentBookings.map((b) => (
                                            <div
                                                key={b.id}
                                                className="flex items-center justify-between py-2.5"
                                            >
                                                <div>
                                                    <span className="font-mono font-medium text-slate-900">
                                                        {b.code}
                                                    </span>{' '}
                                                    • {b.service?.name}
                                                    <div className="text-[11px] text-slate-400">
                                                        Customer:{' '}
                                                        {b.customer?.name} (
                                                        {b.customer?.phone_e164}
                                                        )
                                                    </div>
                                                </div>
                                                <div className="text-right">
                                                    <span className="font-medium text-slate-900">
                                                        Rp{' '}
                                                        {Number(
                                                            b.total_idr
                                                        ).toLocaleString(
                                                            'id-ID'
                                                        )}
                                                    </span>
                                                    <div className="text-[10px] text-slate-400">
                                                        {b.status_category}
                                                    </div>
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Owner & Account Details Card */}
                        <div className="space-y-6">
                            <div className="rounded-[12px] border border-slate-200 bg-white p-5 shadow-none">
                                <h3 className="mb-3 text-sm font-semibold text-slate-900">
                                    Informasi Pemilik Usaha
                                </h3>
                                <div className="space-y-2.5 text-xs">
                                    <div>
                                        <span className="text-slate-400">
                                            Nama Lengkap
                                        </span>
                                        <div className="font-semibold text-slate-900">
                                            {tenant.owner?.name}
                                        </div>
                                    </div>
                                    <div>
                                        <span className="text-slate-400">
                                            Alamat Email
                                        </span>
                                        <div className="font-medium text-slate-900">
                                            {tenant.owner?.email}
                                        </div>
                                    </div>
                                    <div>
                                        <span className="text-slate-400">
                                            ID Pengguna
                                        </span>
                                        <div className="font-mono text-slate-900">
                                            User #{tenant.owner_user_id}
                                        </div>
                                    </div>
                                    <div className="border-t border-slate-100 pt-2">
                                        <span className="text-slate-400">
                                            Tanggal Pendaftaran
                                        </span>
                                        <div className="text-slate-900">
                                            {new Date(
                                                tenant.created_at
                                            ).toLocaleString('id-ID')}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Team Members List */}
                            <div className="rounded-[12px] border border-slate-200 bg-white p-5 shadow-none">
                                <div className="mb-2 flex items-center justify-between">
                                    <h3 className="text-sm font-semibold text-slate-900">
                                        Tim & Anggota ({tenant.members?.length || 0})
                                    </h3>
                                </div>
                                <div className="divide-y divide-slate-100 text-xs">
                                    {tenant.members?.map((m) => (
                                        <div
                                            key={m.id}
                                            className="flex items-center justify-between py-2"
                                        >
                                            <div className="min-w-0 pr-2">
                                                <span className="block truncate font-medium text-slate-900">
                                                    {m.user?.name}
                                                </span>
                                                <span className="block truncate text-[10px] text-slate-400">
                                                    {m.user?.email}
                                                </span>
                                            </div>
                                            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-semibold text-slate-600">
                                                {m.role}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Tab 2: Subscription & Billing */}
                {activeTab === 'subscription' && (
                    <div className="space-y-6">
                        {/* Current Subscription Card */}
                        <div className="rounded-[12px] border border-slate-200 bg-white p-5 shadow-none">
                            <h3 className="mb-4 text-sm font-semibold text-slate-900">
                                Status Langganan Saat Ini
                            </h3>
                            <div className="grid grid-cols-2 gap-4 text-xs sm:grid-cols-4">
                                <div>
                                    <span className="block text-slate-400">
                                        Paket Aktif
                                    </span>
                                    <span className="text-sm font-bold text-slate-900">
                                        {plan?.name || 'Trial'}
                                    </span>
                                </div>
                                <div>
                                    <span className="block text-slate-400">
                                        Harga Langganan
                                    </span>
                                    <span className="font-semibold text-slate-900">
                                        Rp{' '}
                                        {Number(
                                            plan?.price_idr || 0
                                        ).toLocaleString('id-ID')}{' '}
                                        / {plan?.billing_cycle || 'MONTHLY'}
                                    </span>
                                </div>
                                <div>
                                    <span className="block text-slate-400">
                                        Status Lisensi
                                    </span>
                                    <span className="font-bold text-emerald-600">
                                        {sub?.status || 'TRIAL'}
                                    </span>
                                </div>
                                <div>
                                    <span className="block text-slate-400">
                                        Batas Akhir Periode / Trial
                                    </span>
                                    <span className="font-semibold text-slate-900">
                                        {sub?.trial_ends_at
                                            ? new Date(
                                                  sub.trial_ends_at
                                              ).toLocaleDateString('id-ID')
                                            : sub?.current_period_end
                                              ? new Date(
                                                    sub.current_period_end
                                                ).toLocaleDateString('id-ID')
                                              : '-'}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Invoices History */}
                        <div className="rounded-[12px] border border-slate-200 bg-white p-5 shadow-none">
                            <h3 className="mb-3 text-sm font-semibold text-slate-900">
                                Riwayat Tagihan & Faktur (Invoices)
                            </h3>
                            <div className="divide-y divide-slate-100 text-xs">
                                {invoices.length === 0 ? (
                                    <p className="py-4 text-center text-slate-400">
                                        Belum ada tagihan terbit untuk tenant
                                        ini.
                                    </p>
                                ) : (
                                    invoices.map((inv) => (
                                        <div
                                            key={inv.id}
                                            className="flex items-center justify-between py-2.5"
                                        >
                                            <div>
                                                <span className="font-mono font-medium text-slate-900">
                                                    {inv.invoice_number}
                                                </span>
                                                <div className="text-[11px] text-slate-400">
                                                    Jatuh tempo:{' '}
                                                    {new Date(
                                                        inv.due_at
                                                    ).toLocaleDateString(
                                                        'id-ID'
                                                    )}
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <span className="font-semibold text-slate-900">
                                                    Rp{' '}
                                                    {Number(
                                                        inv.amount_idr
                                                    ).toLocaleString('id-ID')}
                                                </span>
                                                <div>
                                                    <span
                                                        className={`rounded-full px-2 py-0.5 text-[10px] font-medium ${
                                                            inv.status ===
                                                            'PAID'
                                                                ? 'bg-emerald-50 text-emerald-700'
                                                                : 'bg-amber-50 text-amber-700'
                                                        }`}
                                                    >
                                                        {inv.status}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>
                    </div>
                )}

                {/* Tab 3: Usage Limits */}
                {activeTab === 'usage' && (
                    <div className="rounded-[12px] border border-slate-200 bg-white p-5 shadow-none">
                        <h3 className="mb-4 text-sm font-semibold text-slate-900">
                            Penggunaan Resource vs Batas Kuota Paket (PRD 73, 75)
                        </h3>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div className="rounded-lg border border-slate-100 bg-[#f8fafc] p-4 text-xs">
                                <span className="text-slate-400">
                                    Anggota Tim / Staf
                                </span>
                                <div className="mt-2 text-2xl font-bold text-slate-900">
                                    {usage.members_count}{' '}
                                    <span className="text-xs font-normal text-slate-400">
                                        /{' '}
                                        {plan?.limits?.max_members ||
                                            'Tidak Terbatas'}
                                    </span>
                                </div>
                            </div>

                            <div className="rounded-lg border border-slate-100 bg-[#f8fafc] p-4 text-xs">
                                <span className="text-slate-400">
                                    Katalog Layanan
                                </span>
                                <div className="mt-2 text-2xl font-bold text-slate-900">
                                    {usage.services_count}{' '}
                                    <span className="text-xs font-normal text-slate-400">
                                        /{' '}
                                        {plan?.limits?.max_services ||
                                            'Tidak Terbatas'}
                                    </span>
                                </div>
                            </div>

                            <div className="rounded-lg border border-slate-100 bg-[#f8fafc] p-4 text-xs">
                                <span className="text-slate-400">
                                    Booking Bulan Ini
                                </span>
                                <div className="mt-2 text-2xl font-bold text-slate-900">
                                    {usage.bookings_month_count}{' '}
                                    <span className="text-xs font-normal text-slate-400">
                                        /{' '}
                                        {plan?.limits?.max_monthly_bookings ||
                                            'Tidak Terbatas'}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Tab 4: Support Notes */}
                {activeTab === 'notes' && (
                    <div className="rounded-[12px] border border-slate-200 bg-white p-5 shadow-none">
                        <div className="mb-3 flex items-center justify-between">
                            <div>
                                <h3 className="text-sm font-semibold text-slate-900">
                                    Catatan Internal Dukungan Teknis (Support Notes)
                                </h3>
                                <p className="text-xs text-slate-500">
                                    Hanya dapat dilihat oleh Super Admin platform (PRD 74).
                                </p>
                            </div>
                        </div>

                        <form onSubmit={handleSaveNotes} className="space-y-3">
                            <Textarea
                                rows={6}
                                value={supportNotes}
                                onChange={(e) => setSupportNotes(e.target.value)}
                                placeholder="Tuliskan catatan khusus terkait tenant ini (misal: perjanjian khusus, eskalasi bug, kontak darurat)..."
                                className="text-xs font-mono"
                            />

                            <div className="flex justify-end">
                                <Button
                                    type="submit"
                                    variant="primary"
                                    size="sm"
                                    isLoading={isSavingNotes}
                                    className="gap-1.5"
                                >
                                    <Save className="h-3.5 w-3.5" />
                                    <span>Simpan Catatan Dukungan</span>
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Tab 5: Audit Log */}
                {activeTab === 'audit' && (
                    <div className="rounded-[12px] border border-slate-200 bg-white p-5 shadow-none">
                        <h3 className="mb-3 text-sm font-semibold text-slate-900">
                            Log Audit & Rekam Jejak Tenant (Termasuk Support Access)
                        </h3>

                        <div className="divide-y divide-slate-100 text-xs">
                            {auditLogs.length === 0 ? (
                                <p className="py-4 text-center text-slate-400">
                                    Belum ada catatan audit log.
                                </p>
                            ) : (
                                auditLogs.map((log) => (
                                    <div
                                        key={log.id}
                                        className="flex items-center justify-between py-2.5"
                                    >
                                        <div className="min-w-0 pr-3">
                                            <span className="font-mono font-semibold text-slate-900">
                                                {log.action}
                                            </span>
                                            <div className="text-[11px] text-slate-400">
                                                Aktor: {log.actor?.name || 'Sistem'}{' '}
                                                ({log.actor?.email || 'System'})
                                            </div>
                                            {log.after?.reason && (
                                                <div className="mt-0.5 text-[11px] text-amber-700 italic">
                                                    Alasan: "{log.after.reason}"
                                                </div>
                                            )}
                                        </div>
                                        <span className="shrink-0 text-[10px] text-slate-400">
                                            {new Date(
                                                log.created_at
                                            ).toLocaleString('id-ID')}
                                        </span>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                )}
            </div>

            {/* Support Access Modal */}
            <Modal
                isOpen={isSupportAccessOpen}
                onClose={() => setIsSupportAccessOpen(false)}
                title="Sesi Dukungan Teknis (Support Access)"
                description={`Akses masuk ke workspace tenant ${tenant.name} sebagai Super Admin (PRD 74).`}
            >
                <form onSubmit={handleSupportAccessSubmit} className="space-y-3">
                    <div className="flex items-start gap-2 rounded-[8px] border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                        <div>
                            <strong>Pemberitahuan Audit:</strong> Tindakan ini
                            akan tercatat dalam rekam jejak audit tenant dan
                            dapat dilihat oleh pemilik usaha.
                        </div>
                    </div>

                    <Textarea
                        label="Alasan Dukungan Teknis (Wajib Diisi)"
                        required
                        rows={2}
                        value={supportReason}
                        onChange={(e) => setSupportReason(e.target.value)}
                        placeholder="Contoh: Bantuan sinkronisasi invoice dan troubleshooting slot booking."
                    />

                    <div className="flex justify-end gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsSupportAccessOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant="primary"
                            disabled={supportReason.trim().length < 5}
                            isLoading={isSubmittingSupport}
                        >
                            Masuk Sesi Dukungan
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Change Plan Modal */}
            <Modal
                isOpen={isChangePlanOpen}
                onClose={() => setIsChangePlanOpen(false)}
                title="Ubah Paket Langganan"
                description={`Ubah paket aktif untuk tenant ${tenant.name} (PRD 75).`}
            >
                <form onSubmit={handleChangePlanSubmit} className="space-y-3">
                    <div>
                        <label className="mb-1 block text-xs font-medium text-slate-700">
                            Pilih Paket Langganan
                        </label>
                        <select
                            value={selectedPlanId}
                            onChange={(e) => setSelectedPlanId(e.target.value)}
                            required
                            className="w-full rounded-[8px] border border-slate-200 bg-[#f8fafc] px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:bg-white focus:outline-none"
                        >
                            {availablePlans.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name} (Rp{' '}
                                    {Number(p.price_idr).toLocaleString(
                                        'id-ID'
                                    )}
                                    )
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsChangePlanOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant="primary"
                            isLoading={isSubmittingPlan}
                        >
                            Simpan Perubahan
                        </Button>
                    </div>
                </form>
            </Modal>
        </AdminLayout>
    );
}
