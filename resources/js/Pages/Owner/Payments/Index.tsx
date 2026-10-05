import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowDownLeft,
    ArrowUpRight,
    Banknote,
    Check,
    Clock,
    Copy,
    CreditCard,
    Key,
    Search,
    Shield,
    Sliders,
} from 'lucide-react';
import React, { useState } from 'react';
import { Badge } from '../../../Components/ui/Badge';
import { Button } from '../../../Components/ui/Button';
import { Modal } from '../../../Components/ui/Modal';
import { useToast } from '../../../Components/ui/Toast';
import { OwnerLayout } from '../../../Layouts/OwnerLayout';

interface CustomerInfo {
    id: number;
    name: string;
    phone?: string;
    email?: string;
}

interface BookingInfo {
    id: number;
    code: string;
    customer?: CustomerInfo;
}

interface PaymentRecord {
    id: number;
    payment_number: string;
    provider: string;
    provider_transaction_id?: string;
    payment_method: string;
    amount_idr: number;
    status: string;
    snap_token?: string;
    checkout_url?: string;
    qr_string?: string;
    paid_at?: string;
    created_at: string;
    invoice_id: number;
    invoice?: InvoiceRecord;
    booking?: BookingInfo;
}

interface InvoiceRecord {
    id: number;
    invoice_number: string;
    payment_model: string;
    amount_total_idr: number;
    amount_due_idr: number;
    amount_paid_idr: number;
    status: string;
    due_at?: string;
    notes?: string;
    created_at: string;
    booking?: BookingInfo;
    payments?: PaymentRecord[];
}

interface RefundRecord {
    id: number;
    payment_id: number;
    booking_id: number;
    amount_idr: number;
    reason: string;
    status: string;
    notes?: string;
    created_at: string;
    payment?: PaymentRecord;
    booking?: BookingInfo;
    requested_by?: { id: number; name: string };
    approved_by?: { id: number; name: string };
}

interface PaginationMeta<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface PaymentSettingsData {
    default_provider: string;
    default_model: string;
    deposit_percentage: number;
    is_production: boolean;
    has_midtrans_key: boolean;
    has_xendit_key: boolean;
    midtrans_client_key: string;
    xendit_public_key: string;
    xendit_webhook_token: string;
}

interface Props {
    invoices: PaginationMeta<InvoiceRecord>;
    payments: PaginationMeta<PaymentRecord>;
    pending_refunds: RefundRecord[];
    metrics: {
        total_collected_idr: number;
        total_pending_idr: number;
        total_refunded_idr: number;
        total_invoices_count: number;
    };
    filters: {
        search: string;
        status: string;
        provider: string;
        tab: string;
    };
    settings: PaymentSettingsData;
    permissions: {
        can_approve_refund: boolean;
    };
    business: {
        id: number;
        slug: string;
        name: string;
    };
}

