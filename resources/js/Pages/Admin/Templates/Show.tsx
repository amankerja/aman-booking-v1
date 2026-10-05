import { Head, Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Check,
    Copy,
    FileCode,
    FileText,
    GitBranch,
    History,
    Plus,
    Rocket,
    Shield,
    Users,
} from 'lucide-react';
import React, { useState } from 'react';
import { Badge } from '../../../Components/ui/Badge';
import { Button } from '../../../Components/ui/Button';
import { Input } from '../../../Components/ui/Input';
import { useToast } from '../../../Components/ui/Toast';
import { AdminLayout } from '../../../Layouts/AdminLayout';

interface WorkflowNodeData {
    label?: string;
    category?: string;
    type?: string;
    config?: Record<string, unknown>;
    event?: string;
}

interface WorkflowNodeItem {
    id: string;
    type: string;
    position?: { x: number; y: number };
    data?: WorkflowNodeData;
}

interface FormFieldItem {
    field_key: string;
    field_label?: string;
    label?: string;
    field_type?: string;
    type?: string;
    is_required?: boolean;
    sort_order?: number;
}

export interface SystemTemplateVersionItem {
    id: number;
    system_template_id: number;
    version: string;
    status: 'DRAFT' | 'PUBLISHED';
    changelog: string | null;
    payload: Record<string, unknown>;
    created_by: number | null;
    published_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface SystemTemplateDetail {
    id: number;
    kind: 'workflow' | 'form';
    key: string;
    name: string;
    description: string | null;
    business_type: string;
    is_active: boolean;
    latest_version_id: number | null;
    latest_version?: SystemTemplateVersionItem | null;
    versions: SystemTemplateVersionItem[];
}

interface AdminTemplateShowProps {
    template: SystemTemplateDetail;
    tenantUsageCount: number;
}

export default function AdminTemplateShow({
    template,
    tenantUsageCount,
}: AdminTemplateShowProps) {
    const { addToast } = useToast();
    const isWorkflow = template.kind === 'workflow';

    // State for selected version to inspect
    const [selectedVersionId, setSelectedVersionId] = useState<number>(
        template.latest_version_id ?? (template.versions[0]?.id || 0)
    );

    const selectedVersion =
        template.versions.find((v) => v.id === selectedVersionId) ||
        template.latest_version ||
        template.versions[0];

    // State for creating new version
    const [showCreateModal, setShowCreateModal] = useState<boolean>(false);
    const [newVersionNumber, setNewVersionNumber] = useState<string>('');
    const [newChangelog, setNewChangelog] = useState<string>('');
    const [newPayloadJson, setNewPayloadJson] = useState<string>('');
    const [jsonError, setJsonError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
    const [copiedJson, setCopiedJson] = useState<boolean>(false);

    const openCreateModal = () => {
        // Suggest next semver based on latest
        const currentVer = template.latest_version?.version || '1.0.0';
        const parts = currentVer.split('.').map(Number);
        const suggested = parts.length === 3 ? `${parts[0]}.${parts[1] + 1}.0` : '1.1.0';

        setNewVersionNumber(suggested);
        setNewChangelog('');
        setNewPayloadJson(
            selectedVersion
                ? JSON.stringify(selectedVersion.payload, null, 2)
                : '{\n  \n}'
        );
        setJsonError(null);
        setShowCreateModal(true);
    };

    const handleCreateVersion = (e: React.FormEvent) => {
        e.preventDefault();
        setJsonError(null);

        let parsedPayload: Record<string, unknown>;
        try {
            parsedPayload = JSON.parse(newPayloadJson);
            if (typeof parsedPayload !== 'object' || parsedPayload === null) {
                setJsonError('Payload harus berupa objek JSON valid.');
                return;
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : String(err);
            setJsonError(`JSON Syntax Error: ${message}`);
            return;
        }

        setIsSubmitting(true);
        router.post(
            `/admin/templates/${template.id}/versions`,
            {
                version: newVersionNumber,
                changelog: newChangelog,
                payload: parsedPayload,
            },
            {
                onSuccess: () => {
                    addToast({
                        title: 'Draf Versi Dibuat',
                        message: `Versi ${newVersionNumber} berhasil disimpan sebagai draf.`,
                        variant: 'success',
                    });
                    setShowCreateModal(false);
                    setIsSubmitting(false);
                },
                onError: (errors) => {
                    const firstErr = Object.values(errors)[0] as string;
                    addToast({
                        title: 'Gagal Menyimpan Draf',
                        message: firstErr || 'Periksa kembali data yang dimasukkan.',
                        variant: 'danger',
                    });
                    setIsSubmitting(false);
                },
            }
        );
    };

    const handlePublishVersion = (versionId: number, versionNum: string) => {
        if (
            !confirm(
                `Apakah Anda yakin ingin mempublikasikan versi ${versionNum}? Versi ini akan langsung menjadi rilis standar global dan memicu notifikasi pembaruan untuk tenant terkait.`
            )
        ) {
            return;
        }

        router.post(
            `/admin/templates/versions/${versionId}/publish`,
            {},
            {
                onSuccess: () => {
                    addToast({
                        title: 'Versi Dipublikasikan',
                        message: `Versi ${versionNum} kini aktif sebagai versi standar global.`,
                        variant: 'success',
                    });
                },
                onError: () => {
                    addToast({
                        title: 'Gagal Publikasi',
                        message: 'Terjadi kesalahan saat mempublikasikan versi.',
                        variant: 'danger',
                    });
                },
            }
        );
    };

    const copyPayloadToClipboard = () => {
        if (!selectedVersion) return;
        navigator.clipboard.writeText(JSON.stringify(selectedVersion.payload, null, 2));
        setCopiedJson(true);
        setTimeout(() => setCopiedJson(false), 2000);
    };

    return (
        <AdminLayout title={`${template.name} - Detail Template`}>
            <Head title={`${template.name} - Super Admin`} />

            <div className="space-y-6">
                {/* Back button and Header */}
                <div>
                    <Link
                        href="/admin/templates"
                        className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 transition-colors hover:text-slate-900"
                    >
                        <ArrowLeft className="h-3.5 w-3.5" />
                        <span>Kembali ke Katalog Template</span>
                    </Link>

                    <div className="mt-3 flex flex-col justify-between gap-4 rounded-xl border border-slate-200 bg-white p-6 md:flex-row md:items-center">
                        <div className="flex items-start gap-3">
                            <span
                                className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${
                                    isWorkflow
                                        ? 'bg-sky-50 text-sky-600'
                                        : 'bg-emerald-50 text-emerald-600'
                                }`}
                            >
                                {isWorkflow ? (
                                    <GitBranch className="h-6 w-6" />
                                ) : (
                                    <FileText className="h-6 w-6" />
                                )}
                            </span>
                            <div>
                                <div className="flex items-center gap-2">
                                    <h1 className="text-xl font-bold tracking-tight text-slate-900">
                                        {template.name}
                                    </h1>
                                    <Badge
                                        variant={isWorkflow ? 'hauling' : 'active'}
                                        size="sm"
                                    >
                                        {isWorkflow ? 'Workflow Engine' : 'Booking Form'}
                                    </Badge>
                                </div>
                                <p className="mt-1 font-mono text-xs text-slate-500">
                                    Key: <span className="font-semibold text-slate-800">{template.key}</span> • Industri: <span className="font-semibold text-slate-800">{template.business_type}</span>
                                </p>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-3">
                            <div className="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-600">
                                <Users className="h-4 w-4 text-slate-500" />
                                <span>
                                    <strong>{tenantUsageCount}</strong> Tenant Menggunakan
                                </span>
                            </div>

                            <Button
                                variant="primary"
                                size="sm"
                                onClick={openCreateModal}
                                className="bg-blue-600 text-white hover:bg-blue-700"
                            >
                                <Plus className="mr-1.5 h-4 w-4" />
                                Buat Draf Versi Baru
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Main Split: Left = Versions List, Right = Version Inspector */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    {/* Versions History Column (4 cols) */}
                    <div className="space-y-4 lg:col-span-4">
                        <div className="rounded-xl border border-slate-200 bg-white p-4">
                            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                                <div className="flex items-center gap-2">
                                    <History className="h-4 w-4 text-slate-500" />
                                    <h2 className="text-xs font-bold uppercase tracking-wider text-slate-700">
                                        Riwayat Versi ({template.versions.length})
                                    </h2>
                                </div>
                                <span className="text-[11px] text-slate-400">
                                    SemVer Immutable
                                </span>
                            </div>

                            <div className="mt-3 space-y-2">
                                {template.versions.map((ver) => {
                                    const isSelected = ver.id === selectedVersion?.id;
                                    const isPublished = ver.status === 'PUBLISHED';
                                    const isLatest = template.latest_version_id === ver.id;

                                    return (
                                        <div
                                            key={ver.id}
                                            onClick={() => setSelectedVersionId(ver.id)}
                                            className={`cursor-pointer rounded-lg border p-3.5 transition-all ${
                                                isSelected
                                                    ? 'border-blue-500 bg-blue-50/40 shadow-xs'
                                                    : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/50'
                                            }`}
                                        >
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-mono text-sm font-bold text-slate-900">
                                                        v{ver.version}
                                                    </span>
                                                    {isLatest && (
                                                        <span className="rounded-full bg-blue-600 px-2 py-0.5 text-[10px] font-bold text-white">
                                                            LATEST
                                                        </span>
                                                    )}
                                                </div>
                                                <Badge
                                                    variant={isPublished ? 'active' : 'queuing'}
                                                    size="sm"
                                                >
                                                    {ver.status}
                                                </Badge>
                                            </div>

                                            {ver.changelog && (
                                                <p className="mt-1.5 line-clamp-2 text-xs text-slate-600">
                                                    {ver.changelog}
                                                </p>
                                            )}

                                            <div className="mt-2.5 flex items-center justify-between text-[11px] text-slate-400">
                                                <span>
                                                    {isPublished && ver.published_at
                                                        ? `Rilis: ${new Date(ver.published_at).toLocaleDateString('id-ID')}`
                                                        : `Dibuat: ${new Date(ver.created_at).toLocaleDateString('id-ID')}`}
                                                </span>

                                                {!isPublished && (
                                                    <button
                                                        type="button"
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            handlePublishVersion(ver.id, ver.version);
                                                        }}
                                                        className="inline-flex items-center gap-1 font-semibold text-blue-600 hover:underline"
                                                    >
                                                        <Rocket className="h-3 w-3" />
                                                        Rilis Versi
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Immutability Banner */}
                        <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-600">
                            <div className="flex items-start gap-2">
                                <Shield className="mt-0.5 h-4 w-4 shrink-0 text-slate-500" />
                                <div>
                                    <p className="font-semibold text-slate-800">
                                        Perlindungan Integritas
                                    </p>
                                    <p className="mt-0.5 text-[11px] text-slate-500">
                                        Versi dengan status <strong>PUBLISHED</strong> dikunci secara permanen dan tidak dapat diubah atau dihapus untuk menjaga konsistensi audit log dan integritas alur kerja tenant.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Inspector Column (8 cols) */}
                    <div className="space-y-4 lg:col-span-8">
                        {selectedVersion ? (
                            <div className="rounded-xl border border-slate-200 bg-white p-6">
                                <div className="flex flex-col justify-between gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-center">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <h2 className="text-base font-bold text-slate-900">
                                                Detail Versi {selectedVersion.version}
                                            </h2>
                                            <Badge
                                                variant={
                                                    selectedVersion.status === 'PUBLISHED'
                                                        ? 'active'
                                                        : 'queuing'
                                                }
                                                size="sm"
                                            >
                                                {selectedVersion.status}
                                            </Badge>
                                        </div>
                                        <p className="mt-1 text-xs text-slate-500">
                                            {selectedVersion.changelog || 'Tidak ada catatan perubahan.'}
                                        </p>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <button
                                            type="button"
                                            onClick={copyPayloadToClipboard}
                                            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                        >
                                            {copiedJson ? (
                                                <>
                                                    <Check className="h-3.5 w-3.5 text-emerald-600" />
                                                    <span className="text-emerald-700">Tersalin</span>
                                                </>
                                            ) : (
                                                <>
                                                    <Copy className="h-3.5 w-3.5" />
                                                    <span>Salin JSON</span>
                                                </>
                                            )}
                                        </button>

                                        {selectedVersion.status === 'DRAFT' && (
                                            <Button
                                                variant="primary"
                                                size="sm"
                                                onClick={() =>
                                                    handlePublishVersion(
                                                        selectedVersion.id,
                                                        selectedVersion.version
                                                    )
                                                }
                                                className="bg-emerald-600 text-white hover:bg-emerald-700"
                                            >
                                                <Rocket className="mr-1.5 h-3.5 w-3.5" />
                                                Publikasikan Versi Ini
                                            </Button>
                                        )}
                                    </div>
                                </div>

                                {/* Payload Visual Summary */}
                                <div className="mt-5">
                                    <h3 className="text-xs font-bold uppercase tracking-wider text-slate-700">
                                        {isWorkflow ? 'Struktur Alur Kerja (Workflow Graph)' : 'Struktur Kolom Formulir (Form Fields)'}
                                    </h3>

                                    {isWorkflow ? (
                                        <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                            <div className="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                                <span className="text-[11px] text-slate-500">Trigger Node</span>
                                                <p className="mt-1 font-mono text-xs font-bold text-slate-800">
                                                    {(selectedVersion.payload?.trigger as { event?: string } | undefined)?.event ||
                                                        (selectedVersion.payload?.nodes as WorkflowNodeItem[] | undefined)?.find(
                                                            (n) => n.type === 'trigger'
                                                        )?.data?.event ||
                                                        'BOOKING_CREATED'}
                                                </p>
                                            </div>
                                            <div className="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                                <span className="text-[11px] text-slate-500">Total Node</span>
                                                <p className="mt-1 text-sm font-bold text-slate-800">
                                                    {Array.isArray(selectedVersion.payload?.nodes)
                                                        ? selectedVersion.payload.nodes.length
                                                        : 0} Node
                                                </p>
                                            </div>
                                            <div className="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                                <span className="text-[11px] text-slate-500">Total Sambungan (Edges)</span>
                                                <p className="mt-1 text-sm font-bold text-slate-800">
                                                    {Array.isArray(selectedVersion.payload?.edges)
                                                        ? selectedVersion.payload.edges.length
                                                        : 0} Sambungan
                                                </p>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="mt-3 overflow-hidden rounded-lg border border-slate-200">
                                            <table className="w-full text-left text-xs">
                                                <thead className="border-b border-slate-200 bg-slate-50 text-[11px] font-semibold text-slate-600">
                                                    <tr>
                                                        <th className="px-3 py-2">Urutan</th>
                                                        <th className="px-3 py-2">Key</th>
                                                        <th className="px-3 py-2">Label Kolom</th>
                                                        <th className="px-3 py-2">Tipe Kolom</th>
                                                        <th className="px-3 py-2">Wajib</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-slate-100">
                                                    {Array.isArray(selectedVersion.payload?.fields) &&
                                                    selectedVersion.payload.fields.length > 0 ? (
                                                        (selectedVersion.payload.fields as FormFieldItem[]).map(
                                                            (field, idx) => (
                                                                <tr key={field.field_key || idx}>
                                                                    <td className="px-3 py-2 font-mono text-slate-400">
                                                                        {field.sort_order ?? idx + 1}
                                                                    </td>
                                                                    <td className="px-3 py-2 font-mono font-medium text-slate-800">
                                                                        {field.field_key}
                                                                    </td>
                                                                    <td className="px-3 py-2 font-medium text-slate-900">
                                                                        {field.field_label || field.label || field.field_key}
                                                                    </td>
                                                                    <td className="px-3 py-2">
                                                                        <span className="rounded bg-slate-100 px-2 py-0.5 font-mono text-[10px] text-slate-700">
                                                                            {field.field_type || field.type || 'text'}
                                                                        </span>
                                                                    </td>
                                                                    <td className="px-3 py-2">
                                                                        {field.is_required ? (
                                                                            <span className="font-semibold text-rose-600">
                                                                                Ya
                                                                            </span>
                                                                        ) : (
                                                                            <span className="text-slate-400">
                                                                                Opsional
                                                                            </span>
                                                                        )}
                                                                    </td>
                                                                </tr>
                                                            )
                                                        )
                                                    ) : (
                                                        <tr>
                                                            <td
                                                                colSpan={5}
                                                                className="px-3 py-4 text-center text-slate-400"
                                                            >
                                                                Tidak ada kolom terdefinisi
                                                            </td>
                                                        </tr>
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </div>

                                {/* Raw JSON viewer */}
                                <div className="mt-5">
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-xs font-bold uppercase tracking-wider text-slate-700">
                                            Payload JSON Mentah
                                        </h3>
                                        <span className="font-mono text-[10px] text-slate-400">
                                            {JSON.stringify(selectedVersion.payload).length} bytes
                                        </span>
                                    </div>
                                    <pre className="mt-2 max-h-96 overflow-auto rounded-lg border border-slate-200 bg-slate-900 p-4 font-mono text-xs text-slate-200">
                                        <code>{JSON.stringify(selectedVersion.payload, null, 2)}</code>
                                    </pre>
                                </div>
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-white py-12 text-center">
                                <FileCode className="h-10 w-10 text-slate-300" />
                                <h4 className="mt-3 text-sm font-semibold text-slate-800">
                                    Belum ada versi dipilih
                                </h4>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Create Draft Version Modal */}
            {showCreateModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-xs">
                    <div className="w-full max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-xl">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 className="text-base font-bold text-slate-900">
                                Buat Draf Versi Baru untuk {template.name}
                            </h3>
                            <button
                                type="button"
                                onClick={() => setShowCreateModal(false)}
                                className="text-slate-400 hover:text-slate-600"
                            >
                                &times;
                            </button>
                        </div>

                        <form onSubmit={handleCreateVersion} className="mt-4 space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700">
                                        Nomor Versi (SemVer X.Y.Z) <span className="text-rose-500">*</span>
                                    </label>
                                    <Input
                                        placeholder="contoh: 1.1.0"
                                        value={newVersionNumber}
                                        onChange={(e) => setNewVersionNumber(e.target.value)}
                                        pattern="^[0-9]+\.[0-9]+\.[0-9]+$"
                                        required
                                        className="mt-1 h-9 text-xs font-mono"
                                    />
                                    <p className="mt-1 text-[11px] text-slate-500">
                                        Format: angka mayor.minor.patch (cth: 1.1.0)
                                    </p>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-700">
                                        Catatan Perubahan (Changelog)
                                    </label>
                                    <Input
                                        placeholder="Penambahan langkah konfirmasi WA..."
                                        value={newChangelog}
                                        onChange={(e) => setNewChangelog(e.target.value)}
                                        className="mt-1 h-9 text-xs"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700">
                                    Payload JSON Struktur Versi <span className="text-rose-500">*</span>
                                </label>
                                <textarea
                                    value={newPayloadJson}
                                    onChange={(e) => {
                                        setNewPayloadJson(e.target.value);
                                        setJsonError(null);
                                    }}
                                    rows={10}
                                    className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-900 p-3 font-mono text-xs text-slate-100 focus:border-blue-500 focus:outline-hidden"
                                    required
                                />
                                {jsonError && (
                                    <div className="mt-1.5 flex items-center gap-1.5 text-xs text-rose-600">
                                        <AlertCircle className="h-4 w-4" />
                                        <span>{jsonError}</span>
                                    </div>
                                )}
                            </div>

                            <div className="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setShowCreateModal(false)}
                                    disabled={isSubmitting}
                                >
                                    Batal
                                </Button>
                                <Button
                                    type="submit"
                                    variant="primary"
                                    size="sm"
                                    disabled={isSubmitting}
                                    className="bg-blue-600 text-white hover:bg-blue-700"
                                >
                                    {isSubmitting ? 'Menyimpan...' : 'Simpan Draf Versi'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
