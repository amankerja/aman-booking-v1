import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    Edit3,
    GitFork,
    Plus,
    Sparkles,
    Trash2,
} from 'lucide-react';
import React, { useState } from 'react';
import { Button } from '../../../Components/ui/Button';
import { Input } from '../../../Components/ui/Input';
import { Modal } from '../../../Components/ui/Modal';
import { Textarea } from '../../../Components/ui/Textarea';
import { useToast } from '../../../Components/ui/Toast';
import { OwnerLayout } from '../../../Layouts/OwnerLayout';
import { BusinessPresetWorkflow, WorkflowItem } from './types';

interface ServiceOption {
    id: number;
    name: string;
}

interface WorkflowIndexProps {
    workflows: WorkflowItem[];
    services: ServiceOption[];
    presets: BusinessPresetWorkflow[];
}

export default function WorkflowIndexPage({
    workflows,
    services,
    presets,
}: WorkflowIndexProps) {
    const toast = useToast();

    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isPresetModalOpen, setIsPresetModalOpen] = useState(false);
    const [editingWorkflow, setEditingWorkflow] = useState<WorkflowItem | null>(null);

    const form = useForm({
        name: '',
        description: '',
        service_id: '',
        is_default: false,
        is_active: true,
    });

    const handleOpenCreateModal = (item?: WorkflowItem) => {
        if (item) {
            setEditingWorkflow(item);
            form.setData({
                name: item.name,
                description: item.description || '',
                service_id: item.service_id ? String(item.service_id) : '',
                is_default: item.is_default,
                is_active: item.is_active,
            });
        } else {
            setEditingWorkflow(null);
            form.setData({
                name: '',
                description: '',
                service_id: '',
                is_default: workflows.length === 0,
                is_active: true,
            });
        }
        setIsCreateModalOpen(true);
    };

    const handleSaveWorkflow = (e: React.FormEvent) => {
        e.preventDefault();

        const payload = {
            name: form.data.name,
            description: form.data.description || null,
            service_id: form.data.service_id ? Number(form.data.service_id) : null,
            is_default: Boolean(form.data.is_default),
            is_active: Boolean(form.data.is_active),
        };

        if (editingWorkflow) {
            router.put(`/app/workflows/${editingWorkflow.id}`, payload, {
                onSuccess: () => {
                    setIsCreateModalOpen(false);
                    toast.success('Pengaturan alur kerja berhasil diperbarui.');
                },
                onError: (errs) => {
                    toast.error(
                        (Object.values(errs)[0] as string) || 'Gagal menyimpan alur kerja.'
                    );
                },
            });
        } else {
            router.post('/app/workflows', payload, {
                onSuccess: () => {
                    setIsCreateModalOpen(false);
                    toast.success('Alur kerja berhasil dibuat. Silakan buka kanvas.');
                },
                onError: (errs) => {
                    toast.error(
                        (Object.values(errs)[0] as string) || 'Gagal membuat alur kerja.'
                    );
                },
            });
        }
    };

    const handleDeleteWorkflow = (item: WorkflowItem) => {
        if (item.is_default) {
            toast.error('Alur kerja utama (default) tidak dapat dihapus.');
            return;
        }

        if (confirm(`Apakah Anda yakin ingin menghapus alur kerja '${item.name}'?`)) {
            router.delete(`/app/workflows/${item.id}`, {
                onSuccess: () => toast.success('Alur kerja berhasil dihapus.'),
                onError: (errs) => toast.error(Object.values(errs)[0] as string),
            });
        }
    };

    const handleInstallPreset = (presetKey: string) => {
        router.post(`/app/workflows/presets/${presetKey}`, {}, {
            onSuccess: () => {
                setIsPresetModalOpen(false);
                toast.success('Template alur kerja berhasil diinstal.');
            },
            onError: (errs) => toast.error(Object.values(errs)[0] as string),
        });
    };

    return (
        <OwnerLayout
            title="Otomasi & Alur Kerja (Workflow)"
            breadcrumbs={[
                { label: 'Dashboard', href: '/app/dashboard' },
                { label: 'Alur Kerja (Workflow)' },
            ]}
            actions={
                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setIsPresetModalOpen(true)}
                        className="gap-1.5 text-xs text-slate-700"
                    >
                        <Sparkles className="h-3.5 w-3.5 text-amber-500" /> Pasang Template Preset
                    </Button>
                    <Button
                        type="button"
                        variant="primary"
                        onClick={() => handleOpenCreateModal()}
                        className="gap-1.5 text-xs font-semibold"
                    >
                        <Plus className="h-3.5 w-3.5" /> Buat Alur Kerja Baru
                    </Button>
                </div>
            }
        >
            <Head title="Otomasi & Alur Kerja - AMAN BOOKING" />

            <div className="space-y-6">
                {/* Header Banner */}
                <div className="rounded-[12px] border border-slate-200 bg-white p-5 shadow-xs">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div className="flex items-start gap-3">
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] bg-blue-50 text-blue-600 border border-blue-100">
                                <GitFork className="h-5 w-5" />
                            </div>
                            <div>
                                <h2 className="text-sm font-bold text-slate-900">
                                    Visual Workflow Engine (XYFlow Canvas)
                                </h2>
                                <p className="mt-1 text-xs text-slate-500 max-w-2xl leading-relaxed">
                                    Otomatisasikan alur kerja bisnis Anda tanpa coding. Konfigurasikan
                                    pemicu saat booking dibuat, percabangan kondisi status pembayaran,
                                    notifikasi WhatsApp otomatis, hingga penugasan terapis dan staf.
                                </p>
                            </div>
                        </div>

                        <div className="flex items-center gap-2 shrink-0">
                            <div className="rounded-[8px] bg-slate-50 border border-slate-200 px-3 py-1.5 text-center">
                                <span className="block text-[10px] font-semibold text-slate-400 uppercase tracking-wider">
                                    Total Alur
                                </span>
                                <span className="text-base font-bold text-slate-900">
                                    {workflows.length}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Workflows Cards Grid */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {workflows.map((wf) => {
                        const hasPublished = Boolean(wf.current_version);
                        const hasDraft = Boolean(wf.draft_version);

                        return (
                            <div
                                key={wf.id}
                                className="flex flex-col justify-between rounded-[12px] border border-slate-200 bg-white p-4 shadow-xs transition-all hover:border-slate-300 hover:shadow-sm"
                            >
                                <div className="space-y-3">
                                    {/* Badges & default indicator */}
                                    <div className="flex items-center justify-between gap-2">
                                        <div className="flex items-center gap-1.5">
                                            {wf.is_default && (
                                                <span className="rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700">
                                                    DEFAULT
                                                </span>
                                            )}
                                            {wf.is_active ? (
                                                <span className="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                                                    AKTIF
                                                </span>
                                            ) : (
                                                <span className="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-bold text-slate-500">
                                                    NONAKTIF
                                                </span>
                                            )}
                                        </div>

                                        <div className="flex items-center gap-1">
                                            {hasPublished && (
                                                <span className="rounded-[6px] bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] font-bold text-slate-700">
                                                    V{wf.current_version?.version_number}
                                                </span>
                                            )}
                                            {hasDraft && (
                                                <span className="rounded-[6px] border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">
                                                    Draft Ada
                                                </span>
                                            )}
                                        </div>
                                    </div>

                                    {/* Title & Service */}
                                    <div>
                                        <h3 className="text-sm font-bold text-slate-900">
                                            {wf.name}
                                        </h3>
                                        <p className="mt-0.5 text-xs text-slate-500 line-clamp-2">
                                            {wf.description || 'Tidak ada deskripsi.'}
                                        </p>
                                    </div>

                                    <div className="rounded-[8px] border border-slate-100 bg-slate-50/80 p-2 text-xs">
                                        <span className="text-[11px] text-slate-400 block">
                                            Layanan Terikat:
                                        </span>
                                        <strong className="text-slate-800">
                                            {wf.service?.name
                                                ? wf.service.name
                                                : 'Seluruh Layanan (Fallback Default)'}
                                        </strong>
                                    </div>
                                </div>

                                {/* Actions */}
                                <div className="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
                                    <div className="flex items-center gap-1">
                                        <button
                                            type="button"
                                            onClick={() => handleOpenCreateModal(wf)}
                                            title="Ubah Pengaturan Meta"
                                            className="rounded-[6px] p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                                        >
                                            <Edit3 className="h-3.5 w-3.5" />
                                        </button>
                                        {!wf.is_default && (
                                            <button
                                                type="button"
                                                onClick={() => handleDeleteWorkflow(wf)}
                                                title="Hapus Alur Kerja"
                                                className="rounded-[6px] p-1.5 text-slate-500 hover:bg-rose-50 hover:text-rose-600"
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </button>
                                        )}
                                    </div>

                                    <Link
                                        href={`/app/workflows/${wf.id}/builder`}
                                        className="flex items-center gap-1.5 rounded-[8px] bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white shadow-2xs transition-colors hover:bg-blue-700"
                                    >
                                        Buka Kanvas <ArrowRight className="h-3.5 w-3.5" />
                                    </Link>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* Create / Edit Workflow Meta Modal */}
            <Modal
                isOpen={isCreateModalOpen}
                onClose={() => setIsCreateModalOpen(false)}
                title={
                    <div className="flex items-center gap-2 text-sm font-semibold text-slate-900">
                        <GitFork className="h-4 w-4 text-blue-600" />
                        <span>
                            {editingWorkflow ? 'Ubah Pengaturan Alur Kerja' : 'Buat Alur Kerja Baru'}
                        </span>
                    </div>
                }
                description="Tentukan nama alur kerja dan layanan yang terhubung."
                size="md"
            >
                <form onSubmit={handleSaveWorkflow} className="space-y-4 text-xs">
                    <div>
                        <label className="block text-xs font-medium text-slate-700 mb-1">
                            Nama Alur Kerja <span className="text-rose-500">*</span>
                        </label>
                        <Input
                            required
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="Contoh: Alur Booking Reguler Salon"
                            className="text-xs"
                        />
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-slate-700 mb-1">
                            Layanan Terikat (Opsional)
                        </label>
                        <select
                            value={form.data.service_id}
                            onChange={(e) => form.setData('service_id', e.target.value)}
                            className="w-full rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:outline-none"
                        >
                            <option value="">-- Seluruh Layanan (Alur Default) --</option>
                            {services.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.name}
                                </option>
                            ))}
                        </select>
                        <p className="mt-1 text-[11px] text-slate-400">
                            Kosongkan bila alur kerja ini berlaku sebagai aturan umum default untuk semua layanan.
                        </p>
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-slate-700 mb-1">
                            Deskripsi Singkat
                        </label>
                        <Textarea
                            rows={2}
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            placeholder="Catatan mengenai alur kerja ini..."
                            className="text-xs"
                        />
                    </div>

                    <div className="space-y-2 border-t border-slate-100 pt-3">
                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={form.data.is_default}
                                onChange={(e) => form.setData('is_default', e.target.checked)}
                                className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span className="text-xs text-slate-700 font-medium">
                                Jadikan sebagai Alur Kerja Utama (Default Tenant)
                            </span>
                        </label>

                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={form.data.is_active}
                                onChange={(e) => form.setData('is_active', e.target.checked)}
                                className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span className="text-xs text-slate-700 font-medium">
                                Aktifkan Alur Kerja Ini
                            </span>
                        </label>
                    </div>

                    <div className="flex justify-end gap-2 border-t border-slate-100 pt-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsCreateModalOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant="primary"
                            isLoading={form.processing}
                        >
                            Simpan & Buka Kanvas
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Presets Modal */}
            <Modal
                isOpen={isPresetModalOpen}
                onClose={() => setIsPresetModalOpen(false)}
                title={
                    <div className="flex items-center gap-2 text-sm font-semibold text-slate-900">
                        <Sparkles className="h-4 w-4 text-amber-500" />
                        <span>Katalog Template Alur Kerja Siap Pakai</span>
                    </div>
                }
                description="Pilih salah satu template industri bisnis yang telah diuji dan siap pakai."
                size="lg"
            >
                <div className="space-y-3 text-xs">
                    {presets.map((preset) => (
                        <div
                            key={preset.key}
                            className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-[10px] border border-slate-200 bg-slate-50/50 p-3.5 transition-colors hover:border-blue-400 hover:bg-blue-50/20"
                        >
                            <div>
                                <h4 className="font-bold text-slate-900 text-xs">{preset.name}</h4>
                                <p className="mt-1 text-[11px] text-slate-500 leading-relaxed max-w-md">
                                    {preset.description}
                                </p>
                            </div>

                            <Button
                                type="button"
                                variant="primary"
                                size="sm"
                                onClick={() => handleInstallPreset(preset.key)}
                                className="shrink-0 gap-1 text-xs"
                            >
                                Pasang Template <ArrowRight className="h-3 w-3" />
                            </Button>
                        </div>
                    ))}
                </div>
            </Modal>
        </OwnerLayout>
    );
}