export default function PaymentsIndex({
    invoices,
    payments,
    pending_refunds,
    metrics,
    filters,
    settings,
    permissions,
    business: _business,
}: Props) {
    const toast = useToast();
    const [currentTab, setCurrentTab] = useState<'invoices' | 'payments' | 'refunds' | 'settings'>(
        (filters.tab as 'invoices' | 'payments' | 'refunds' | 'settings') || 'invoices'
    );
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'ALL');
    const [copiedUrl, setCopiedUrl] = useState<string | null>(null);

    // Manual Payment Modal
    const [manualModalOpen, setManualModalOpen] = useState(false);
    const [selectedInvoice, setSelectedInvoice] = useState<InvoiceRecord | null>(null);

    const manualForm = useForm({
        invoice_id: 0,
        amount_idr: 0,
        payment_method: 'cash',
        notes: '',
    });

    // Refund Request Modal
    const [refundModalOpen, setRefundModalOpen] = useState(false);
    const [selectedPayment, setSelectedPayment] = useState<PaymentRecord | null>(null);

    const refundForm = useForm({
        payment_id: 0,
        amount_idr: 0,
        reason: '',
    });

    // Settings Form
    const settingsForm = useForm({
        default_provider: settings.default_provider || 'midtrans',
        default_model: settings.default_model || 'full_payment',
        deposit_percentage: settings.deposit_percentage || 30,
        is_production: settings.is_production,
        midtrans_server_key: '',
        midtrans_client_key: settings.midtrans_client_key || '',
        xendit_secret_key: '',
        xendit_public_key: settings.xendit_public_key || '',
        xendit_webhook_token: settings.xendit_webhook_token || '',
    });

    const formatIdr = (amount: number) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(amount);
    };

    const formatDate = (dateStr?: string) => {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/app/payments',
            {
                search: searchQuery,
                status: statusFilter,
                tab: currentTab,
            },
            { preserveState: true }
        );
    };

    const handleCopy = (text: string, key: string) => {
        navigator.clipboard.writeText(text);
        setCopiedUrl(key);
        toast.success('URL webhook disalin ke clipboard.');
        setTimeout(() => setCopiedUrl(null), 2500);
    };

    const openManualPaymentFor = (inv: InvoiceRecord) => {
        setSelectedInvoice(inv);
        const remaining = Math.max(0, inv.amount_due_idr - inv.amount_paid_idr);
        manualForm.setData({
            invoice_id: inv.id,
            amount_idr: remaining,
            payment_method: 'cash',
            notes: '',
        });
        setManualModalOpen(true);
    };

    const submitManualPayment = (e: React.FormEvent) => {
        e.preventDefault();
        manualForm.post('/app/payments/manual', {
            onSuccess: () => {
                setManualModalOpen(false);
                manualForm.reset();
            },
        });
    };

    const openRefundFor = (pm: PaymentRecord) => {
        setSelectedPayment(pm);
        refundForm.setData({
            payment_id: pm.id,
            amount_idr: pm.amount_idr,
            reason: '',
        });
        setRefundModalOpen(true);
    };

    const submitRefund = (e: React.FormEvent) => {
        e.preventDefault();
        refundForm.post('/app/payments/refunds', {
            onSuccess: () => {
                setRefundModalOpen(false);
                refundForm.reset();
            },
        });
    };

    const handleApproveRefund = (refundId: number) => {
        if (!confirm('Apakah Anda yakin menyetujui pengembalian dana (refund) ini?')) {
            return;
        }
        router.post(`/app/payments/refunds/${refundId}/approve`, {}, {
            preserveScroll: true,
        });
    };

    const submitSettings = (e: React.FormEvent) => {
        e.preventDefault();
        settingsForm.put('/app/settings/payment', {
            preserveScroll: true,
        });
    };

    const webhookOrigin = typeof window !== 'undefined' ? window.location.origin : '';
    const midtransWebhookUrl = `${webhookOrigin}/webhooks/payment/midtrans`;
    const xenditWebhookUrl = `${webhookOrigin}/webhooks/payment/xendit`;

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'PAID':
            case 'SETTLEMENT':
                return <Badge variant="active">LUNAS / BERHASIL</Badge>;
            case 'PENDING':
                return <Badge variant="queuing">MENUNGGU BAYAR</Badge>;
            case 'PARTIAL':
                return <Badge variant="hauling">SEBAGIAN (DP)</Badge>;
            case 'FAILED':
            case 'EXPIRED':
                return <Badge variant="breakdown">GAGAL / KADALUWARSA</Badge>;
            case 'REFUNDED':
                return <Badge variant="breakdown">REFUND SELESAI</Badge>;
            case 'UNPAID':
                return <Badge variant="neutral">BELUM DIBAYAR</Badge>;
            default:
                return <Badge variant="neutral">{status}</Badge>;
        }
    };

    return (
        <OwnerLayout
            title="Pembayaran & Kasir"
            breadcrumbs={[
                { label: 'Dashboard', href: '/app/dashboard' },
                { label: 'Pembayaran & Kasir' },
            ]}
        >
            <Head title="Pembayaran & Kasir - AMAN Booking" />

            <div className="space-y-6">
                {/* 1. Metric Cards (Strict OFALabs: Flat, Slate borders, No drop shadow) */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="rounded-xl border border-slate-200 bg-white p-5">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium uppercase tracking-wider text-slate-500">
                                Penerimaan Lunas
                            </span>
                            <div className="rounded-lg bg-emerald-50 p-2 text-emerald-600">
                                <ArrowDownLeft className="h-4 w-4" />
                            </div>
                        </div>
                        <div className="mt-3 text-2xl font-bold tracking-tight text-slate-900">
                            {formatIdr(metrics.total_collected_idr)}
                        </div>
                        <div className="mt-1 text-xs text-slate-500">
                            Total pembayaran online & kasir
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-white p-5">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium uppercase tracking-wider text-slate-500">
                                Piutang / Pending
                            </span>
                            <div className="rounded-lg bg-amber-50 p-2 text-amber-600">
                                <Clock className="h-4 w-4" />
                            </div>
                        </div>
                        <div className="mt-3 text-2xl font-bold tracking-tight text-slate-900">
                            {formatIdr(metrics.total_pending_idr)}
                        </div>
                        <div className="mt-1 text-xs text-slate-500">
                            Menunggu pembayaran / sisa DP
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-white p-5">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium uppercase tracking-wider text-slate-500">
                                Pengembalian Dana
                            </span>
                            <div className="rounded-lg bg-rose-50 p-2 text-rose-600">
                                <ArrowUpRight className="h-4 w-4" />
                            </div>
                        </div>
                        <div className="mt-3 text-2xl font-bold tracking-tight text-slate-900">
                            {formatIdr(metrics.total_refunded_idr)}
                        </div>
                        <div className="mt-1 text-xs text-slate-500">
                            {pending_refunds.length > 0 ? (
                                <span className="font-semibold text-rose-600">
                                    {pending_refunds.length} permintaan butuh persetujuan
                                </span>
                            ) : (
                                'Semua refund telah disetujui'
                            )}
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-white p-5">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium uppercase tracking-wider text-slate-500">
                                Gateway Aktif
                            </span>
                            <div className="rounded-lg bg-blue-50 p-2 text-blue-600">
                                <CreditCard className="h-4 w-4" />
                            </div>
                        </div>
                        <div className="mt-3 flex items-center gap-2">
                            <span className="text-lg font-bold capitalize text-slate-900">
                                {settings.default_provider}
                            </span>
                            <span
                                className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${
                                    settings.is_production
                                        ? 'bg-emerald-100 text-emerald-800'
                                        : 'bg-amber-100 text-amber-800'
                                }`}
                            >
                                {settings.is_production ? 'Production' : 'Sandbox'}
                            </span>
                        </div>
                        <div className="mt-1 text-xs text-slate-500">
                            Model: {settings.default_model.replace('_', ' ')}
                        </div>
                    </div>
                </div>

                {/* 2. Navigation Tabs */}
                <div className="flex flex-col gap-4 border-b border-slate-200 pb-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => setCurrentTab('invoices')}
                            className={`rounded-lg px-3.5 py-2 text-xs font-semibold transition-colors ${
                                currentTab === 'invoices'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'
                            }`}
                        >
                            Daftar Tagihan (Invoice)
                        </button>
                        <button
                            type="button"
                            onClick={() => setCurrentTab('payments')}
                            className={`rounded-lg px-3.5 py-2 text-xs font-semibold transition-colors ${
                                currentTab === 'payments'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'
                            }`}
                        >
                            Histori Transaksi
                        </button>
                        <button
                            type="button"
                            onClick={() => setCurrentTab('refunds')}
                            className={`relative rounded-lg px-3.5 py-2 text-xs font-semibold transition-colors ${
                                currentTab === 'refunds'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'
                            }`}
                        >
                            Persetujuan Refund
                            {pending_refunds.length > 0 && (
                                <span className="ml-1.5 rounded-full bg-rose-500 px-1.5 py-0.2 text-[10px] text-white">
                                    {pending_refunds.length}
                                </span>
                            )}
                        </button>
                        <button
                            type="button"
                            onClick={() => setCurrentTab('settings')}
                            className={`rounded-lg px-3.5 py-2 text-xs font-semibold transition-colors ${
                                currentTab === 'settings'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'
                            }`}
                        >
                            <span className="flex items-center gap-1.5">
                                <Sliders className="h-3.5 w-3.5" />
                                Pengaturan Gateway
                            </span>
                        </button>
                    </div>

                    {currentTab !== 'settings' && (
                        <form onSubmit={handleSearchSubmit} className="flex items-center gap-2">
                            <div className="relative">
                                <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-400" />
                                <input
                                    type="text"
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    placeholder="Cari invoice / booking / nama..."
                                    className="h-9 w-64 rounded-lg border border-slate-200 bg-white pl-8 pr-3 text-xs text-slate-800 placeholder-slate-400 focus:border-blue-600 focus:outline-none"
                                />
                            </div>
                            <select
                                value={statusFilter}
                                onChange={(e) => {
                                    setStatusFilter(e.target.value);
                                    router.get(
                                        '/app/payments',
                                        { search: searchQuery, status: e.target.value, tab: currentTab },
                                        { preserveState: true }
                                    );
                                }}
                                className="h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-800 focus:border-blue-600 focus:outline-none"
                            >
                                <option value="ALL">Semua Status</option>
                                <option value="PAID">Lunas</option>
                                <option value="PENDING">Pending</option>
                                <option value="PARTIAL">Sebagian (DP)</option>
                                <option value="UNPAID">Belum Bayar</option>
                                <option value="FAILED">Gagal</option>
                            </select>
                            <Button type="submit" size="sm" variant="secondary">
                                Cari
                            </Button>
                        </form>
                    )}
                </div>

                {/* 3. Tab Content */}
                {currentTab === 'invoices' && (
                    <div className="rounded-xl border border-slate-200 bg-white overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="border-b border-slate-200 bg-slate-50 font-semibold text-slate-600 uppercase tracking-wider">
                                    <tr>
                                        <th className="px-4 py-3">No. Invoice</th>
                                        <th className="px-4 py-3">Kode Booking</th>
                                        <th className="px-4 py-3">Pelanggan</th>
                                        <th className="px-4 py-3">Model</th>
                                        <th className="px-4 py-3 text-right">Total Tagihan</th>
                                        <th className="px-4 py-3 text-right">Sudah Dibayar</th>
                                        <th className="px-4 py-3 text-right">Sisa Tagihan</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3">Batas Waktu</th>
                                        <th className="px-4 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
                                    {invoices.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={10} className="py-8 text-center text-slate-400">
                                                Belum ada invoice yang tercatat.
                                            </td>
                                        </tr>
                                    ) : (
                                        invoices.data.map((inv) => {
                                            const remaining = Math.max(0, inv.amount_due_idr - inv.amount_paid_idr);
                                            return (
                                                <tr key={inv.id} className="hover:bg-slate-50/75">
                                                    <td className="px-4 py-3 font-mono font-semibold text-slate-900">
                                                        {inv.invoice_number}
                                                    </td>
                                                    <td className="px-4 py-3 font-mono text-blue-600">
                                                        {inv.booking?.code || '-'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="font-semibold text-slate-900">
                                                            {inv.booking?.customer?.name || 'Pelanggan Walk-in'}
                                                        </div>
                                                        <div className="text-[11px] text-slate-400">
                                                            {inv.booking?.customer?.phone || '-'}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <span className="capitalize text-slate-600">
                                                            {inv.payment_model.replace('_', ' ')}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-semibold text-slate-900">
                                                        {formatIdr(inv.amount_total_idr)}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-semibold text-emerald-600">
                                                        {formatIdr(inv.amount_paid_idr)}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-semibold text-slate-700">
                                                        {formatIdr(remaining)}
                                                    </td>
                                                    <td className="px-4 py-3">{getStatusBadge(inv.status)}</td>
                                                    <td className="px-4 py-3 text-slate-500">
                                                        {formatDate(inv.due_at)}
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        {inv.status !== 'PAID' && (
                                                            <Button
                                                                size="sm"
                                                                variant="secondary"
                                                                onClick={() => openManualPaymentFor(inv)}
                                                                className="text-xs"
                                                            >
                                                                <Banknote className="mr-1 h-3.5 w-3.5 text-emerald-600" />
                                                                Catat Kasir
                                                            </Button>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {currentTab === 'payments' && (
                    <div className="rounded-xl border border-slate-200 bg-white overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="border-b border-slate-200 bg-slate-50 font-semibold text-slate-600 uppercase tracking-wider">
                                    <tr>
                                        <th className="px-4 py-3">No. Transaksi</th>
                                        <th className="px-4 py-3">Invoice & Booking</th>
                                        <th className="px-4 py-3">Provider</th>
                                        <th className="px-4 py-3">Metode</th>
                                        <th className="px-4 py-3">ID Gateway</th>
                                        <th className="px-4 py-3 text-right">Nominal</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3">Waktu Bayar</th>
                                        <th className="px-4 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
                                    {payments.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={9} className="py-8 text-center text-slate-400">
                                                Belum ada transaksi pembayaran.
                                            </td>
                                        </tr>
                                    ) : (
                                        payments.data.map((pm) => (
                                            <tr key={pm.id} className="hover:bg-slate-50/75">
                                                <td className="px-4 py-3 font-mono font-semibold text-slate-900">
                                                    {pm.payment_number}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="font-mono text-slate-800">
                                                        {pm.invoice?.invoice_number || '-'}
                                                    </div>
                                                    <div className="font-mono text-[11px] text-blue-600">
                                                        {pm.booking?.code}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 capitalize font-semibold text-slate-700">
                                                    {pm.provider}
                                                </td>
                                                <td className="px-4 py-3 uppercase text-slate-600">
                                                    {pm.payment_method}
                                                </td>
                                                <td className="px-4 py-3 font-mono text-slate-500">
                                                    {pm.provider_transaction_id || '-'}
                                                </td>
                                                <td className="px-4 py-3 text-right font-semibold text-slate-900">
                                                    {formatIdr(pm.amount_idr)}
                                                </td>
                                                <td className="px-4 py-3">{getStatusBadge(pm.status)}</td>
                                                <td className="px-4 py-3 text-slate-500">
                                                    {formatDate(pm.paid_at || pm.created_at)}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    {pm.status === 'SETTLEMENT' && (
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() => openRefundFor(pm)}
                                                            className="text-rose-600 hover:bg-rose-50"
                                                        >
                                                            Ajukan Refund
                                                        </Button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {currentTab === 'refunds' && (
                    <div className="space-y-4">
                        <div className="rounded-xl border border-slate-200 bg-white p-5">
                            <div className="flex items-center gap-2 text-slate-900 font-semibold mb-1">
                                <Shield className="h-4 w-4 text-blue-600" />
                                <span>Gerbang Otorisasi Refund (Owner Gate - PRD 212)</span>
                            </div>
                            <p className="text-xs text-slate-500">
                                Pengembalian dana (refund) yang diajukan oleh Manajer atau Staf memerlukan persetujuan langsung dari Pemilik Usaha (Owner) sebelum saldo invoice disesuaikan.
                            </p>
                        </div>

                        <div className="rounded-xl border border-slate-200 bg-white overflow-hidden">
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead className="border-b border-slate-200 bg-slate-50 font-semibold text-slate-600 uppercase tracking-wider">
                                        <tr>
                                            <th className="px-4 py-3">No. Transaksi / Booking</th>
                                            <th className="px-4 py-3">Pelanggan</th>
                                            <th className="px-4 py-3">Diajukan Oleh</th>
                                            <th className="px-4 py-3 text-right">Nominal Refund</th>
                                            <th className="px-4 py-3">Alasan Pengembalian</th>
                                            <th className="px-4 py-3">Waktu Pengajuan</th>
                                            <th className="px-4 py-3 text-right">Aksi Otorisasi</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
                                        {pending_refunds.length === 0 ? (
                                            <tr>
                                                <td colSpan={7} className="py-8 text-center text-slate-400">
                                                    Tidak ada permohonan refund yang menunggu persetujuan.
                                                </td>
                                            </tr>
                                        ) : (
                                            pending_refunds.map((rf) => (
                                                <tr key={rf.id} className="hover:bg-slate-50/75">
                                                    <td className="px-4 py-3 font-mono">
                                                        <div className="font-semibold text-slate-900">
                                                            {rf.payment?.payment_number}
                                                        </div>
                                                        <div className="text-[11px] text-blue-600">
                                                            Booking: {rf.booking?.code}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="font-semibold text-slate-900">
                                                            {rf.booking?.customer?.name || '-'}
                                                        </div>
                                                        <div className="text-[11px] text-slate-400">
                                                            {rf.booking?.customer?.phone || '-'}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3 font-semibold text-slate-800">
                                                        {rf.requested_by?.name || 'Staf'}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-bold text-rose-600">
                                                        {formatIdr(rf.amount_idr)}
                                                    </td>
                                                    <td className="px-4 py-3 text-slate-700 max-w-xs truncate">
                                                        {rf.reason}
                                                    </td>
                                                    <td className="px-4 py-3 text-slate-500">
                                                        {formatDate(rf.created_at)}
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        {permissions.can_approve_refund ? (
                                                            <Button
                                                                size="sm"
                                                                variant="primary"
                                                                onClick={() => handleApproveRefund(rf.id)}
                                                                className="bg-emerald-600 hover:bg-emerald-700"
                                                            >
                                                                <Check className="mr-1 h-3.5 w-3.5" />
                                                                Setujui Refund
                                                            </Button>
                                                        ) : (
                                                            <span className="text-[11px] italic text-slate-400">
                                                                Hanya Owner
                                                            </span>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                )}

                {currentTab === 'settings' && (
                    <div className="space-y-6">
                        {/* Gateway Configuration Form */}
                        <form onSubmit={submitSettings} className="space-y-6">
                            <div className="rounded-xl border border-slate-200 bg-white p-6">
                                <h3 className="text-sm font-semibold text-slate-900 mb-1">
                                    Penyedia Layanan (Payment Provider) & Model
                                </h3>
                                <p className="text-xs text-slate-500 mb-4">
                                    Pilih payment gateway yang akan digunakan oleh pelanggan saat melakukan reservasi online.
                                </p>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                                            Default Provider
                                        </label>
                                        <select
                                            value={settingsForm.data.default_provider}
                                            onChange={(e) =>
                                                settingsForm.setData('default_provider', e.target.value)
                                            }
                                            className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-blue-600 focus:outline-none"
                                        >
                                            <option value="midtrans">Midtrans (Snap & QRIS)</option>
                                            <option value="xendit">Xendit (Invoice & VA)</option>
                                            <option value="manual">Manual / On-site Only (Kasir Tunai)</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                                            Model Pembayaran Pelanggan
                                        </label>
                                        <select
                                            value={settingsForm.data.default_model}
                                            onChange={(e) =>
                                                settingsForm.setData('default_model', e.target.value)
                                            }
                                            className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-blue-600 focus:outline-none"
                                        >
                                            <option value="no_payment">Tanpa Pembayaran Online (Bayar di Tempat)</option>
                                            <option value="deposit">Uang Muka / Deposit (DP)</option>
                                            <option value="full_payment">Bayar Penuh di Muka (100%)</option>
                                            <option value="partial_payment">Pembayaran Termin / Parsial</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                                            Persentase Deposit / DP (%)
                                        </label>
                                        <input
                                            type="number"
                                            min={5}
                                            max={100}
                                            value={settingsForm.data.deposit_percentage}
                                            onChange={(e) =>
                                                settingsForm.setData(
                                                    'deposit_percentage',
                                                    parseInt(e.target.value) || 30
                                                )
                                            }
                                            className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-blue-600 focus:outline-none"
                                        />
                                    </div>
                                </div>

                                <div className="mt-4 pt-4 border-t border-slate-100 flex items-center gap-3">
                                    <input
                                        type="checkbox"
                                        id="is_production"
                                        checked={settingsForm.data.is_production}
                                        onChange={(e) =>
                                            settingsForm.setData('is_production', e.target.checked)
                                        }
                                        className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <label htmlFor="is_production" className="text-xs font-semibold text-slate-800">
                                        Aktifkan Mode Produksi (Live). Bila tidak dicentang, sistem berjalan pada mode Sandbox (Uji Coba).
                                    </label>
                                </div>
                            </div>

                            {/* Midtrans Keys */}
                            <div className="rounded-xl border border-slate-200 bg-white p-6">
                                <div className="flex items-center justify-between mb-3">
                                    <div>
                                        <h3 className="text-sm font-semibold text-slate-900">
                                            Kredensial Midtrans
                                        </h3>
                                        <p className="text-xs text-slate-500">
                                            Dibutuhkan untuk memproses transaksi Snap QRIS, GoPay, ShopeePay, dan Virtual Account.
                                        </p>
                                    </div>
                                    <Badge variant={settings.has_midtrans_key ? 'active' : 'neutral'}>
                                        {settings.has_midtrans_key ? 'Terkonfigurasi' : 'Belum Ada Key'}
                                    </Badge>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                                            Server Key
                                        </label>
                                        <input
                                            type="password"
                                            placeholder={
                                                settings.has_midtrans_key
                                                    ? '•••••••••••••••••••• (Tersimpan)'
                                                    : 'SB-Mid-server-xxxx'
                                            }
                                            value={settingsForm.data.midtrans_server_key}
                                            onChange={(e) =>
                                                settingsForm.setData('midtrans_server_key', e.target.value)
                                            }
                                            className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 font-mono text-xs text-slate-800 focus:border-blue-600 focus:outline-none"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                                            Client Key
                                        </label>
                                        <input
                                            type="text"
                                            placeholder="SB-Mid-client-xxxx"
                                            value={settingsForm.data.midtrans_client_key}
                                            onChange={(e) =>
                                                settingsForm.setData('midtrans_client_key', e.target.value)
                                            }
                                            className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 font-mono text-xs text-slate-800 focus:border-blue-600 focus:outline-none"
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Xendit Keys */}
                            <div className="rounded-xl border border-slate-200 bg-white p-6">
                                <div className="flex items-center justify-between mb-3">
                                    <div>
                                        <h3 className="text-sm font-semibold text-slate-900">
                                            Kredensial Xendit
                                        </h3>
                                        <p className="text-xs text-slate-500">
                                            Dibutuhkan untuk integrasi Xendit Invoicing dan Callback Token verification.
                                        </p>
                                    </div>
                                    <Badge variant={settings.has_xendit_key ? 'active' : 'neutral'}>
                                        {settings.has_xendit_key ? 'Terkonfigurasi' : 'Belum Ada Key'}
                                    </Badge>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                                            Secret API Key
                                        </label>
                                        <input
                                            type="password"
                                            placeholder={
                                                settings.has_xendit_key
                                                    ? '•••••••••••••••••••• (Tersimpan)'
                                                    : 'xnd_development_xxxx'
                                            }
                                            value={settingsForm.data.xendit_secret_key}
                                            onChange={(e) =>
                                                settingsForm.setData('xendit_secret_key', e.target.value)
                                            }
                                            className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 font-mono text-xs text-slate-800 focus:border-blue-600 focus:outline-none"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                                            Public Key
                                        </label>
                                        <input
                                            type="text"
                                            placeholder="xnd_public_xxxx"
                                            value={settingsForm.data.xendit_public_key}
                                            onChange={(e) =>
                                                settingsForm.setData('xendit_public_key', e.target.value)
                                            }
                                            className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 font-mono text-xs text-slate-800 focus:border-blue-600 focus:outline-none"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                                            Callback Verification Token
                                        </label>
                                        <input
                                            type="text"
                                            placeholder="Token x-callback-token"
                                            value={settingsForm.data.xendit_webhook_token}
                                            onChange={(e) =>
                                                settingsForm.setData('xendit_webhook_token', e.target.value)
                                            }
                                            className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 font-mono text-xs text-slate-800 focus:border-blue-600 focus:outline-none"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="flex justify-end">
                                <Button
                                    type="submit"
                                    size="md"
                                    variant="primary"
                                    isLoading={settingsForm.processing}
                                >
                                    Simpan Pengaturan Gateway
                                </Button>
                            </div>
                        </form>

                        {/* Webhook Endpoints & Step-by-Step Provider Guide */}
                        <div className="rounded-xl border border-slate-200 bg-white p-6 space-y-4">
                            <h3 className="text-sm font-semibold text-slate-900 flex items-center gap-2">
                                <Key className="h-4 w-4 text-blue-600" />
                                <span>URL Webhook & Panduan Dashboard Provider</span>
                            </h3>
                            <p className="text-xs text-slate-500">
                                Salin URL webhook di bawah ini dan tempelkan pada konfigurasi notifikasi URL di dashboard Midtrans atau Xendit agar sistem dapat mengonfirmasi pembayaran secara real-time.
                            </p>

                            <div className="space-y-3">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 mb-1">
                                        Midtrans Notification URL (Webhook)
                                    </label>
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="text"
                                            readOnly
                                            value={midtransWebhookUrl}
                                            className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-800 select-all"
                                        />
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="secondary"
                                            onClick={() => handleCopy(midtransWebhookUrl, 'midtrans')}
                                        >
                                            {copiedUrl === 'midtrans' ? (
                                                <Check className="h-3.5 w-3.5 text-emerald-600" />
                                            ) : (
                                                <Copy className="h-3.5 w-3.5" />
                                            )}
                                        </Button>
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 mb-1">
                                        Xendit Callback URL (Invoice Webhook)
                                    </label>
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="text"
                                            readOnly
                                            value={xenditWebhookUrl}
                                            className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-800 select-all"
                                        />
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="secondary"
                                            onClick={() => handleCopy(xenditWebhookUrl, 'xendit')}
                                        >
                                            {copiedUrl === 'xendit' ? (
                                                <Check className="h-3.5 w-3.5 text-emerald-600" />
                                            ) : (
                                                <Copy className="h-3.5 w-3.5" />
                                            )}
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            {/* Checklist Instructions */}
                            <div className="mt-4 pt-4 border-t border-slate-100 grid grid-cols-1 gap-4 sm:grid-cols-2 text-xs">
                                <div className="rounded-lg bg-slate-50 p-4 border border-slate-100">
                                    <div className="font-semibold text-slate-900 mb-2">
                                        Langkah di Dashboard Midtrans (MAP):
                                    </div>
                                    <ol className="list-decimal list-inside space-y-1.5 text-slate-600">
                                        <li>Buka <strong>dashboard.midtrans.com</strong> dan login.</li>
                                        <li>Buka menu <strong>Settings &gt; Configuration</strong>.</li>
                                        <li>Paste URL ke <strong>Payment Notification URL</strong>.</li>
                                        <li>Salin <strong>Server Key</strong> &amp; <strong>Client Key</strong> ke formulir di atas.</li>
                                        <li>Pastikan metode <strong>QRIS</strong> dan VA telah aktif.</li>
                                    </ol>
                                </div>

                                <div className="rounded-lg bg-slate-50 p-4 border border-slate-100">
                                    <div className="font-semibold text-slate-900 mb-2">
                                        Langkah di Dashboard Xendit:
                                    </div>
                                    <ol className="list-decimal list-inside space-y-1.5 text-slate-600">
                                        <li>Buka <strong>dashboard.xendit.co</strong> dan login.</li>
                                        <li>Buka menu <strong>Settings &gt; Callbacks</strong>.</li>
                                        <li>Centang <strong>Invoice Callbacks</strong> dan masukkan URL webhook.</li>
                                        <li>Salin <strong>Verification Token</strong> ke kolom token di atas.</li>
                                        <li>Buka <strong>API Keys</strong> dan salin Secret Key Anda.</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* Modal: Catat Pembayaran Manual (Kasir) */}
            <Modal
                isOpen={manualModalOpen}
                onClose={() => setManualModalOpen(false)}
                title="Catat Pembayaran Kasir / Manual"
                size="md"
            >
                {selectedInvoice && (
                    <form onSubmit={submitManualPayment} className="space-y-4">
                        <div className="rounded-lg bg-slate-50 p-3 border border-slate-200 text-xs">
                            <div className="flex justify-between">
                                <span className="text-slate-500">Invoice:</span>
                                <span className="font-mono font-semibold text-slate-900">
                                    {selectedInvoice.invoice_number}
                                </span>
                            </div>
                            <div className="flex justify-between mt-1">
                                <span className="text-slate-500">Booking:</span>
                                <span className="font-mono text-blue-600 font-semibold">
                                    {selectedInvoice.booking?.code}
                                </span>
                            </div>
                            <div className="flex justify-between mt-1">
                                <span className="text-slate-500">Pelanggan:</span>
                                <span className="font-semibold text-slate-800">
                                    {selectedInvoice.booking?.customer?.name || 'Walk-in'}
                                </span>
                            </div>
                            <div className="flex justify-between mt-1">
                                <span className="text-slate-500">Sisa Tagihan:</span>
                                <span className="font-bold text-slate-900">
                                    {formatIdr(
                                        Math.max(
                                            0,
                                            selectedInvoice.amount_due_idr - selectedInvoice.amount_paid_idr
                                        )
                                    )}
                                </span>
                            </div>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1">
                                Nominal Diterima (Rp)
                            </label>
                            <input
                                type="number"
                                min={1000}
                                value={manualForm.data.amount_idr}
                                onChange={(e) =>
                                    manualForm.setData('amount_idr', parseInt(e.target.value) || 0)
                                }
                                className="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs font-mono font-semibold text-slate-900 focus:border-blue-600 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1">
                                Metode Pembayaran
                            </label>
                            <select
                                value={manualForm.data.payment_method}
                                onChange={(e) =>
                                    manualForm.setData('payment_method', e.target.value)
                                }
                                className="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:border-blue-600 focus:outline-none"
                            >
                                <option value="cash">Tunai / Cash di Kasir</option>
                                <option value="bank_transfer">Transfer Bank Langsung</option>
                                <option value="qris">QRIS Statis / Venue QRIS</option>
                                <option value="edc">Mesin EDC Kartu Debit / Kredit</option>
                                <option value="other">Metode Lainnya</option>
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1">
                                Catatan Kasir (Opsional)
                            </label>
                            <input
                                type="text"
                                placeholder="Contoh: No. struk EDC 88471"
                                value={manualForm.data.notes}
                                onChange={(e) => manualForm.setData('notes', e.target.value)}
                                className="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:border-blue-600 focus:outline-none"
                            />
                        </div>

                        <div className="flex justify-end gap-2 pt-2 border-t border-slate-100">
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                onClick={() => setManualModalOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                variant="primary"
                                size="sm"
                                isLoading={manualForm.processing}
                            >
                                Konfirmasi Pembayaran Kasir
                            </Button>
                        </div>
                    </form>
                )}
            </Modal>

            {/* Modal: Ajukan Refund */}
            <Modal
                isOpen={refundModalOpen}
                onClose={() => setRefundModalOpen(false)}
                title="Ajukan Pengembalian Dana (Refund)"
                size="md"
            >
                {selectedPayment && (
                    <form onSubmit={submitRefund} className="space-y-4">
                        <div className="rounded-lg bg-rose-50 p-3 border border-rose-200 text-xs">
                            <div className="flex justify-between">
                                <span className="text-slate-500">No. Pembayaran:</span>
                                <span className="font-mono font-semibold text-slate-900">
                                    {selectedPayment.payment_number}
                                </span>
                            </div>
                            <div className="flex justify-between mt-1">
                                <span className="text-slate-500">Booking:</span>
                                <span className="font-mono text-blue-600 font-semibold">
                                    {selectedPayment.booking?.code}
                                </span>
                            </div>
                            <div className="flex justify-between mt-1">
                                <span className="text-slate-500">Maksimal Refund:</span>
                                <span className="font-bold text-rose-700">
                                    {formatIdr(selectedPayment.amount_idr)}
                                </span>
                            </div>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1">
                                Nominal Refund (Rp)
                            </label>
                            <input
                                type="number"
                                min={1000}
                                max={selectedPayment.amount_idr}
                                value={refundForm.data.amount_idr}
                                onChange={(e) =>
                                    refundForm.setData('amount_idr', parseInt(e.target.value) || 0)
                                }
                                className="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs font-mono font-semibold text-slate-900 focus:border-blue-600 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1">
                                Alasan Pengembalian Dana
                            </label>
                            <textarea
                                required
                                rows={3}
                                placeholder="Jelaskan alasan pengembalian dana (cth: Layanan dibatalkan karena staf berhalangan)"
                                value={refundForm.data.reason}
                                onChange={(e) => refundForm.setData('reason', e.target.value)}
                                className="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:border-blue-600 focus:outline-none"
                            />
                        </div>

                        <div className="flex justify-end gap-2 pt-2 border-t border-slate-100">
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                onClick={() => setRefundModalOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                variant="danger"
                                size="sm"
                                isLoading={refundForm.processing}
                            >
                                Kirim Permintaan Refund
                            </Button>
                        </div>
                    </form>
                )}
            </Modal>
        </OwnerLayout>
    );
}
