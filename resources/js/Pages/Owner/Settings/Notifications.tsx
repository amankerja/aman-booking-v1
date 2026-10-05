import { router, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    Bell,
    CheckCircle2,
    Clock,
    Edit3,
    Filter,
    Mail,
    MessageSquare,
    RefreshCw,
    RotateCw,
    Search,
    Send,
} from 'lucide-react';
import React, { useState } from 'react';
import {
    Button,
    Input,
    Modal,
    Tabs,
    Textarea,
    useToast,
} from '../../../Components/ui';
import { OwnerLayout } from '../../../Layouts/OwnerLayout';

interface TemplateItem {
    id: number;
    tenant_id: number;
    event: string;
    channel: 'EMAIL' | 'WHATSAPP';
    name: string;
    subject: string | null;
    body: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

interface LogItem {
    id: number;
    tenant_id: number;
    booking_id: number | null;
    channel: 'EMAIL' | 'WHATSAPP';
    event: string;
    recipient: string;
    subject: string | null;
    body: string;
    status: 'PENDING' | 'SENT' | 'FAILED' | 'DEAD_LETTER';
    attempts: number;
    max_attempts: number;
    last_error: string | null;
    next_retry_at: string | null;
    sent_at: string | null;
    created_at: string;
    booking?: {
        id: number;
        code: string;
        start_at: string;
    } | null;
}

interface PaginatedLogs {
    data: LogItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface VariableHelper {
    name: string;
    description: string;
}

interface NotificationsPageProps {
    templates: TemplateItem[];
    logs: PaginatedLogs;
    counts: {
        total: number;
        sent: number;
        failed: number;
        dead_letter: number;
    };
    filters: {
        status?: string;
        channel?: string;
        search?: string;
    };
    availableVariables: VariableHelper[];
}

export default function Notifications({
    templates,
    logs,
    counts,
    filters,
    availableVariables,
}: NotificationsPageProps) {
    const toast = useToast();
    const [activeTab, setActiveTab] = useState<'templates' | 'logs'>(
        'templates'
    );

    // Filter states for logs tab
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [channelFilter, setChannelFilter] = useState(filters.channel || '');

    // Edit Template Modal State
    const [editingTemplate, setEditingTemplate] = useState<TemplateItem | null>(
        null
    );
    const {
        data: formData,
        setData: setFormData,
        put: putTemplate,
        processing: formProcessing,
        reset: resetForm,
    } = useForm({
        name: '',
        subject: '',
        body: '',
        is_active: true,
    });

    // View Log Detail Modal State
    const [selectedLog, setSelectedLog] = useState<LogItem | null>(null);

    const openEditModal = (tpl: TemplateItem) => {
        setEditingTemplate(tpl);
        setFormData({
            name: tpl.name,
            subject: tpl.subject || '',
            body: tpl.body,
            is_active: tpl.is_active,
        });
    };

    const handleSaveTemplate = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingTemplate) return;

        putTemplate(
            `/app/settings/notifications/templates/${editingTemplate.id}`,
            {
                onSuccess: () => {
                    toast.success('Template notifikasi berhasil diperbarui');
                    setEditingTemplate(null);
                    resetForm();
                },
                onError: () => {
                    toast.error(
                        'Gagal memperbarui template. Silakan periksa data input.'
                    );
                },
            }
        );
    };

    const handleResetDefault = (templateId: number) => {
        if (
            !confirm(
                'Apakah Anda yakin ingin mengembalikan template ini ke pengaturan standar bawaan sistem?'
            )
        ) {
            return;
        }

        router.post(
            `/app/settings/notifications/templates/${templateId}/reset`,
            {},
            {
                onSuccess: () => {
                    toast.success(
                        'Template berhasil dikembalikan ke standar awal.'
                    );
                    if (editingTemplate?.id === templateId) {
                        setEditingTemplate(null);
                    }
                },
                onError: () => {
                    toast.error('Gagal me-reset template.');
                },
            }
        );
    };

