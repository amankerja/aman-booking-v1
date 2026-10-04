import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CalendarOff, Plus, Trash2 } from 'lucide-react';
import React, { useState } from 'react';
import {
    Button,
    ConfirmationDialog,
    Input,
    Modal,
    useToast,
} from '../../../Components/ui';
import { OwnerLayout } from '../../../Layouts/OwnerLayout';

interface TimeBlockRow {
    id: number;
    resource_id: number | null;
    resource_name: string;
    resource_code: string | null;
    start_at: string;
    end_at: string;
    start_formatted: string;
    end_formatted: string;
    reason: string;
    is_all_resources: boolean;
    created_at: string | null;
}

interface PaginatedTimeBlocks {
    data: TimeBlockRow[];
    current_page: number;
    last_page: number;
    total: number;
}

interface SimpleResource {
    id: number;
    name: string;
    code: string | null;
}

interface TimeBlocksProps {
    time_blocks: PaginatedTimeBlocks;
    resources: SimpleResource[];
    filters: {
        resource_id: number | null;
    };
}

export default function TimeBlocksIndex({
    time_blocks,
    resources,
    filters,
}: TimeBlocksProps) {
    const toast = useToast();

    const [isAddModalOpen, setIsAddModalOpen] = useState(false);
    const [resourceId, setResourceId] = useState<number | ''>(
        filters.resource_id ?? ''
    );
    const [isAllResources, setIsAllResources] = useState(false);
    const [startAt, setStartAt] = useState('');
    const [endAt, setEndAt] = useState('');
    const [reason, setReason] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const [deleteTarget, setDeleteTarget] = useState<TimeBlockRow | null>(null);

    const handleFilterChange = (id: number | '') => {
        setResourceId(id);
        router.get(
            '/app/time-blocks',
            { resource_id: id || undefined },
            { preserveState: true, replace: true }
        );
    };

    const handleCreateBlock = (e: React.FormEvent) => {
        e.preventDefault();
        if (!startAt || !endAt || !reason.trim()) {
            toast.error(
                'Lengkapi tanggal mulai, tanggal selesai, dan alasan block.'
            );
            return;
        }

        setIsSubmitting(true);
        router.post(
            '/app/time-blocks',
            {
                resource_id: isAllResources || !resourceId ? null : resourceId,
                is_all_resources: isAllResources,
                start_at: startAt,
                end_at: endAt,
                reason: reason.trim(),
            },
            {
                onSuccess: () => {
                    setIsAddModalOpen(false);
                    setStartAt('');
                    setEndAt('');
                    setReason('');
                    setIsAllResources(false);
                    toast.success('Block waktu / cuti berhasil ditambahkan.');
                },
                onError: (err) => {
                    toast.error(Object.values(err)[0] as string);
                },
                onFinish: () => setIsSubmitting(false),
            }
        );
    };

    const handleDeleteConfirm = () => {
        if (!deleteTarget) return;
        router.delete(`/app/time-blocks/${deleteTarget.id}`, {
            onSuccess: () => {
                setDeleteTarget(null);
                toast.success('Block waktu / cuti berhasil dihapus.');
            },
            onError: (err) => {
                toast.error(Object.values(err)[0] as string);
            },
        });
    };

    return (
        <OwnerLayout
            title="Cuti & Block Waktu Manual"
            breadcrumbs={[
                { label: 'Dashboard', href: '/app/dashboard' },
                { label: 'Resource & Tim', href: '/app/resources' },
                { label: 'Cuti & Block Waktu' },
            ]}
            actions={
                <div className="flex items-center gap-2">
                    <Link href="/app/resources">
                        <Button
                            variant="outline"
                            size="sm"
                            leftIcon={<ArrowLeft className="h-4 w-4" />}
                        >
                            Kembali ke Resource
                        </Button>
                    </Link>
                    <Button
                        variant="primary"
                        size="sm"
                        onClick={() => setIsAddModalOpen(true)}
                        leftIcon={<Plus className="h-4 w-4" />}
                    >
                        Tambah Block Waktu
                    </Button>
                </div>
            }
        >
            <Head title="Cuti & Block Waktu - AMAN BOOKING" />

            <div className="space-y-6">
                {/* Intro Card */}
                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                                <CalendarOff className="h-5 w-5" />
                            </div>
                            <div>
                                <h3 className="text-sm font-semibold text-slate-900">
                                    Manual Time Block & Cuti Pegawai
                                </h3>
                                <p className="text-xs text-slate-500">
                                    Blokir jam tertentu untuk cuti, sakit,
                                    maintenance alat, renovasi ruangan, atau
                                    hari libur mendadak.
                                </p>
                            </div>
                        </div>

                        {/* Filter by Resource */}
                        <div className="flex items-center gap-2">
                            <span className="text-xs text-slate-500">
                                Filter Resource:
                            </span>
                            <select
                                value={resourceId}
                                onChange={(e) =>
                                    handleFilterChange(
                                        e.target.value
                                            ? Number(e.target.value)
                                            : ''
                                    )
                                }
                                className="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-700 focus:border-blue-500 focus:outline-none"
                            >
                                <option value="">Semua Resource</option>
                                {resources.map((r) => (
                                    <option key={r.id} value={r.id}>
                                        {r.name} {r.code ? `(${r.code})` : ''}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Target Resource
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Alasan / Catatan
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Waktu Mulai
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Waktu Selesai
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 text-slate-700">
                                {time_blocks.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-4 py-12 text-center text-slate-500"
                                        >
                                            <CalendarOff className="mx-auto mb-2 h-8 w-8 text-slate-300" />
                                            <p className="text-sm font-medium text-slate-700">
                                                Tidak ada block waktu atau cuti
                                                yang aktif.
                                            </p>
                                            <p className="mt-1 text-xs text-slate-400">
                                                Semua resource berjalan sesuai
                                                jadwal mingguan normal
                                                masing-masing.
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    time_blocks.data.map((tb) => (
                                        <tr
                                            key={tb.id}
                                            className="transition-colors hover:bg-slate-50/70"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    {tb.is_all_resources ? (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700">
                                                            Semua Resource
                                                            (Tutup)
                                                        </span>
                                                    ) : (
                                                        <div className="font-semibold text-slate-900">
                                                            {tb.resource_name}
                                                            {tb.resource_code && (
                                                                <span className="ml-1.5 rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] text-slate-600">
                                                                    {
                                                                        tb.resource_code
                                                                    }
                                                                </span>
                                                            )}
                                                        </div>
                                                    )}
                                                </div>
                                            </td>

                                            <td className="px-4 py-3 font-medium text-slate-800">
                                                {tb.reason}
                                            </td>

                                            <td className="px-4 py-3 font-mono text-[11px] text-slate-600">
                                                {tb.start_formatted}
                                            </td>

                                            <td className="px-4 py-3 font-mono text-[11px] text-slate-600">
                                                {tb.end_formatted}
                                            </td>

                                            <td className="px-4 py-3 text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    title="Hapus Block Waktu"
                                                    onClick={() =>
                                                        setDeleteTarget(tb)
                                                    }
                                                >
                                                    <Trash2 className="h-3.5 w-3.5 text-rose-600" />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Modal Tambah Block Waktu */}
            <Modal
                isOpen={isAddModalOpen}
                onClose={() => setIsAddModalOpen(false)}
                title="Tambah Block Waktu / Cuti"
                size="md"
            >
                <form onSubmit={handleCreateBlock} className="space-y-4">
                    <div className="flex items-center gap-2 rounded-lg border border-slate-100 bg-slate-50 p-2.5">
                        <input
                            type="checkbox"
                            id="modal_is_all"
                            checked={isAllResources}
                            onChange={(e) => {
                                setIsAllResources(e.target.checked);
                                if (e.target.checked) setResourceId('');
                            }}
                            className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                        />
                        <label
                            htmlFor="modal_is_all"
                            className="cursor-pointer text-xs font-medium text-slate-800"
                        >
                            Terapkan ke SEMUA Resource (Seluruh Operasional
                            Ditutup)
                        </label>
                    </div>

                    {!isAllResources && (
                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Pilih Resource Spesifik{' '}
                                <span className="text-rose-500">*</span>
                            </label>
                            <select
                                value={resourceId}
                                onChange={(e) =>
                                    setResourceId(
                                        e.target.value
                                            ? Number(e.target.value)
                                            : ''
                                    )
                                }
                                className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-blue-500 focus:outline-none"
                                required={!isAllResources}
                            >
                                <option value="">-- Pilih Resource --</option>
                                {resources.map((r) => (
                                    <option key={r.id} value={r.id}>
                                        {r.name} {r.code ? `(${r.code})` : ''}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Waktu Mulai{' '}
                                <span className="text-rose-500">*</span>
                            </label>
                            <Input
                                type="datetime-local"
                                value={startAt}
                                onChange={(e) => setStartAt(e.target.value)}
                                required
                                className="mt-1 text-xs"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Waktu Selesai{' '}
                                <span className="text-rose-500">*</span>
                            </label>
                            <Input
                                type="datetime-local"
                                value={endAt}
                                onChange={(e) => setEndAt(e.target.value)}
                                required
                                className="mt-1 text-xs"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-slate-700">
                            Alasan Block / Cuti{' '}
                            <span className="text-rose-500">*</span>
                        </label>
                        <Input
                            placeholder="Contoh: Cuti Tahunan, Sakit, Maintenance Alat, Renovasi Ruang"
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            required
                            className="mt-1 text-xs"
                        />
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setIsAddModalOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant="primary"
                            size="sm"
                            isLoading={isSubmitting}
                        >
                            Simpan Block Waktu
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Confirm Delete */}
            <ConfirmationDialog
                isOpen={!!deleteTarget}
                onClose={() => setDeleteTarget(null)}
                onConfirm={handleDeleteConfirm}
                title="Hapus Block Waktu?"
                description={`Apakah Anda yakin ingin menghapus block waktu '${deleteTarget?.reason}'? Slot waktu ini akan kembali tersedia untuk booking publik.`}
                confirmText="Hapus"
                variant="danger"
            />
        </OwnerLayout>
    );
}
