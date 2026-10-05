import { Link, router } from '@inertiajs/react';
import {
    Activity,
    AlertCircle,
    ArrowRight,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    CreditCard,
    Eye,
    KeyRound,
    MoreHorizontal,
    Search,
    ShieldAlert,
    UserCheck,
    Users,
} from 'lucide-react';
import React, { useState } from 'react';
import { Button, DataTable, Modal, Textarea, useToast } from '../../../Components/ui';
import { AdminLayout } from '../../../Layouts/AdminLayout';

interface TenantItem {
    id: number;
    uuid: string;
    name: string;
    status: string;
    owner_user_id: number;
    support_notes?: string | null;
    created_at: string;
    services_count: number;
    bookings_count: number;
    last_activity: string;
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
        trial_ends_at: string | null;
        current_period_end: string | null;
        plan?: {
            id: number;
            code: string;
            name: string;
            price_idr: number;
        } | null;
    } | null;
}

interface PaginatedTenants {
    data: TenantItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface AdminTenantsIndexProps {
    tenants: PaginatedTenants;
    filters: {
        search: string;
        status: string;
        plan_id: string;
    };
    plans: Array<{
        id: number;
        code: string;
        name: string;
    }>;
}

const STATUS_OPTIONS = [
    { key: 'ALL', label: 'Semua Status' },
    { key: 'ACTIVE', label: 'Aktif' },
    { key: 'TRIAL', label: 'Trial' },
    { key: 'SUSPENDED', label: 'Suspended' },
    { key: 'EXPIRED', label: 'Expired' },
];

export default function AdminTenantsIndex({
    tenants,
    filters,
    plans,
}: AdminTenantsIndexProps) {
    const toast = useToast();

    const [search, setSearch] = useState(filters.search || '');
    const [selectedStatus, setSelectedStatus] = useState(filters.status || 'ALL');
    const [selectedPlanId, setSelectedPlanId] = useState(filters.plan_id || 'ALL');

    // Modals state
    const [activeTenant, setActiveTenant] = useState<TenantItem | null>(null);

    const [isSuspendOpen, setIsSuspendOpen] = useState(false);
    const [suspendReason, setSuspendReason] = useState('');
    const [isSubmittingSuspend, setIsSubmittingSuspend] = useState(false);

    const [isExtendTrialOpen, setIsExtendTrialOpen] = useState(false);
    const [extendDays, setExtendDays] = useState(14);
    const [isSubmittingExtend, setIsSubmittingExtend] = useState(false);

    const [isChangePlanOpen, setIsChangePlanOpen] = useState(false);
    const [newPlanId, setNewPlanId] = useState<number | string>('');
    const [isSubmittingPlan, setIsSubmittingPlan] = useState(false);

    const [isSupportAccessOpen, setIsSupportAccessOpen] = useState(false);
    const [supportReason, setSupportReason] = useState('');
    const [isSubmittingSupport, setIsSubmittingSupport] = useState(false);

    const applyFilters = (newParams: { search?: string; status?: string; plan_id?: string }) => {
        const s = newParams.search !== undefined ? newParams.search : search;
        const st = newParams.status !== undefined ? newParams.status : selectedStatus;
        const p = newParams.plan_id !== undefined ? newParams.plan_id : selectedPlanId;

        router.get(
            '/admin/tenants',
            {
                search: s.trim() || undefined,
                status: st !== 'ALL' ? st : undefined,
                plan_id: p !== 'ALL' ? p : undefined,
            },
            { preserveState: true, replace: true }
        );
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters({ search });
    };

    const handleReset = () => {
        setSearch('');
        setSelectedStatus('ALL');
        setSelectedPlanId('ALL');
        router.get('/admin/tenants', {}, { preserveState: true, replace: true });
    };

    const handleSuspendSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!activeTenant) return;
        setIsSubmittingSuspend(true);

