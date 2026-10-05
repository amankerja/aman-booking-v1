import {
    AlertCircle,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    Clock,
    Play,
    RefreshCw,
    RotateCw,
} from 'lucide-react';
import React, { useCallback, useEffect, useState } from 'react';
import { Button } from '../../../Components/ui/Button';
import { Drawer } from '../../../Components/ui/Drawer';
import { useToast } from '../../../Components/ui/Toast';
import { WorkflowLogItem, WorkflowRunItem } from './types';

interface WorkflowRunsDrawerProps {
    isOpen: boolean;
    onClose: () => void;
    workflowId: number;
    workflowName: string;
}

export const WorkflowRunsDrawer: React.FC<WorkflowRunsDrawerProps> = ({
    isOpen,
    onClose,
    workflowId,
    workflowName,
}) => {
    const toast = useToast();
    const [runs, setRuns] = useState<WorkflowRunItem[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [expandedRunId, setExpandedRunId] = useState<number | null>(null);
    const [runLogs, setRunLogs] = useState<Record<number, WorkflowLogItem[]>>({});
    const [retryingId, setRetryingId] = useState<number | null>(null);

    const fetchRuns = useCallback(async () => {
        setIsLoading(true);
        try {
            const res = await fetch(`/app/workflows/${workflowId}/runs`);
            if (res.ok) {
                const data = await res.json();
                setRuns(data.data || []);
            }
        } catch {
            toast.error('Gagal memuat riwayat eksekusi alur kerja.');
        } finally {
            setIsLoading(false);
        }
    }, [workflowId, toast]);

    useEffect(() => {
        if (isOpen && workflowId) {
            fetchRuns();
        }
    }, [isOpen, workflowId, fetchRuns]);

    const handleToggleExpand = async (runId: number) => {
        if (expandedRunId === runId) {
            setExpandedRunId(null);
            return;
        }

        setExpandedRunId(runId);

        // Fetch logs if not cached yet
        if (!runLogs[runId]) {
            try {
                const res = await fetch(`/app/workflows/runs/${runId}`);
                if (res.ok) {
                    const data = await res.json();
                    setRunLogs((prev) => ({ ...prev, [runId]: data.logs || [] }));
                }
            } catch {
                toast.error('Gagal memuat log detail simpul.');
            }
        }
    };

    const handleRetry = async (runId: number) => {
        setRetryingId(runId);
        try {
            const csrfToken =
                (
                    document.querySelector(
                        'meta[name="csrf-token"]'
                    ) as HTMLMetaElement
                )?.content || '';

            const res = await fetch(`/app/workflows/runs/${runId}/retry`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            });

            if (res.ok) {
                toast.success('Alur kerja berhasil dijadwalkan ulang.');
                fetchRuns();
            } else {
                toast.error('Gagal menjadwalkan ulang alur kerja.');
            }
        } catch {
            toast.error('Terjadi kesalahan saat mencoba kembali.');
        } finally {
            setRetryingId(null);
        }
    };

    return (
        <Drawer
            isOpen={isOpen}
            onClose={onClose}
            title={
                <div className="flex items-center gap-2">
                    <span className="font-bold text-slate-900">Riwayat Eksekusi Alur:</span>
                    <span className="truncate max-w-[200px] text-xs font-semibold text-blue-600 bg-blue-50 border border-blue-200 px-2 py-0.5 rounded-full">
                        {workflowName}
                    </span>
                </div>
            }
            size="lg"
            footer={
                <div className="flex items-center justify-between w-full">
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        onClick={fetchRuns}
                        isLoading={isLoading}
                        className="gap-1.5"
                    >
                        <RefreshCw className="h-3.5 w-3.5" /> Segarkan
                    </Button>
                    <Button type="button" variant="primary" size="sm" onClick={onClose}>
                        Tutup
                    </Button>
                </div>
            }
        >
            <div className="space-y-3 p-1">
                {isLoading && runs.length === 0 ? (
                    <div className="py-12 text-center text-xs text-slate-400">
                        Memuat riwayat eksekusi alur...
                    </div>
                ) : runs.length === 0 ? (
                    <div className="rounded-[10px] border border-slate-200 bg-slate-50 p-8 text-center text-xs text-slate-500">
                        Belum ada eksekusi alur kerja untuk alur ini. Eksekusi akan tercatat secara otomatis saat event pemicu (booking dibuat/status berubah) terjadi.
                    </div>
                ) : (
                    <div className="space-y-2.5">
                        {runs.map((run) => {
                            const isExpanded = expandedRunId === run.id;
                            const logs = runLogs[run.id] || [];

                            return (
                                <div
                                    key={run.id}
                                    className="rounded-[10px] border border-slate-200 bg-white p-3 shadow-xs transition-colors hover:border-slate-300"
                                >
                                    {/* Top Row: Execution ID, Status, Actions */}
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <button
                                                type="button"
                                                onClick={() => handleToggleExpand(run.id)}
                                                className="text-slate-400 hover:text-slate-600"
                                            >
                                                {isExpanded ? (
                                                    <ChevronDown className="h-4 w-4" />
                                                ) : (
                                                    <ChevronRight className="h-4 w-4" />
                                                )}
                                            </button>
                                            <span className="font-mono text-xs font-bold text-slate-800">
                                                {run.execution_id}
                                            </span>
                                            {run.booking && (
                                                <span className="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600">
                                                    {run.booking.code}
                                                </span>
                                            )}
                                        </div>

                                        <div className="flex items-center gap-2">
                                            {/* Status Badge */}
                                            <span
                                                className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${
                                                    run.status === 'COMPLETED'
                                                        ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                                        : run.status === 'RUNNING'
                                                          ? 'bg-sky-50 text-sky-700 border border-sky-200'
                                                          : run.status === 'FAILED'
                                                            ? 'bg-rose-50 text-rose-700 border border-rose-200'
                                                            : 'bg-amber-50 text-amber-700 border border-amber-200'
                                                }`}
                                            >
                                                {run.status}
                                            </span>

                                            {/* Retry Button if Failed */}
                                            {run.status === 'FAILED' && (
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                    size="sm"
                                                    onClick={() => handleRetry(run.id)}
                                                    isLoading={retryingId === run.id}
                                                    className="h-6 px-2 text-[10px] text-rose-600 hover:bg-rose-50 border-rose-200 gap-1"
                                                >
                                                    <RotateCw className="h-2.5 w-2.5" /> Coba Lagi
                                                </Button>
                                            )}
                                        </div>
                                    </div>

                                    {/* Meta Row */}
                                    <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-slate-500">
                                        <span>
                                            Event: <strong className="text-slate-700">{run.trigger_event}</strong>
                                        </span>
                                        <span>
                                            Langkah: <strong className="text-slate-700">{run.depth}</strong>
                                        </span>
                                        <span>
                                            Waktu: {new Date(run.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                                        </span>
                                    </div>

                                    {/* Error Message banner if failed */}
                                    {run.error_message && (
                                        <div className="mt-2 flex items-start gap-1.5 rounded-[6px] border border-rose-200 bg-rose-50 p-2 text-xs text-rose-800">
                                            <AlertCircle className="h-3.5 w-3.5 shrink-0 mt-0.5" />
                                            <span>{run.error_message}</span>
                                        </div>
                                    )}

                                    {/* Expanded Node Logs */}
                                    {isExpanded && (
                                        <div className="mt-3 border-t border-slate-100 pt-3 space-y-2">
                                            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                                Log Eksekusi Tiap Node ({logs.length}):
                                            </span>
                                            {logs.length === 0 ? (
                                                <div className="text-[11px] text-slate-400 py-1">
                                                    Memuat detail langkah...
                                                </div>
                                            ) : (
                                                <div className="space-y-1.5 max-h-60 overflow-y-auto pr-1">
                                                    {logs.map((log) => (
                                                        <div
                                                            key={log.id}
                                                            className="flex items-start gap-2 rounded-[6px] border border-slate-100 bg-slate-50 p-2 text-xs"
                                                        >
                                                            <div className="mt-0.5">
                                                                {log.status === 'SUCCESS' ? (
                                                                    <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" />
                                                                ) : log.status === 'WAITING_DELAY' ? (
                                                                    <Clock className="h-3.5 w-3.5 text-amber-500" />
                                                                ) : log.status === 'FAILED' ? (
                                                                    <AlertCircle className="h-3.5 w-3.5 text-rose-600" />
                                                                ) : (
                                                                    <Play className="h-3.5 w-3.5 text-sky-600" />
                                                                )}
                                                            </div>
                                                            <div className="flex-1 overflow-hidden">
                                                                <div className="flex items-center justify-between">
                                                                    <span className="font-bold text-slate-800">
                                                                        {log.node_label || log.node_id}
                                                                    </span>
                                                                    <span className="text-[10px] uppercase font-semibold text-slate-400">
                                                                        {log.status} (Coba ke-{log.attempt})
                                                                    </span>
                                                                </div>
                                                                {log.error_message && (
                                                                    <p className="mt-1 text-[11px] text-rose-700">
                                                                        {log.error_message}
                                                                    </p>
                                                                )}
                                                                {log.output_data && Object.keys(log.output_data).length > 0 && (
                                                                    <p className="mt-1 font-mono text-[10px] text-slate-500 truncate">
                                                                        {JSON.stringify(log.output_data)}
                                                                    </p>
                                                                )}
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </Drawer>
    );
};
