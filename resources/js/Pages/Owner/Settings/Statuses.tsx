import { router, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowDown,
    ArrowUp,
    Calendar,
    Check,
    CheckCircle2,
    CheckCheck,
    CircleDot,
    Clock,
    Edit3,
    Layers,
    Play,
    Plus,
    RotateCcw,
    Trash2,
    UserCheck,
    UserX,
    XCircle,
} from 'lucide-react';
import React, { useState } from 'react';
import {
    Badge,
    Button,
    Input,
    Modal,
    Textarea,
    useToast,
} from '../../../Components/ui';
import { OwnerLayout } from '../../../Layouts/OwnerLayout';
import { BookingStatusItem } from '../Bookings/types';

interface SystemCategoryOption {
    value: string;
    label: string;
}

interface StatusesPageProps {
    statuses: BookingStatusItem[];
    systemCategories: SystemCategoryOption[];
}

const ICON_PRESETS = [
    { id: 'clock', label: 'Jam / Tunggu', icon: Clock },
    { id: 'check-circle', label: 'Centang / Konfirmasi', icon: CheckCircle2 },
    { id: 'user-check', label: 'Customer Hadir', icon: UserCheck },
    { id: 'play', label: 'Sedang Proses', icon: Play },
    { id: 'check-check', label: 'Selesai', icon: CheckCheck },
    { id: 'x-circle', label: 'Batal', icon: XCircle },
    { id: 'user-x', label: 'No-Show', icon: UserX },
    { id: 'calendar', label: 'Kalender', icon: Calendar },
    { id: 'alert-circle', label: 'Peringatan', icon: AlertCircle },
];

const COLOR_PRESETS = [
    { hex: '#64748b', name: 'Slate' },
    { hex: '#2563eb', name: 'Blue' },
    { hex: '#0284c7', name: 'Sky' },
    { hex: '#d97706', name: 'Amber' },
    { hex: '#059669', name: 'Emerald' },
    { hex: '#dc2626', name: 'Rose' },
    { hex: '#e11d48', name: 'Red' },
    { hex: '#7c3aed', name: 'Violet' },
];

export const renderStatusIcon = (iconName?: string, className = 'h-4 w-4') => {
    switch (iconName?.toLowerCase()) {
        case 'clock':
            return <Clock className={className} />;
        case 'check-circle':
        case 'checkcircle':
            return <CheckCircle2 className={className} />;
        case 'user-check':
        case 'usercheck':
            return <UserCheck className={className} />;
        case 'play':
            return <Play className={className} />;
        case 'check-check':
        case 'checkcheck':
            return <CheckCheck className={className} />;
        case 'x-circle':
        case 'xcircle':
            return <XCircle className={className} />;
        case 'user-x':
        case 'userx':
            return <UserX className={className} />;
        case 'alert-circle':
            return <AlertCircle className={className} />;
        case 'calendar':
            return <Calendar className={className} />;
        default:
            return <CircleDot className={className} />;
    }
};