        router.post(
            `/admin/tenants/${activeTenant.id}/suspend`,
            { reason: suspendReason.trim() || null },
            {
                onSuccess: () => {
                    setIsSuspendOpen(false);
                    setSuspendReason('');
                    setActiveTenant(null);
                    toast.success('Tenant berhasil dinonaktifkan (SUSPENDED).');
                },
                onError: () => toast.error('Gagal menonaktifkan tenant.'),
                onFinish: () => setIsSubmittingSuspend(false),
            }
        );
    };

    const handleActivate = (t: TenantItem) => {
        if (!confirm(`Aktifkan kembali tenant '${t.name}'?`)) return;

        router.post(
            `/admin/tenants/${t.id}/activate`,
            {},
            {
                onSuccess: () => toast.success('Tenant berhasil diaktifkan kembali.'),
                onError: () => toast.error('Gagal mengaktifkan tenant.'),
            }
        );
    };

    const handleExtendTrialSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!activeTenant) return;
        setIsSubmittingExtend(true);

        router.post(
            `/admin/tenants/${activeTenant.id}/extend-trial`,
            { days: Number(extendDays) },
            {
                onSuccess: () => {
                    setIsExtendTrialOpen(false);
                    setActiveTenant(null);
                    toast.success('Masa trial berhasil diperpanjang.');
                },
                onError: () => toast.error('Gagal memperpanjang trial.'),
                onFinish: () => setIsSubmittingExtend(false),
            }
        );
    };

    const handleChangePlanSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!activeTenant || !newPlanId) return;
        setIsSubmittingPlan(true);

        router.post(
            `/admin/tenants/${activeTenant.id}/change-plan`,
            { plan_id: Number(newPlanId) },
            {
                onSuccess: () => {
                    setIsChangePlanOpen(false);
                    setActiveTenant(null);
                    toast.success('Paket langganan tenant berhasil diubah.');
                },
                onError: () => toast.error('Gagal mengubah paket tenant.'),
                onFinish: () => setIsSubmittingPlan(false),
            }
        );
    };

    const handleSupportAccessSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!activeTenant || !supportReason.trim()) return;
        setIsSubmittingSupport(true);

        router.post(
            `/admin/tenants/${activeTenant.id}/support-access`,
            { reason: supportReason.trim() },
            {
                onSuccess: () => {
                    setIsSupportAccessOpen(false);
                },
                onError: () => {
                    toast.error('Gagal mengakses sesi dukungan teknis.');
                    setIsSubmittingSupport(false);
                },
            }
        );
    };

    const columns = [
        {
            key: 'business',
            header: 'BISNIS & TENANT',
            render: (t: TenantItem) => (
                <div className="flex items-center gap-3">
                    <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xs font-bold text-slate-700">
                        {t.name.substring(0, 2).toUpperCase()}
                    </div>
                    <div className="min-w-0">
                        <Link
                            href={`/admin/tenants/${t.id}`}
                            className="block truncate font-semibold text-slate-900 hover:text-blue-600 hover:underline"
                        >
                            {t.name}
                        </Link>
                        <div className="truncate font-mono text-[10px] text-slate-400">
                            {t.business?.slug ? `/${t.business.slug}` : 'Belum publish bisnis'}
                        </div>
                    </div>
                </div>
            ),
        },
        {
            key: 'owner',
            header: 'PEMILIK USAHA',
            render: (t: TenantItem) => (
                <div className="min-w-0">
                    <span className="block truncate font-medium text-slate-900">
                        {t.owner?.name || 'Tanpa Owner'}
                    </span>
                    <span className="block truncate text-[11px] text-slate-500">
                        {t.owner?.email || '-'}
                    </span>
                </div>
            ),
        },
        {
            key: 'plan',
            header: 'PAKET LANGGANAN',
            render: (t: TenantItem) => {
                const sub = t.current_subscription;
                return (
                    <div>
                        <span className="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-semibold text-slate-700">
                            {sub?.plan?.name || 'Trial (Default)'}
                        </span>
                        {sub?.plan && (
                            <span className="mt-0.5 block text-[10px] text-slate-400">
                                Rp {Number(sub.plan.price_idr).toLocaleString('id-ID')}/bln
                            </span>
                        )}
                    </div>
                );
            },
        },
        {
            key: 'status',
            header: 'STATUS',
            render: (t: TenantItem) => {
                const isSuspended = t.status === 'SUSPENDED';
                const subStatus = t.current_subscription?.status || 'TRIAL';

                if (isSuspended) {
                    return (
                        <span className="inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-700">
                            <ShieldAlert className="h-3 w-3" />
                            SUSPENDED
                        </span>
                    );
                }

                return (
                    <span
                        className={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-semibold ${
                            subStatus === 'ACTIVE'
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                : subStatus === 'TRIAL'
                                  ? 'border-amber-200 bg-amber-50 text-amber-700'
                                  : 'border-slate-200 bg-slate-100 text-slate-600'
                        }`}
                    >
                        {subStatus === 'ACTIVE' && <CheckCircle2 className="h-3 w-3" />}
                        {subStatus === 'TRIAL' && <Clock className="h-3 w-3" />}
                        {subStatus}
                    </span>
                );
            },
        },
        {
            key: 'usage',
            header: 'PENGGUNAAN',
            render: (t: TenantItem) => (
                <div className="text-[11px] text-slate-600">
                    <div>
                        <span className="font-semibold text-slate-900">{t.services_count}</span> layanan
                    </div>
                    <div>
                        <span className="font-semibold text-slate-900">{t.bookings_count}</span> booking
                    </div>
                </div>
            ),
        },
        {
            key: 'dates',
            header: 'TANGGAL & RENEWAL',
            render: (t: TenantItem) => {
                const trialEnd = t.current_subscription?.trial_ends_at;
                const periodEnd = t.current_subscription?.current_period_end;
                return (
                    <div className="text-[10px] text-slate-500">
                        <div>Dibuat: {new Date(t.created_at).toLocaleDateString('id-ID')}</div>
                        {trialEnd && (
                            <div className="text-amber-600">
                                Trial s/d: {new Date(trialEnd).toLocaleDateString('id-ID')}
                            </div>
                        )}
                        {periodEnd && (
                            <div className="text-slate-600">
                                Renewal: {new Date(periodEnd).toLocaleDateString('id-ID')}
                            </div>
                        )}
                    </div>
                );
            },
        },
        {
            key: 'actions',
            header: 'AKSI',
            align: 'right' as const,
            render: (t: TenantItem) => (
                <div className="flex items-center justify-end gap-1">
                    <Link
                        href={`/admin/tenants/${t.id}`}
                        title="Lihat Detail Tenant"
                        className="rounded p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                    >
                        <Eye className="h-4 w-4" />
                    </Link>

                    {/* Support Access */}
                    <button
                        type="button"
                        onClick={() => {
                            setActiveTenant(t);
                            setSupportReason('');
                            setIsSupportAccessOpen(true);
                        }}
                        title="Support Access (Inspeksi Workspace)"
                        className="rounded p-1 text-slate-500 hover:bg-slate-100 hover:text-blue-600"
                    >
                        <KeyRound className="h-4 w-4" />
                    </button>

                    {/* Change Plan */}
                    <button
                        type="button"
                        onClick={() => {
                            setActiveTenant(t);
                            setNewPlanId(t.current_subscription?.plan?.id || plans[0]?.id || '');
                            setIsChangePlanOpen(true);
                        }}
                        title="Ubah Paket Langganan"
                        className="rounded p-1 text-slate-500 hover:bg-slate-100 hover:text-emerald-600"
                    >
                        <CreditCard className="h-4 w-4" />
                    </button>

                    {/* Extend Trial */}
                    <button
                        type="button"
                        onClick={() => {
                            setActiveTenant(t);
                            setExtendDays(14);
                            setIsExtendTrialOpen(true);
                        }}
                        title="Perpanjang Masa Trial"
                        className="rounded p-1 text-slate-500 hover:bg-slate-100 hover:text-amber-600"
                    >
                        <Clock className="h-4 w-4" />
                    </button>

                    {/* Suspend or Activate */}
                    {t.status === 'SUSPENDED' ? (
                        <button
                            type="button"
                            onClick={() => handleActivate(t)}
                            title="Aktifkan Kembali Tenant"
                            className="rounded p-1 text-emerald-600 hover:bg-emerald-50"
                        >
                            <CheckCircle2 className="h-4 w-4" />
                        </button>
                    ) : (
                        <button
                            type="button"
                            onClick={() => {
                                setActiveTenant(t);
                                setSuspendReason('');
                                setIsSuspendOpen(true);
                            }}
                            title="Suspend Tenant"
                            className="rounded p-1 text-rose-500 hover:bg-rose-50 hover:text-rose-700"
                        >
                            <ShieldAlert className="h-4 w-4" />
                        </button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <AdminLayout title="Kelola Tenant">
            <div className="space-y-4">
                {/* Header */}
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-bold tracking-tight text-slate-900">
                                Manajemen Tenant Platform
                            </h1>
                            <span className="rounded-full border border-slate-200 bg-white px-2 py-0.5 text-xs font-semibold text-slate-600">
                                {tenants.total} Total
                            </span>
                        </div>
                        <p className="mt-0.5 text-xs text-slate-500">
                            Kelola lisensi, status operasional (suspend/activate), perpanjangan trial, dan support access (PRD 73, 74).
                        </p>
                    </div>
                </div>

                {/* Filter & Search Bar */}
                <div className="rounded-[12px] border border-slate-200 bg-white p-3 shadow-none">
                    <form onSubmit={handleSearchSubmit} className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div className="relative flex-1">
                            <Search className="absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Cari nama tenant, nama owner, email, atau slug bisnis..."
                                className="h-9 w-full rounded-[8px] border border-slate-200 bg-[#f8fafc] pr-3 pl-8 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:bg-white focus:outline-none"
                            />
                        </div>

                        {/* Status Pills */}
                        <div className="flex items-center gap-1 overflow-x-auto text-xs">
                            {STATUS_OPTIONS.map((st) => (
                                <button
                                    key={st.key}
                                    type="button"
                                    onClick={() => {
                                        setSelectedStatus(st.key);
                                        applyFilters({ status: st.key });
                                    }}
                                    className={`rounded-full px-2.5 py-1 text-xs font-medium whitespace-nowrap transition-colors ${
                                        selectedStatus === st.key
                                            ? 'bg-blue-600 text-white'
                                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                    }`}
                                >
                                    {st.label}
                                </button>
                            ))}
                        </div>

                        {/* Plan Select */}
                        <select
                            value={selectedPlanId}
                            onChange={(e) => {
                                setSelectedPlanId(e.target.value);
                                applyFilters({ plan_id: e.target.value });
                            }}
                            className="h-9 rounded-[8px] border border-slate-200 bg-[#f8fafc] px-3 text-xs text-slate-700 focus:border-blue-600 focus:outline-none"
                        >
                            <option value="ALL">Semua Paket</option>
                            {plans.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </select>

                        <Button type="submit" variant="secondary" size="sm">
                            Cari
                        </Button>

                        {(search || selectedStatus !== 'ALL' || selectedPlanId !== 'ALL') && (
                            <Button type="button" variant="ghost" size="sm" onClick={handleReset} className="text-slate-500">
                                Reset
                            </Button>
                        )}
                    </form>
                </div>

                {/* Tenant Table */}
                <div className="space-y-3">
                    <DataTable
                        columns={columns}
                        data={tenants.data}
                        keyExtractor={(item) => item.id}
                        emptyText="Belum ada data tenant yang cocok dengan kriteria pencarian."
                    />

                    {/* Pagination */}
                    {tenants.total > tenants.per_page && (
                        <div className="flex items-center justify-between rounded-[12px] border border-slate-200 bg-white px-4 py-2.5 text-xs text-slate-500">
                            <div>
                                Menampilkan <span className="font-medium text-slate-900">{tenants.from || 0}</span> -{' '}
                                <span className="font-medium text-slate-900">{tenants.to || 0}</span> dari{' '}
                                <span className="font-medium text-slate-900">{tenants.total}</span> tenant
                            </div>
                            <div className="flex items-center gap-1">
                                {tenants.links.map((link, idx) => {
                                    if (!link.url) {
                                        return (
                                            <span
                                                key={idx}
                                                className="px-2 py-1 text-slate-300 select-none"
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        );
                                    }
                                    return (
                                        <Link
                                            key={idx}
                                            href={link.url}
                                            preserveState
                                            className={`rounded px-2.5 py-1 text-xs font-medium transition-colors ${
                                                link.active ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Suspend Modal */}
            <Modal
                isOpen={isSuspendOpen}
                onClose={() => setIsSuspendOpen(false)}
                title="Tangguhkan / Suspend Tenant"
                description={`Tindakan ini akan menutup booking publik (503 TENANT_UNAVAILABLE) dan membatasi akses owner untuk tenant: ${activeTenant?.name}`}
            >
                <form onSubmit={handleSuspendSubmit} className="space-y-3">
                    <div className="flex items-start gap-2 rounded-[8px] border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800">
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0 text-rose-600" />
                        <div>
                            <strong>Peringatan Penting:</strong> Customer tidak akan dapat melakukan booking di landing page bisnis ini sampai tenant diaktifkan kembali.
                        </div>
                    </div>

                    <Textarea
                        label="Alasan Penangguhan (Opsional)"
                        rows={2}
                        value={suspendReason}
                        onChange={(e) => setSuspendReason(e.target.value)}
                        placeholder="Contoh: Masalah pembayaran yang belum diselesaikan / pelanggaran kebijakan platform."
                    />

                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => setIsSuspendOpen(false)}>
                            Batal
                        </Button>
                        <Button type="submit" variant="danger" isLoading={isSubmittingSuspend}>
                            Tangguhkan Sekarang (SUSPEND)
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Extend Trial Modal */}
            <Modal
                isOpen={isExtendTrialOpen}
                onClose={() => setIsExtendTrialOpen(false)}
                title="Perpanjang Masa Trial"
                description={`Tambahkan hari masa percobaan gratis untuk tenant: ${activeTenant?.name}`}
            >
                <form onSubmit={handleExtendTrialSubmit} className="space-y-3">
                    <div>
                        <label className="mb-1 block text-xs font-medium text-slate-700">
                            Jumlah Hari Perpanjangan
                        </label>
                        <div className="flex gap-2">
                            {[7, 14, 30].map((d) => (
                                <button
                                    key={d}
                                    type="button"
                                    onClick={() => setExtendDays(d)}
                                    className={`flex-1 rounded-[8px] border py-2 text-xs font-semibold transition-colors ${
                                        extendDays === d
                                            ? 'border-blue-600 bg-blue-50 text-blue-700'
                                            : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                                    }`}
                                >
                                    +{d} Hari
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => setIsExtendTrialOpen(false)}>
                            Batal
                        </Button>
                        <Button type="submit" variant="primary" isLoading={isSubmittingExtend}>
                            Simpan Perpanjangan
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Change Plan Modal */}
            <Modal
                isOpen={isChangePlanOpen}
                onClose={() => setIsChangePlanOpen(false)}
                title="Ubah Paket Langganan Tenant"
                description={`Pilih paket baru untuk tenant: ${activeTenant?.name}. Data transaksi dan histori booking tetap terjaga (PRD 75).`}
            >
                <form onSubmit={handleChangePlanSubmit} className="space-y-3">
                    <div>
                        <label className="mb-1 block text-xs font-medium text-slate-700">
                            Pilih Paket Langganan Baru
                        </label>
                        <select
                            value={newPlanId}
                            onChange={(e) => setNewPlanId(e.target.value)}
                            required
                            className="w-full rounded-[8px] border border-slate-200 bg-[#f8fafc] px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:bg-white focus:outline-none"
                        >
                            {plans.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => setIsChangePlanOpen(false)}>
                            Batal
                        </Button>
                        <Button type="submit" variant="primary" isLoading={isSubmittingPlan}>
                            Simpan Perubahan Paket
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Support Access Modal */}
            <Modal
                isOpen={isSupportAccessOpen}
                onClose={() => setIsSupportAccessOpen(false)}
                title="Sesi Dukungan Teknis (Support Access)"
                description={`Memasuki workspace tenant: ${activeTenant?.name} untuk investigasi teknis (PRD 74).`}
            >
                <form onSubmit={handleSupportAccessSubmit} className="space-y-3">
                    <div className="flex items-start gap-2 rounded-[8px] border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                        <div>
                            <strong>Pemberitahuan Kepatuhan & Keamanan:</strong> Sesuai PRD 74, akses dukungan teknis wajib menyertakan alasan investigasi dan akan dicatat secara transparan di log audit tenant yang dapat dilihat oleh pemilik usaha.
                        </div>
                    </div>

                    <Textarea
                        label="Alasan Dukungan Teknis (Wajib Diisi)"
                        required
                        rows={2}
                        value={supportReason}
                        onChange={(e) => setSupportReason(e.target.value)}
                        placeholder="Contoh: Investigasi konfigurasi availability dan workflow sesuai tiket dukungan #1234."
                    />

                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => setIsSupportAccessOpen(false)}>
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant="primary"
                            disabled={supportReason.trim().length < 5}
                            isLoading={isSubmittingSupport}
                        >
                            Mulai Sesi Dukungan
                        </Button>
                    </div>
                </form>
            </Modal>
        </AdminLayout>
    );
}
