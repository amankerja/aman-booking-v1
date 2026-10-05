import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    FileText,
    GitBranch,
    Layers,
    RefreshCw,
    Search,
    Shield,
} from 'lucide-react';
import React, { useMemo, useState } from 'react';
import { Badge } from '../../../Components/ui/Badge';
import { Button } from '../../../Components/ui/Button';
import { Input } from '../../../Components/ui/Input';
import { useToast } from '../../../Components/ui/Toast';
import { AdminLayout } from '../../../Layouts/AdminLayout';

export interface SystemTemplateVersionSummary {
    id: number;
    version: string;
    status: 'DRAFT' | 'PUBLISHED';
    published_at: string | null;
    changelog: string | null;
    created_at: string;
}

export interface SystemTemplateSummary {
    id: number;
    kind: 'workflow' | 'form';
    key: string;
    name: string;
    description: string | null;
    business_type: string;
    is_active: boolean;
    latest_version_id: number | null;
    latest_version?: SystemTemplateVersionSummary | null;
    versions_count?: number;
    versions?: SystemTemplateVersionSummary[];
}

interface AdminTemplatesIndexProps {
    templates: SystemTemplateSummary[];
    currentKind?: string | null;
}

export default function AdminTemplatesIndex({
    templates,
    currentKind: initialKind = null,
}: AdminTemplatesIndexProps) {
    const { addToast } = useToast();
    const [selectedKind, setSelectedKind] = useState<string>(initialKind || 'all');
    const [searchQuery, setSearchQuery] = useState<string>('');
    const [isSyncing, setIsSyncing] = useState<boolean>(false);

    const handleSyncDefaults = () => {
        setIsSyncing(true);
        router.post(
            '/admin/templates/sync-defaults',
            {},
            {
                onSuccess: () => {
                    addToast({
                        title: 'Sinkronisasi Berhasil',
                        message: 'Template standar sistem berhasil diperbarui.',
                        variant: 'success',
                    });
                    setIsSyncing(false);
                },
                onError: () => {
                    addToast({
                        title: 'Gagal Sinkronisasi',
                        message: 'Terjadi kesalahan saat menyinkronkan template default.',
                        variant: 'danger',
                    });
                    setIsSyncing(false);
                },
            }
        );
    };

    const filteredTemplates = useMemo(() => {
        return templates.filter((template) => {
            const matchesKind =
                selectedKind === 'all' || template.kind === selectedKind;
            const matchesSearch =
                searchQuery.trim() === '' ||
                template.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                template.key.toLowerCase().includes(searchQuery.toLowerCase()) ||
                template.business_type.toLowerCase().includes(searchQuery.toLowerCase()) ||
                (template.description &&
                    template.description.toLowerCase().includes(searchQuery.toLowerCase()));

            return matchesKind && matchesSearch;
        });
    }, [templates, selectedKind, searchQuery]);

    const stats = useMemo(() => {
        const workflows = templates.filter((t) => t.kind === 'workflow').length;
        const forms = templates.filter((t) => t.kind === 'form').length;
        const total = templates.length;
        return { workflows, forms, total };
    }, [templates]);

    return (
        <AdminLayout title="Katalog Template Sistem & Versioning">
            <Head title="Katalog Template & Versioning - Super Admin" />

            <div className="space-y-6">
                {/* Header Banner */}
                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                    <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-bold tracking-tight text-slate-900">
                                    Katalog Template Sistem & Versioning
                                </h1>
                                <Badge variant="hauling" size="sm">
                                    PRD 47 / 185 / 186
                                </Badge>
                            </div>
                            <p className="mt-1 text-sm text-slate-500">
                                Kelola template global Alur Kerja (Workflow) dan Formulir Booking lintas industri vertikal dengan sistem Semantic Versioning immutable.
                            </p>
                        </div>
                        <div className="flex items-center gap-3">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleSyncDefaults}
                                disabled={isSyncing}
                                className="border-slate-200 text-slate-700 hover:bg-slate-50"
                            >
                                <RefreshCw
                                    className={`mr-2 h-4 w-4 ${isSyncing ? 'animate-spin' : ''}`}
                                />
                                {isSyncing ? 'Menyinkronkan...' : 'Sinkron Template Standar'}
                            </Button>
                        </div>
                    </div>
                </div>

                {/* System Guarantees Notice */}
                <div className="rounded-xl border border-blue-100 bg-blue-50/60 p-4">
                    <div className="flex items-start gap-3">
                        <Shield className="mt-0.5 h-5 w-5 shrink-0 text-blue-600" />
                        <div className="text-xs text-blue-900">
                            <p className="font-semibold">
                                Garansi Immutability & Perlindungan Konfigurasi Tenant (PRD 186)
                            </p>
                            <p className="mt-0.5 text-blue-700">
                                Setiap rilis versi template global (misal v1.1.0) bersifat <strong>permanen & immutable</strong>. Pembaruan template dari Super Admin <strong>TIDAK PERNAH menimpa</strong> alur kerja atau formulir tenant yang sedang berjalan. Tenant menerima notifikasi &ldquo;Update Tersedia&rdquo; dan memilih untuk menduplikasi ke draf baru secara sadar.
                            </p>
                        </div>
                    </div>
                </div>

                {/* Filter and Search Bar */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    {/* Kind Tabs */}
                    <div className="inline-flex rounded-lg border border-slate-200 bg-white p-1 shadow-2xs">
                        <button
                            type="button"
                            onClick={() => setSelectedKind('all')}
                            className={`rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${
                                selectedKind === 'all'
                                    ? 'bg-slate-900 text-white'
                                    : 'text-slate-600 hover:text-slate-900'
                            }`}
                        >
                            Semua ({stats.total})
                        </button>
                        <button
                            type="button"
                            onClick={() => setSelectedKind('workflow')}
                            className={`flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${
                                selectedKind === 'workflow'
                                    ? 'bg-slate-900 text-white'
                                    : 'text-slate-600 hover:text-slate-900'
                            }`}
                        >
                            <GitBranch className="h-3.5 w-3.5" />
                            Alur Kerja ({stats.workflows})
                        </button>
                        <button
                            type="button"
                            onClick={() => setSelectedKind('form')}
                            className={`flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${
                                selectedKind === 'form'
                                    ? 'bg-slate-900 text-white'
                                    : 'text-slate-600 hover:text-slate-900'
                            }`}
                        >
                            <FileText className="h-3.5 w-3.5" />
                            Formulir ({stats.forms})
                        </button>
                    </div>

                    {/* Search Field */}
                    <div className="relative w-full sm:w-72">
                        <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                        <Input
                            placeholder="Cari nama, kunci, industri..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="h-9 pl-9 text-xs"
                        />
                    </div>
                </div>

                {/* Templates Grid */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {filteredTemplates.map((template) => {
                        const isWorkflow = template.kind === 'workflow';
                        const latestVer = template.latest_version;
                        const versionCount =
                            template.versions_count ?? template.versions?.length ?? 1;

                        return (
                            <div
                                key={template.id}
                                className="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-5 transition-shadow hover:shadow-xs"
                            >
                                <div>
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="flex items-center gap-2">
                                            <span
                                                className={`flex h-8 w-8 items-center justify-center rounded-lg ${
                                                    isWorkflow
                                                        ? 'bg-sky-50 text-sky-600'
                                                        : 'bg-emerald-50 text-emerald-600'
                                                }`}
                                            >
                                                {isWorkflow ? (
                                                    <GitBranch className="h-4 w-4" />
                                                ) : (
                                                    <FileText className="h-4 w-4" />
                                                )}
                                            </span>
                                            <div>
                                                <Badge
                                                    variant={isWorkflow ? 'hauling' : 'active'}
                                                    size="sm"
                                                >
                                                    {isWorkflow ? 'Workflow' : 'Form Booking'}
                                                </Badge>
                                            </div>
                                        </div>

                                        <span className="font-mono text-[11px] font-medium text-slate-400">
                                            {template.key}
                                        </span>
                                    </div>

                                    <h3 className="mt-3 text-sm font-bold text-slate-900">
                                        {template.name}
                                    </h3>
                                    <p className="mt-1 line-clamp-2 text-xs text-slate-500">
                                        {template.description ||
                                            'Template bawaan standar untuk alur bisnis dan formulir reservasi.'}
                                    </p>

                                    <div className="mt-4 flex flex-wrap items-center gap-2">
                                        <Badge variant="queuing" size="sm">
                                            Industri: {template.business_type}
                                        </Badge>
                                        <span className="rounded-full bg-slate-100 px-2.5 py-0.5 font-mono text-[10px] font-semibold text-slate-700">
                                            v{latestVer ? latestVer.version : '1.0.0'}
                                        </span>
                                        <span className="text-[11px] text-slate-400">
                                            {versionCount} versi
                                        </span>
                                    </div>
                                </div>

                                <div className="mt-5 border-t border-slate-100 pt-4">
                                    <Link
                                        href={`/admin/templates/${template.id}`}
                                        className="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 transition-colors hover:border-slate-300 hover:bg-slate-100"
                                    >
                                        <span>Kelola & Rilis Versi</span>
                                        <ArrowRight className="h-3.5 w-3.5" />
                                    </Link>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {filteredTemplates.length === 0 && (
                    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-white py-12 text-center">
                        <Layers className="h-10 w-10 text-slate-300" />
                        <h4 className="mt-3 text-sm font-semibold text-slate-800">
                            Tidak ada template ditemukan
                        </h4>
                        <p className="mt-1 max-w-sm text-xs text-slate-500">
                            Coba ubah kata kunci pencarian atau klik tombol &ldquo;Sinkron Template Standar&rdquo; untuk mengisi template bawaan sistem.
                        </p>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