export default function StatusesPage({
    statuses: initialStatuses,
    systemCategories,
}: StatusesPageProps) {
    const toast = useToast();
    const [statuses, setStatuses] =
        useState<BookingStatusItem[]>(initialStatuses);
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingStatus, setEditingStatus] =
        useState<BookingStatusItem | null>(null);

    // Form state
    const {
        data: formData,
        setData: setFormData,
        post,
        put,
        processing,
        reset,
    } = useForm<{
        name: string;
        category: string;
        color: string;
        icon: string;
        description: string;
        is_active: boolean;
    }>({
        name: '',
        category: 'CONFIRMED',
        color: '#2563eb',
        icon: 'check-circle',
        description: '',
        is_active: true,
    });

    const openCreateModal = () => {
        reset();
        setEditingStatus(null);
        setFormData({
            name: '',
            category: 'CONFIRMED',
            color: '#2563eb',
            icon: 'check-circle',
            description: '',
            is_active: true,
        });
        setIsCreateOpen(true);
    };

    const openEditModal = (status: BookingStatusItem) => {
        setEditingStatus(status);
        setFormData({
            name: status.name,
            category: status.category,
            color: status.color || '#2563eb',
            icon: status.icon || 'circle',
            description: status.description || '',
            is_active: status.is_active,
        });
        setIsCreateOpen(true);
    };

    const handleFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (editingStatus) {
            put(route('owner.settings.statuses.update', editingStatus.id), {
                onSuccess: () => {
                    toast.success(
                        `Status '${formData.name}' berhasil diperbarui.`
                    );
                    setIsCreateOpen(false);
                },
                onError: (errors) => {
                    toast.error(
                        errors.name ||
                            errors.category ||
                            'Gagal menyimpan status.'
                    );
                },
            });
        } else {
            post(route('owner.settings.statuses.store'), {
                onSuccess: () => {
                    toast.success(
                        `Status '${formData.name}' berhasil ditambahkan.`
                    );
                    setIsCreateOpen(false);
                },
                onError: (errors) => {
                    toast.error(
                        errors.name ||
                            errors.category ||
                            'Gagal menambahkan status.'
                    );
                },
            });
        }
    };

    const handleDeleteStatus = (status: BookingStatusItem) => {
        if (!confirm(`Hapus status kustom '${status.name}'?`)) return;

        router.delete(route('owner.settings.statuses.destroy', status.id), {
            onSuccess: () => {
                toast.success(`Status '${status.name}' berhasil dihapus.`);
            },
            onError: (errors) => {
                toast.error(errors.status || 'Gagal menghapus status.');
            },
        });
    };

    const handleSeedDefaults = () => {
        if (
            !confirm(
                'Pasang ulang status default? Status kustom yang ada akan disinkronkan kembali.'
            )
        )
            return;

        router.post(
            route('owner.settings.statuses.seed-defaults'),
            {},
            {
                onSuccess: () => {
                    toast.success('Status default berhasil dipasang.');
                },
            }
        );
    };

    const handleMove = (index: number, direction: 'up' | 'down') => {
        const targetIndex = direction === 'up' ? index - 1 : index + 1;
        if (targetIndex < 0 || targetIndex >= statuses.length) return;

        const newStatuses = [...statuses];
        const temp = newStatuses[index];
        newStatuses[index] = newStatuses[targetIndex];
        newStatuses[targetIndex] = temp;

        setStatuses(newStatuses);

        router.post(
            route('owner.settings.statuses.reorder'),
            {
                ordered_ids: newStatuses.map((s) => s.id),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Urutan status diperbarui.');
                },
            }
        );
    };

    return (
        <OwnerLayout title="Status Booking & Kanban">
            <div className="mx-auto max-w-5xl space-y-6 pb-12">
                {/* Header Section */}
                <div className="flex flex-col justify-between gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-xl font-bold tracking-tight text-slate-900">
                            Status Booking & Alur Kanban
                        </h1>
                        <p className="mt-1 text-xs text-slate-500">
                            Kustomisasi tahapan alur booking usaha Anda (PRD 24,
                            25, 213). Setiap status kustom dipetakan ke kategori
                            sistem formal.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            variant="secondary"
                            onClick={handleSeedDefaults}
                            leftIcon={<RotateCcw className="h-3.5 w-3.5" />}
                        >
                            Reset Default
                        </Button>
                        <Button
                            variant="primary"
                            onClick={openCreateModal}
                            leftIcon={<Plus className="h-3.5 w-3.5" />}
                        >
                            Tambah Status
                        </Button>
                    </div>
                </div>

                {/* Status Table Card */}
                <div className="rounded-[12px] border border-slate-200 bg-white shadow-none">
                    <div className="border-b border-slate-100 px-5 py-3.5">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2 text-xs font-semibold text-slate-800">
                                <Layers className="h-4 w-4 text-blue-600" />
                                <span>
                                    Daftar Tahapan Kolom Kanban (
                                    {statuses.length})
                                </span>
                            </div>
                            <span className="text-[11px] text-slate-400">
                                Urutan menentukan tata letak kolom kiri ke kanan
                                pada Kanban
                            </span>
                        </div>
                    </div>

                    <div className="divide-y divide-slate-100">
                        {statuses.length === 0 ? (
                            <div className="p-8 text-center text-xs text-slate-400">
                                Belum ada status booking yang dikonfigurasi.
                                Klik &quot;Reset Default&quot; untuk memasang
                                template awal.
                            </div>
                        ) : (
                            statuses.map((s, idx) => (
                                <div
                                    key={s.id}
                                    className="flex items-center justify-between p-4 transition-colors hover:bg-slate-50/60"
                                >
                                    {/* Left: Reorder & Status Identity */}
                                    <div className="flex items-center gap-3">
                                        <div className="flex flex-col gap-0.5">
                                            <button
                                                type="button"
                                                disabled={idx === 0}
                                                onClick={() =>
                                                    handleMove(idx, 'up')
                                                }
                                                className="rounded p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-700 disabled:opacity-20"
                                                title="Pindah ke kiri / atas"
                                            >
                                                <ArrowUp className="h-3 w-3" />
                                            </button>
                                            <button
                                                type="button"
                                                disabled={
                                                    idx === statuses.length - 1
                                                }
                                                onClick={() =>
                                                    handleMove(idx, 'down')
                                                }
                                                className="rounded p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-700 disabled:opacity-20"
                                                title="Pindah ke kanan / bawah"
                                            >
                                                <ArrowDown className="h-3 w-3" />
                                            </button>
                                        </div>

                                        <div
                                            className="flex h-9 w-9 shrink-0 items-center justify-center rounded-[8px] border"
                                            style={{
                                                borderColor: `${s.color}30`,
                                                backgroundColor: `${s.color}15`,
                                                color: s.color,
                                            }}
                                        >
                                            {renderStatusIcon(
                                                s.icon,
                                                'h-4 w-4'
                                            )}
                                        </div>

                                        <div>
                                            <div className="flex items-center gap-2">
                                                <span className="text-xs font-semibold text-slate-900">
                                                    {s.name}
                                                </span>
                                                <span className="font-mono text-[10px] text-slate-400">
                                                    ({s.slug})
                                                </span>
                                                {!s.is_active && (
                                                    <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-medium text-slate-500">
                                                        Nonaktif
                                                    </span>
                                                )}
                                            </div>
                                            <div className="mt-0.5 text-[11px] text-slate-500">
                                                {s.description ||
                                                    'Tidak ada keterangan khusus.'}
                                            </div>
                                        </div>
                                    </div>

                                    {/* Right: Category badge & Actions */}
                                    <div className="flex items-center gap-4">
                                        <div className="text-right">
                                            <div className="text-[10px] text-slate-400">
                                                Kategori Sistem:
                                            </div>
                                            <Badge
                                                variant="neutral"
                                                className="mt-0.5 font-mono text-[10px]"
                                            >
                                                {s.category}
                                            </Badge>
                                        </div>

                                        <div className="flex items-center gap-1 border-l border-slate-100 pl-3">
                                            <button
                                                type="button"
                                                onClick={() => openEditModal(s)}
                                                className="rounded-[6px] border border-slate-200 p-1.5 text-slate-600 hover:border-slate-300 hover:bg-slate-100"
                                                title="Sunting Status"
                                            >
                                                <Edit3 className="h-3.5 w-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    handleDeleteStatus(s)
                                                }
                                                className="rounded-[6px] border border-rose-200 p-1.5 text-rose-600 hover:bg-rose-50"
                                                title="Hapus Status"
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </div>

                {/* Guard & Transition Info Note (PRD 213) */}
                <div className="rounded-[10px] border border-slate-200 bg-white p-4">
                    <h3 className="flex items-center gap-1.5 text-xs font-semibold text-slate-800">
                        <AlertCircle className="h-4 w-4 text-blue-600" />
                        <span>
                            Integritas Mesin Status & Guard Validasi (PRD 213)
                        </span>
                    </h3>
                    <p className="mt-1 text-xs text-slate-500">
                        Nama dan tampilan status dapat disesuaikan untuk
                        kebutuhan bisnis Anda. Namun perpindahan status pada
                        Kanban tetap tunduk pada aturan validasi mesin formal:
                    </p>
                    <ul className="mt-2 list-inside list-disc space-y-1 text-xs text-slate-600">
                        <li>
                            <strong className="font-semibold text-slate-800">
                                Guard Pembayaran (PRD 24):
                            </strong>{' '}
                            Perpindahan dari <em>PENDING</em> ke{' '}
                            <em>CONFIRMED</em> tidak diperbolehkan jika
                            pemesanan memerlukan pembayaran dan statusnya masih{' '}
                            <em>UNPAID</em>.
                        </li>
                        <li>
                            <strong className="font-semibold text-slate-800">
                                Status Akhir:
                            </strong>{' '}
                            Status <em>COMPLETED</em>, <em>CANCELLED</em>, dan{' '}
                            <em>EXPIRED</em> adalah status final dan tidak dapat
                            digeser kembali ke status lain pada papan Kanban
                            biasa.
                        </li>
                        <li>
                            <strong className="font-semibold text-slate-800">
                                Alokasi Resource:
                            </strong>{' '}
                            Status pembatalan atau no-show otomatis melepaskan
                            slot resource yang ditahan secara aman di database.
                        </li>
                    </ul>
                </div>
            </div>

            {/* Create / Edit Modal */}
            <Modal
                isOpen={isCreateOpen}
                onClose={() => setIsCreateOpen(false)}
                title={
                    editingStatus
                        ? 'Sunting Status Booking'
                        : 'Tambah Status Booking Kustom'
                }
                size="md"
            >
                <form onSubmit={handleFormSubmit} className="space-y-4">
                    <div>
                        <label className="mb-1 block text-xs font-medium text-slate-700">
                            Nama Label Status{' '}
                            <span className="text-rose-500">*</span>
                        </label>
                        <Input
                            value={formData.name}
                            onChange={(e) =>
                                setFormData('name', e.target.value)
                            }
                            placeholder="Contoh: Menunggu Konfirmasi, Sedang Dikerjakan"
                            required
                        />
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-medium text-slate-700">
                            Kategori Sistem Formal{' '}
                            <span className="text-rose-500">*</span>
                        </label>
                        <select
                            value={formData.category}
                            onChange={(e) =>
                                setFormData('category', e.target.value)
                            }
                            className="w-full rounded-[8px] border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:outline-none"
                            required
                        >
                            {systemCategories.map((c) => (
                                <option key={c.value} value={c.value}>
                                    {c.label} ({c.value})
                                </option>
                            ))}
                        </select>
                        <p className="mt-1 text-[11px] text-slate-500">
                            Menentukan aturan validasi, penahanan slot, dan
                            notifikasi yang berlaku.
                        </p>
                    </div>

                    {/* Icon Selection */}
                    <div>
                        <label className="mb-1.5 block text-xs font-medium text-slate-700">
                            Ikon Simbol (Lucide Stroke Icon)
                        </label>
                        <div className="grid grid-cols-3 gap-2">
                            {ICON_PRESETS.map((ic) => {
                                const IconComponent = ic.icon;
                                const isSelected = formData.icon === ic.id;
                                return (
                                    <button
                                        key={ic.id}
                                        type="button"
                                        onClick={() =>
                                            setFormData('icon', ic.id)
                                        }
                                        className={`flex items-center gap-1.5 rounded-[8px] border px-2.5 py-1.5 text-left text-xs transition-colors ${
                                            isSelected
                                                ? 'border-blue-600 bg-blue-50/50 font-semibold text-blue-700'
                                                : 'border-slate-200 text-slate-700 hover:bg-slate-50'
                                        }`}
                                    >
                                        <IconComponent className="h-3.5 w-3.5 shrink-0" />
                                        <span className="truncate text-[11px]">
                                            {ic.label}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* Accent Color Preset */}
                    <div>
                        <label className="mb-1.5 block text-xs font-medium text-slate-700">
                            Warna Aksen Indikator
                        </label>
                        <div className="flex flex-wrap items-center gap-2">
                            {COLOR_PRESETS.map((cp) => {
                                const isSelected =
                                    formData.color.toLowerCase() ===
                                    cp.hex.toLowerCase();
                                return (
                                    <button
                                        key={cp.hex}
                                        type="button"
                                        onClick={() =>
                                            setFormData('color', cp.hex)
                                        }
                                        className={`flex h-7 items-center gap-1.5 rounded-full border px-2.5 text-xs transition-transform ${
                                            isSelected
                                                ? 'border-slate-800 ring-2 ring-blue-500'
                                                : 'border-slate-200 hover:scale-105'
                                        }`}
                                    >
                                        <span
                                            className="h-3.5 w-3.5 rounded-full"
                                            style={{ backgroundColor: cp.hex }}
                                        />
                                        <span className="text-[11px] font-medium text-slate-700">
                                            {cp.name}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-medium text-slate-700">
                            Keterangan / Catatan Alur
                        </label>
                        <Textarea
                            value={formData.description}
                            onChange={(e) =>
                                setFormData('description', e.target.value)
                            }
                            placeholder="Penjelasan kapan booking berada di status ini..."
                            rows={2}
                        />
                    </div>

                    <div className="flex items-center gap-2 pt-1">
                        <input
                            type="checkbox"
                            id="is_active"
                            checked={formData.is_active}
                            onChange={(e) =>
                                setFormData('is_active', e.target.checked)
                            }
                            className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                        />
                        <label
                            htmlFor="is_active"
                            className="text-xs font-medium text-slate-700"
                        >
                            Aktifkan status ini pada Kanban dan pemfilteran
                        </label>
                    </div>

                    <div className="flex justify-end gap-2 border-t border-slate-100 pt-4">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setIsCreateOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant="primary"
                            isLoading={processing}
                            leftIcon={<Check className="h-3.5 w-3.5" />}
                        >
                            Simpan Status
                        </Button>
                    </div>
                </form>
            </Modal>
        </OwnerLayout>
    );
}