    const handleRetryNotification = (logId: number) => {
        router.post(
            `/app/settings/notifications/logs/${logId}/retry`,
            {},
            {
                onSuccess: () => {
                    toast.success('Pengiriman ulang dijadwalkan ke antrean.');
                    if (selectedLog?.id === logId) {
                        setSelectedLog(null);
                    }
                },
                onError: () => {
                    toast.error('Gagal menjadwalkan ulang notifikasi.');
                },
            }
        );
    };

    const applyLogFilters = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        router.get(
            '/app/settings/notifications',
            {
                search: search || undefined,
                status: statusFilter || undefined,
                channel: channelFilter || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    const handleInsertVariable = (varName: string) => {
        setFormData('body', formData.body + ' ' + varName);
        toast.info(`Variabel ${varName} disisipkan ke pesan.`);
    };

    const renderStatusBadge = (status: LogItem['status']) => {
        switch (status) {
            case 'SENT':
                return (
                    <span className="inline-flex items-center gap-1 rounded-full bg-[#dcfce7] px-2.5 py-0.5 text-xs font-semibold text-[#15803d]">
                        <CheckCircle2 className="h-3 w-3" />
                        Terkirim
                    </span>
                );
            case 'PENDING':
                return (
                    <span className="inline-flex items-center gap-1 rounded-full bg-[#fef9c3] px-2.5 py-0.5 text-xs font-semibold text-[#854d0e]">
                        <Clock className="h-3 w-3" />
                        Antrean
                    </span>
                );
            case 'FAILED':
                return (
                    <span className="inline-flex items-center gap-1 rounded-full bg-[#fee2e2] px-2.5 py-0.5 text-xs font-semibold text-[#b91c1c]">
                        <AlertCircle className="h-3 w-3" />
                        Gagal (Antre Ulang)
                    </span>
                );
            case 'DEAD_LETTER':
                return (
                    <span className="inline-flex items-center gap-1 rounded-full border border-red-300 bg-[#fee2e2] px-2.5 py-0.5 text-xs font-semibold text-[#991b1b]">
                        <AlertTriangle className="h-3 w-3" />
                        Dead Letter (5x Gagal)
                    </span>
                );
            default:
                return <span className="text-xs text-slate-500">{status}</span>;
        }
    };

    return (
        <OwnerLayout
            title="Pengaturan Notifikasi"
            breadcrumbs={[
                { label: 'Dashboard', href: '/app/dashboard' },
                { label: 'Pengaturan' },
                { label: 'Notifikasi' },
            ]}
        >
            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight text-[#0f172a]">
                            Notifikasi & Log Pengiriman
                        </h1>
                        <p className="mt-1 text-sm text-[#64748b]">
                            Kelola template pesan otomatis (Email & WhatsApp),
                            pantau antrean pengiriman, dan tangani notifikasi
                            Dead Letter.
                        </p>
                    </div>
                </div>

                {/* Metrics Cards */}
                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div className="rounded-[12px] border border-[#e2e8f0] bg-white p-4">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-[#64748b]">
                                Total Notifikasi
                            </span>
                            <Send className="h-4 w-4 text-[#64748b]" />
                        </div>
                        <div className="mt-2 font-mono text-2xl font-bold text-[#0f172a]">
                            {counts.total.toLocaleString()}
                        </div>
                    </div>

                    <div className="rounded-[12px] border border-[#e2e8f0] bg-white p-4">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-[#64748b]">
                                Berhasil Terkirim
                            </span>
                            <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                        </div>
                        <div className="mt-2 font-mono text-2xl font-bold text-emerald-600">
                            {counts.sent.toLocaleString()}
                        </div>
                    </div>

                    <div className="rounded-[12px] border border-[#e2e8f0] bg-white p-4">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-[#64748b]">
                                Dalam Antrean Ulang
                            </span>
                            <RefreshCw className="h-4 w-4 text-amber-600" />
                        </div>
                        <div className="mt-2 font-mono text-2xl font-bold text-amber-600">
                            {counts.failed.toLocaleString()}
                        </div>
                    </div>

                    <div className="rounded-[12px] border border-[#e2e8f0] bg-white p-4">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-[#64748b]">
                                Dead Letter Queue
                            </span>
                            <AlertTriangle className="h-4 w-4 text-red-600" />
                        </div>
                        <div className="mt-2 font-mono text-2xl font-bold text-red-600">
                            {counts.dead_letter.toLocaleString()}
                        </div>
                    </div>
                </div>

                {/* Tabs Navigation */}
                <Tabs
                    tabs={[
                        {
                            id: 'templates',
                            label: 'Template Pesan',
                            icon: <Bell className="h-4 w-4" />,
                            badge: templates.length,
                        },
                        {
                            id: 'logs',
                            label: 'Riwayat & Dead Letter Queue',
                            icon: <RotateCw className="h-4 w-4" />,
                            badge:
                                counts.dead_letter > 0
                                    ? `${counts.dead_letter} Dead Letter`
                                    : undefined,
                        },
                    ]}
                    activeTab={activeTab}
                    onChange={(id) => setActiveTab(id as 'templates' | 'logs')}
                />

                {/* Tab 1: Notification Templates */}
                {activeTab === 'templates' && (
                    <div className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            {templates.map((tpl) => (
                                <div
                                    key={tpl.id}
                                    className="flex flex-col justify-between rounded-[12px] border border-[#e2e8f0] bg-white p-5"
                                >
                                    <div>
                                        <div className="mb-3 flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                {tpl.channel === 'EMAIL' ? (
                                                    <span className="rounded-lg bg-blue-50 p-1.5 text-[#2563eb]">
                                                        <Mail className="h-4 w-4" />
                                                    </span>
                                                ) : (
                                                    <span className="rounded-lg bg-emerald-50 p-1.5 text-emerald-600">
                                                        <MessageSquare className="h-4 w-4" />
                                                    </span>
                                                )}
                                                <span className="text-sm font-semibold text-[#0f172a]">
                                                    {tpl.name}
                                                </span>
                                            </div>
                                            <span
                                                className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${
                                                    tpl.is_active
                                                        ? 'bg-emerald-50 text-emerald-700'
                                                        : 'bg-slate-100 text-slate-500'
                                                }`}
                                            >
                                                {tpl.is_active
                                                    ? 'Aktif'
                                                    : 'Nonaktif'}
                                            </span>
                                        </div>

                                        {tpl.subject && (
                                            <div className="mb-2 text-xs font-medium text-[#64748b]">
                                                <span className="text-slate-400">
                                                    Subjek:{' '}
                                                </span>
                                                {tpl.subject}
                                            </div>
                                        )}

                                        <div className="line-clamp-4 rounded-lg border border-[#f1f5f9] bg-[#f8fafc] p-3 font-mono text-xs leading-relaxed whitespace-pre-wrap text-[#0f172a]">
                                            {tpl.body}
                                        </div>
                                    </div>

                                    <div className="mt-4 flex items-center justify-between border-t border-[#f1f5f9] pt-4">
                                        <span className="font-mono text-[11px] text-[#64748b]">
                                            Event: {tpl.event}
                                        </span>
                                        <div className="flex items-center gap-2">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    handleResetDefault(tpl.id)
                                                }
                                                className="rounded px-2 py-1 text-xs text-[#64748b] transition-colors hover:text-slate-900"
                                                title="Reset ke template standar bawaan"
                                            >
                                                Reset
                                            </button>
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                onClick={() =>
                                                    openEditModal(tpl)
                                                }
                                                className="gap-1.5 text-xs"
                                            >
                                                <Edit3 className="h-3.5 w-3.5" />
                                                Ubah Template
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Tab 2: Notification Logs & Dead Letter */}
                {activeTab === 'logs' && (
                    <div className="space-y-4">
                        {/* Filters toolbar */}
                        <div className="flex flex-col items-stretch justify-between gap-3 rounded-[12px] border border-[#e2e8f0] bg-white p-4 md:flex-row md:items-center">
                            <form
                                onSubmit={applyLogFilters}
                                className="flex flex-1 flex-col items-stretch gap-3 sm:flex-row sm:items-center"
                            >
                                <div className="relative flex-1">
                                    <Search className="absolute top-2.5 left-3 h-4 w-4 text-[#64748b]" />
                                    <input
                                        type="text"
                                        placeholder="Cari penerima, subjek, atau kode booking..."
                                        value={search}
                                        onChange={(e) =>
                                            setSearch(e.target.value)
                                        }
                                        className="w-full rounded-[8px] border border-[#e2e8f0] bg-white py-1.5 pr-3 pl-9 text-xs focus:ring-1 focus:ring-[#2563eb] focus:outline-none"
                                    />
                                </div>

                                <select
                                    value={statusFilter}
                                    onChange={(e) =>
                                        setStatusFilter(e.target.value)
                                    }
                                    className="rounded-[8px] border border-[#e2e8f0] bg-white px-3 py-1.5 text-xs focus:ring-1 focus:ring-[#2563eb] focus:outline-none"
                                >
                                    <option value="">Semua Status</option>
                                    <option value="SENT">
                                        Terkirim (SENT)
                                    </option>
                                    <option value="PENDING">
                                        Antrean (PENDING)
                                    </option>
                                    <option value="FAILED">
                                        Gagal Ulang (FAILED)
                                    </option>
                                    <option value="DEAD_LETTER">
                                        Dead Letter (DEAD_LETTER)
                                    </option>
                                </select>

                                <select
                                    value={channelFilter}
                                    onChange={(e) =>
                                        setChannelFilter(e.target.value)
                                    }
                                    className="rounded-[8px] border border-[#e2e8f0] bg-white px-3 py-1.5 text-xs focus:ring-1 focus:ring-[#2563eb] focus:outline-none"
                                >
                                    <option value="">Semua Kanal</option>
                                    <option value="EMAIL">Email</option>
                                    <option value="WHATSAPP">WhatsApp</option>
                                </select>

                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="secondary"
                                    className="gap-1.5 text-xs"
                                >
                                    <Filter className="h-3.5 w-3.5" />
                                    Terapkan
                                </Button>
                            </form>
                        </div>

                        {/* Logs Table */}
                        <div className="overflow-hidden rounded-[12px] border border-[#e2e8f0] bg-white">
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead className="border-b border-[#e2e8f0] bg-[#f8fafc] font-medium text-[#64748b]">
                                        <tr>
                                            <th className="px-4 py-3">
                                                Penerima & Kanal
                                            </th>
                                            <th className="px-4 py-3">
                                                Event & Booking
                                            </th>
                                            <th className="px-4 py-3">
                                                Status & Percobaan
                                            </th>
                                            <th className="px-4 py-3">
                                                Waktu / Jadwal Ulang
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Aksi
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[#f1f5f9]">
                                        {logs.data.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={5}
                                                    className="py-12 text-center text-[#64748b]"
                                                >
                                                    Tidak ada catatan log
                                                    notifikasi yang sesuai
                                                    filter.
                                                </td>
                                            </tr>
                                        ) : (
                                            logs.data.map((log) => (
                                                <tr
                                                    key={log.id}
                                                    className="transition-colors hover:bg-slate-50"
                                                >
                                                    <td className="px-4 py-3">
                                                        <div className="flex items-center gap-2">
                                                            {log.channel ===
                                                            'EMAIL' ? (
                                                                <span className="rounded bg-blue-50 p-1 text-[#2563eb]">
                                                                    <Mail className="h-3.5 w-3.5" />
                                                                </span>
                                                            ) : (
                                                                <span className="rounded bg-emerald-50 p-1 text-emerald-600">
                                                                    <MessageSquare className="h-3.5 w-3.5" />
                                                                </span>
                                                            )}
                                                            <div>
                                                                <div className="font-semibold text-[#0f172a]">
                                                                    {
                                                                        log.recipient
                                                                    }
                                                                </div>
                                                                <div className="max-w-xs truncate text-[11px] text-[#64748b]">
                                                                    {log.subject ||
                                                                        log.body.substring(
                                                                            0,
                                                                            45
                                                                        ) +
                                                                            '...'}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td className="px-4 py-3">
                                                        <div className="font-mono text-xs font-semibold text-[#0f172a]">
                                                            {log.event}
                                                        </div>
                                                        {log.booking && (
                                                            <div className="font-mono text-[11px] text-[#2563eb]">
                                                                {
                                                                    log.booking
                                                                        .code
                                                                }
                                                            </div>
                                                        )}
                                                    </td>

                                                    <td className="px-4 py-3">
                                                        <div className="flex items-center gap-2">
                                                            {renderStatusBadge(
                                                                log.status
                                                            )}
                                                            <span className="font-mono text-[11px] text-[#64748b]">
                                                                ({log.attempts}/
                                                                {
                                                                    log.max_attempts
                                                                }
                                                                )
                                                            </span>
                                                        </div>
                                                        {log.last_error && (
                                                            <div
                                                                className="mt-1 max-w-xs truncate text-[11px] text-red-600"
                                                                title={
                                                                    log.last_error
                                                                }
                                                            >
                                                                Err:{' '}
                                                                {log.last_error}
                                                            </div>
                                                        )}
                                                    </td>

                                                    <td className="px-4 py-3 font-mono text-[11px] text-[#64748b]">
                                                        {log.sent_at ? (
                                                            <div>
                                                                Terkirim:{' '}
                                                                {new Date(
                                                                    log.sent_at
                                                                ).toLocaleString(
                                                                    'id-ID'
                                                                )}
                                                            </div>
                                                        ) : log.next_retry_at ? (
                                                            <div className="font-semibold text-amber-600">
                                                                Retry:{' '}
                                                                {new Date(
                                                                    log.next_retry_at
                                                                ).toLocaleTimeString(
                                                                    'id-ID'
                                                                )}
                                                            </div>
                                                        ) : (
                                                            <div>
                                                                Dibuat:{' '}
                                                                {new Date(
                                                                    log.created_at
                                                                ).toLocaleString(
                                                                    'id-ID'
                                                                )}
                                                            </div>
                                                        )}
                                                    </td>

                                                    <td className="px-4 py-3 text-right">
                                                        <div className="flex items-center justify-end gap-2">
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    setSelectedLog(
                                                                        log
                                                                    )
                                                                }
                                                                className="text-xs text-[#2563eb] hover:underline"
                                                            >
                                                                Detail
                                                            </button>

                                                            {(log.status ===
                                                                'FAILED' ||
                                                                log.status ===
                                                                    'DEAD_LETTER') && (
                                                                <Button
                                                                    size="sm"
                                                                    variant="secondary"
                                                                    onClick={() =>
                                                                        handleRetryNotification(
                                                                            log.id
                                                                        )
                                                                    }
                                                                    className="gap-1 border-red-200 bg-red-50 px-2 py-1 text-[11px] text-red-700 hover:bg-red-100"
                                                                >
                                                                    <RotateCw className="h-3 w-3" />
                                                                    Retry
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {/* Pagination */}
                            {logs.last_page > 1 && (
                                <div className="flex items-center justify-between border-t border-[#e2e8f0] p-3 text-xs text-[#64748b]">
                                    <span>
                                        Halaman {logs.current_page} dari{' '}
                                        {logs.last_page} (Total {logs.total}{' '}
                                        log)
                                    </span>
                                    <div className="flex gap-1">
                                        {logs.prev_page_url && (
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                onClick={() =>
                                                    router.get(
                                                        logs.prev_page_url!
                                                    )
                                                }
                                            >
                                                Sebelumnya
                                            </Button>
                                        )}
                                        {logs.next_page_url && (
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                onClick={() =>
                                                    router.get(
                                                        logs.next_page_url!
                                                    )
                                                }
                                            >
                                                Selanjutnya
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {/* Modal Edit Template */}
            {editingTemplate && (
                <Modal
                    isOpen={!!editingTemplate}
                    onClose={() => setEditingTemplate(null)}
                    title={`Ubah Template: ${editingTemplate.name}`}
                    size="xl"
                >
                    <form onSubmit={handleSaveTemplate} className="space-y-4">
                        <div>
                            <label className="mb-1 block text-xs font-semibold text-[#0f172a]">
                                Nama Template
                            </label>
                            <Input
                                value={formData.name}
                                onChange={(e) =>
                                    setFormData('name', e.target.value)
                                }
                                required
                            />
                        </div>

                        {editingTemplate.channel === 'EMAIL' && (
                            <div>
                                <label className="mb-1 block text-xs font-semibold text-[#0f172a]">
                                    Subjek Email
                                </label>
                                <Input
                                    value={formData.subject}
                                    onChange={(e) =>
                                        setFormData('subject', e.target.value)
                                    }
                                    placeholder="Contoh: Reservasi Anda di {{business.name}} [{{booking.code}}]"
                                    required
                                />
                            </div>
                        )}

                        <div>
                            <div className="mb-1 flex items-center justify-between">
                                <label className="block text-xs font-semibold text-[#0f172a]">
                                    Isi Pesan (Body Template)
                                </label>
                                <span className="text-[11px] text-[#64748b]">
                                    Gunakan tag variabel di bawah ini
                                </span>
                            </div>
                            <Textarea
                                rows={8}
                                value={formData.body}
                                onChange={(e) =>
                                    setFormData('body', e.target.value)
                                }
                                className="font-mono text-xs leading-relaxed"
                                required
                            />
                        </div>

                        {/* Variable Helper Chips */}
                        <div className="rounded-[8px] border border-[#e2e8f0] bg-[#f8fafc] p-3">
                            <span className="mb-2 block text-xs font-semibold text-[#0f172a]">
                                Klik untuk menyisipkan variabel ke pesan:
                            </span>
                            <div className="flex flex-wrap gap-1.5">
                                {availableVariables.map((v) => (
                                    <button
                                        key={v.name}
                                        type="button"
                                        onClick={() =>
                                            handleInsertVariable(v.name)
                                        }
                                        className="inline-flex items-center gap-1 rounded border border-[#e2e8f0] bg-white px-2 py-1 font-mono text-[11px] text-[#2563eb] transition-colors hover:bg-blue-50"
                                        title={v.description}
                                    >
                                        <span>{v.name}</span>
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="flex items-center gap-2 pt-2">
                            <input
                                type="checkbox"
                                id="is_active"
                                checked={formData.is_active}
                                onChange={(e) =>
                                    setFormData('is_active', e.target.checked)
                                }
                                className="rounded text-[#2563eb] focus:ring-[#2563eb]"
                            />
                            <label
                                htmlFor="is_active"
                                className="cursor-pointer text-xs font-medium text-[#0f172a]"
                            >
                                Aktifkan template ini untuk pengiriman otomatis
                            </label>
                        </div>

                        <div className="flex items-center justify-end gap-2 border-t border-[#e2e8f0] pt-4">
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => setEditingTemplate(null)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                variant="primary"
                                disabled={formProcessing}
                            >
                                {formProcessing
                                    ? 'Menyimpan...'
                                    : 'Simpan Perubahan'}
                            </Button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* Modal Detail Log */}
            {selectedLog && (
                <Modal
                    isOpen={!!selectedLog}
                    onClose={() => setSelectedLog(null)}
                    title={`Detail Notifikasi #${selectedLog.id}`}
                    size="lg"
                >
                    <div className="space-y-4 text-xs">
                        <div className="grid grid-cols-2 gap-3 rounded-[8px] border border-[#e2e8f0] bg-[#f8fafc] p-3">
                            <div>
                                <span className="block text-[#64748b]">
                                    Kanal:
                                </span>
                                <span className="font-semibold text-[#0f172a]">
                                    {selectedLog.channel}
                                </span>
                            </div>
                            <div>
                                <span className="block text-[#64748b]">
                                    Penerima:
                                </span>
                                <span className="font-semibold text-[#0f172a]">
                                    {selectedLog.recipient}
                                </span>
                            </div>
                            <div>
                                <span className="block text-[#64748b]">
                                    Event:
                                </span>
                                <span className="font-mono text-[#0f172a]">
                                    {selectedLog.event}
                                </span>
                            </div>
                            <div>
                                <span className="block text-[#64748b]">
                                    Status:
                                </span>
                                <div className="mt-0.5">
                                    {renderStatusBadge(selectedLog.status)}
                                </div>
                            </div>
                            <div>
                                <span className="block text-[#64748b]">
                                    Percobaan Pengiriman:
                                </span>
                                <span className="font-mono text-[#0f172a]">
                                    {selectedLog.attempts} /{' '}
                                    {selectedLog.max_attempts}
                                </span>
                            </div>
                            <div>
                                <span className="block text-[#64748b]">
                                    Kode Booking:
                                </span>
                                <span className="font-mono text-[#2563eb]">
                                    {selectedLog.booking?.code || '-'}
                                </span>
                            </div>
                        </div>

                        {selectedLog.subject && (
                            <div>
                                <span className="mb-1 block font-medium text-[#64748b]">
                                    Subjek:
                                </span>
                                <div className="rounded border border-slate-200 bg-slate-50 p-2 font-medium text-[#0f172a]">
                                    {selectedLog.subject}
                                </div>
                            </div>
                        )}

                        <div>
                            <span className="mb-1 block font-medium text-[#64748b]">
                                Konten Pesan:
                            </span>
                            <div className="max-h-60 overflow-y-auto rounded border border-slate-200 bg-slate-50 p-3 font-mono leading-relaxed whitespace-pre-wrap text-[#0f172a]">
                                {selectedLog.body}
                            </div>
                        </div>

                        {selectedLog.last_error && (
                            <div>
                                <span className="mb-1 block font-semibold text-red-600">
                                    Pesan Kesalahan Terakhir:
                                </span>
                                <div className="rounded border border-red-200 bg-red-50 p-2.5 font-mono text-[11px] whitespace-pre-wrap text-red-800">
                                    {selectedLog.last_error}
                                </div>
                            </div>
                        )}

                        <div className="flex items-center justify-between border-t border-[#e2e8f0] pt-4">
                            {(selectedLog.status === 'FAILED' ||
                                selectedLog.status === 'DEAD_LETTER') && (
                                <Button
                                    variant="secondary"
                                    onClick={() =>
                                        handleRetryNotification(selectedLog.id)
                                    }
                                    className="gap-1.5 border-red-200 bg-red-50 text-xs text-red-700 hover:bg-red-100"
                                >
                                    <RotateCw className="h-3.5 w-3.5" />
                                    Jadwalkan Ulang Sekarang (Manual Retry)
                                </Button>
                            )}
                            <div className="ml-auto">
                                <Button
                                    variant="secondary"
                                    onClick={() => setSelectedLog(null)}
                                >
                                    Tutup
                                </Button>
                            </div>
                        </div>
                    </div>
                </Modal>
            )}
        </OwnerLayout>
    );
}
