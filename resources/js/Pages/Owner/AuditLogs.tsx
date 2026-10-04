import { Link, router } from '@inertiajs/react';
import {
    Activity,
    Clock,
    Filter,
    RotateCcw,
    Shield,
    User as UserIcon,
} from 'lucide-react';
import React, { useState } from 'react';
import { OwnerLayout } from '../../Layouts/OwnerLayout';

interface AuditLogItem {
    id: number;
    tenant_id: number;
    actor_id: number | null;
    actor_type: string | null;
    actor_role: string | null;
    action: string;
    entity_type: string;
    entity_id: string;
    before: Record<string, unknown> | null;
    after: Record<string, unknown> | null;
    source: string;
    ip: string | null;
    created_at: string;
}

interface AuditLogsProps {
    logs: {
        data: AuditLogItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        action: string;
        date_from: string;
        date_to: string;
    };
    availableActions: Record<string, string>;
}

export default function AuditLogs({
    logs,
    filters,
    availableActions,
}: AuditLogsProps) {
    const [action, setAction] = useState(filters.action || '');
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');
    const [expandedLogId, setExpandedLogId] = useState<number | null>(null);

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/app/audit-logs',
            {
                action: action || undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
            },
            { preserveState: true }
        );
    };

    const handleReset = () => {
        setAction('');
        setDateFrom('');
        setDateTo('');
        router.get('/app/audit-logs');
    };

    const getActionBadgeClass = (act: string) => {
        if (act.includes('created') || act.includes('login')) {
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        }
        if (act.includes('deleted') || act.includes('logout')) {
            return 'bg-rose-50 text-rose-700 border-rose-200';
        }
        if (act.includes('updated')) {
            return 'bg-amber-50 text-amber-700 border-amber-200';
        }
        return 'bg-blue-50 text-blue-700 border-blue-200';
    };

    return (
        <OwnerLayout
            title="Log Aktivitas & Audit"
            breadcrumbs={[
                { label: 'Workspace', href: '/app/dashboard' },
                { label: 'Log Aktivitas & Audit' },
            ]}
            actions={
                <div className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs text-slate-600">
                    <Shield className="h-3.5 w-3.5 text-blue-600" />
                    <span>Terenkripsi</span>
                </div>
            }
        >
            <div className="space-y-6">
                {/* Filter Bar */}
                <div className="rounded-xl border border-slate-200 bg-white p-4">
                    <form
                        onSubmit={handleFilter}
                        className="flex flex-wrap items-end gap-3"
                    >
                        <div className="min-w-[180px] flex-1">
                            <label
                                className="mb-1 block text-xs font-medium text-slate-600"
                                htmlFor="action-select"
                            >
                                Jenis Aksi
                            </label>
                            <select
                                id="action-select"
                                value={action}
                                onChange={(e) => setAction(e.target.value)}
                                className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:outline-none"
                            >
                                <option value="">Semua Aksi</option>
                                {Object.entries(availableActions).map(
                                    ([key, label]) => (
                                        <option key={key} value={key}>
                                            {label} ({key})
                                        </option>
                                    )
                                )}
                            </select>
                        </div>

                        <div className="w-full sm:w-auto">
                            <label
                                className="mb-1 block text-xs font-medium text-slate-600"
                                htmlFor="date-from"
                            >
                                Dari Tanggal
                            </label>
                            <input
                                id="date-from"
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                                className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:outline-none"
                            />
                        </div>

                        <div className="w-full sm:w-auto">
                            <label
                                className="mb-1 block text-xs font-medium text-slate-600"
                                htmlFor="date-to"
                            >
                                Sampai Tanggal
                            </label>
                            <input
                                id="date-to"
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                                className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:outline-none"
                            />
                        </div>

                        <div className="flex items-center gap-2">
                            <button
                                type="submit"
                                className="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3.5 py-2 text-xs font-semibold text-white transition-colors hover:bg-blue-700"
                            >
                                <Filter className="h-3.5 w-3.5" />
                                <span>Filter</span>
                            </button>

                            <button
                                type="button"
                                onClick={handleReset}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50"
                            >
                                <RotateCcw className="h-3.5 w-3.5" />
                                <span>Reset</span>
                            </button>
                        </div>
                    </form>
                </div>

                {/* Audit Table */}
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                        <div>
                            <h2 className="text-sm font-bold text-slate-900">
                                Riwayat Log ({logs.total} Total Entri)
                            </h2>
                            <p className="text-xs text-slate-500">
                                Disortir berdasarkan kejadian terbaru (Waktu
                                Server UTC)
                            </p>
                        </div>
                    </div>

                    {logs.data.length === 0 ? (
                        <div className="p-12 text-center">
                            <Activity className="mx-auto h-8 w-8 text-slate-300" />
                            <p className="mt-2 text-sm font-semibold text-slate-700">
                                Belum ada aktivitas tercatat
                            </p>
                            <p className="text-xs text-slate-400">
                                Riwayat mutasi data akan muncul di sini secara
                                otomatis.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="border-b border-slate-100 bg-slate-50 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                                    <tr>
                                        <th className="px-6 py-3">Waktu</th>
                                        <th className="px-6 py-3">Aksi</th>
                                        <th className="px-6 py-3">Entitas</th>
                                        <th className="px-6 py-3">Aktor</th>
                                        <th className="px-6 py-3">IP & Asal</th>
                                        <th className="px-6 py-3 text-right">
                                            Payload
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {logs.data.map((log) => (
                                        <React.Fragment key={log.id}>
                                            <tr className="transition-colors hover:bg-slate-50/50">
                                                <td className="px-6 py-3.5 whitespace-nowrap text-slate-600">
                                                    <div className="flex items-center gap-1.5">
                                                        <Clock className="h-3.5 w-3.5 text-slate-400" />
                                                        <span className="font-mono text-[11px]">
                                                            {log.created_at}
                                                        </span>
                                                    </div>
                                                </td>

                                                <td className="px-6 py-3.5 whitespace-nowrap">
                                                    <span
                                                        className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-semibold ${getActionBadgeClass(
                                                            log.action
                                                        )}`}
                                                    >
                                                        {log.action}
                                                    </span>
                                                </td>

                                                <td className="px-6 py-3.5 whitespace-nowrap">
                                                    <span className="font-semibold text-slate-900">
                                                        {log.entity_type}
                                                    </span>
                                                    <span className="ml-1.5 font-mono text-[11px] text-slate-400">
                                                        #{log.entity_id}
                                                    </span>
                                                </td>

                                                <td className="px-6 py-3.5 whitespace-nowrap">
                                                    <div className="flex items-center gap-1.5">
                                                        <UserIcon className="h-3.5 w-3.5 text-slate-400" />
                                                        <span className="font-medium text-slate-700">
                                                            {log.actor_role ||
                                                                'SYSTEM'}
                                                        </span>
                                                        {log.actor_id && (
                                                            <span className="text-[10px] text-slate-400">
                                                                (ID:{' '}
                                                                {log.actor_id})
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>

                                                <td className="px-6 py-3.5 text-[11px] whitespace-nowrap text-slate-500">
                                                    <span>
                                                        {log.ip || '127.0.0.1'}
                                                    </span>
                                                    <span className="mx-1 text-slate-300">
                                                        •
                                                    </span>
                                                    <span className="capitalize">
                                                        {log.source || 'web'}
                                                    </span>
                                                </td>

                                                <td className="px-6 py-3.5 text-right whitespace-nowrap">
                                                    {(log.before ||
                                                        log.after) && (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setExpandedLogId(
                                                                    expandedLogId ===
                                                                        log.id
                                                                        ? null
                                                                        : log.id
                                                                )
                                                            }
                                                            className="text-xs font-semibold text-blue-600 hover:text-blue-700"
                                                        >
                                                            {expandedLogId ===
                                                            log.id
                                                                ? 'Tutup'
                                                                : 'Lihat Data'}
                                                        </button>
                                                    )}
                                                </td>
                                            </tr>

                                            {expandedLogId === log.id && (
                                                <tr className="bg-slate-50/70">
                                                    <td
                                                        colSpan={6}
                                                        className="px-6 py-3 text-xs"
                                                    >
                                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                            <div>
                                                                <span className="mb-1 block text-[11px] font-semibold text-slate-600">
                                                                    Data Sebelum
                                                                    (Before):
                                                                </span>
                                                                <pre className="overflow-x-auto rounded-lg bg-slate-900 p-3 font-mono text-[10px] text-slate-100">
                                                                    {log.before
                                                                        ? JSON.stringify(
                                                                              log.before,
                                                                              null,
                                                                              2
                                                                          )
                                                                        : 'null (Data Baru)'}
                                                                </pre>
                                                            </div>
                                                            <div>
                                                                <span className="mb-1 block text-[11px] font-semibold text-slate-600">
                                                                    Data Sesudah
                                                                    (After):
                                                                </span>
                                                                <pre className="overflow-x-auto rounded-lg bg-slate-900 p-3 font-mono text-[10px] text-slate-100">
                                                                    {log.after
                                                                        ? JSON.stringify(
                                                                              log.after,
                                                                              null,
                                                                              2
                                                                          )
                                                                        : 'null (Data Dihapus)'}
                                                                </pre>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            )}
                                        </React.Fragment>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Pagination */}
                    {logs.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-6 py-3">
                            <span className="text-xs text-slate-500">
                                Halaman {logs.current_page} dari{' '}
                                {logs.last_page}
                            </span>
                            <div className="flex items-center gap-1">
                                {logs.links.map((link, idx) => (
                                    <Link
                                        key={idx}
                                        href={link.url || '#'}
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                        className={`rounded px-2.5 py-1 text-xs ${
                                            link.active
                                                ? 'bg-blue-600 font-semibold text-white'
                                                : link.url
                                                  ? 'text-slate-600 hover:bg-slate-100'
                                                  : 'pointer-events-none text-slate-300'
                                        }`}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </OwnerLayout>
    );
}
