import { router, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    Clock,
    Filter,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import React, { useMemo, useState } from 'react';
import {
    Badge,
    Button,
    ConfirmationDialog,
    DataTable,
    Input,
    Modal,
    Select,
    Textarea,
    useToast,
} from '../../../Components/ui';
import { OwnerLayout } from '../../../Layouts/OwnerLayout';

interface CalendarExceptionItem {
    id: number;
    type: 'HOLIDAY' | 'BLACKOUT' | 'SPECIAL_OPEN' | 'SPECIAL_CLOSE';
    date: string;
    title: string;
    is_closed: boolean;
    open_time: string | null;
    close_time: string | null;
    note: string | null;
}

interface CalendarExceptionsProps {
    exceptions: CalendarExceptionItem[];
    business: {
        id: number;
        name: string;
        timezone: string;
    };
    types: Array<{
        value: string;
        label: string;
    }>;
}

export default function CalendarExceptions({
    exceptions,
    business,
    types,
}: CalendarExceptionsProps) {
    const toast = useToast();

    // Modal state for creating exception
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);

    // Delete confirmation state
    const [itemToDelete, setItemToDelete] =
        useState<CalendarExceptionItem | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);

    // Filter and search state
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedTypeFilter, setSelectedTypeFilter] = useState<string>('ALL');

    // Create Form
    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm({
            type: 'HOLIDAY',
            date: new Date().toISOString().slice(0, 10),
            title: '',
            is_closed: true,
            open_time: '09:00',
            close_time: '17:00',
            note: '',
        });

    const handleTypeChange = (newType: string) => {
        const isClosedByDefault =
            newType === 'HOLIDAY' || newType === 'BLACKOUT';
        setData((prev) => ({
            ...prev,
            type: newType,
            is_closed: isClosedByDefault,
            open_time: isClosedByDefault ? '' : prev.open_time || '09:00',
            close_time: isClosedByDefault ? '' : prev.close_time || '17:00',
        }));
    };

    const handleOpenCreateModal = () => {
        clearErrors();
        reset();
        setIsCreateModalOpen(true);
    };

    const handleCloseCreateModal = () => {
        setIsCreateModalOpen(false);
        reset();
        clearErrors();
    };

    const handleSubmitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('owner.settings.calendar.store'), {
            onSuccess: () => {
                setIsCreateModalOpen(false);
                reset();
                toast.success('Pengecualian kalender berhasil disimpan.');
            },
            onError: () => {
                toast.error(
                    'Gagal menyimpan pengecualian. Periksa form isian.'
                );
            },
        });
    };

    const handleConfirmDelete = () => {
        if (!itemToDelete) return;
        setIsDeleting(true);

        router.delete(
            route('owner.settings.calendar.destroy', itemToDelete.id),
            {
                onSuccess: () => {
                    setIsDeleting(false);
                    setItemToDelete(null);
                    toast.success('Pengecualian kalender berhasil dihapus.');
                },
                onError: () => {
                    setIsDeleting(false);
                    toast.error('Gagal menghapus pengecualian kalender.');
                },
            }
        );
    };

    const formatDateDisplay = (dateString: string) => {
        try {
            const date = new Date(dateString + 'T00:00:00');
            return new Intl.DateTimeFormat('id-ID', {
                weekday: 'long',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
            }).format(date);
        } catch {
            return dateString;
        }
    };

    const getTypeBadgeVariant = (type: string) => {
        switch (type) {
            case 'HOLIDAY':
                return 'breakdown';
            case 'BLACKOUT':
                return 'warning';
            case 'SPECIAL_OPEN':
                return 'active';
            case 'SPECIAL_CLOSE':
                return 'hauling';
            default:
                return 'neutral';
        }
    };

    const getTypeLabel = (type: string) => {
        const found = types.find((t) => t.value === type);
        return found ? found.label.split(' (')[0] : type;
    };

    // Filtered exceptions list
    const filteredExceptions = useMemo(() => {
        return exceptions.filter((item) => {
            const matchesQuery =
                searchQuery.trim() === '' ||
                item.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
                (item.note &&
                    item.note
                        .toLowerCase()
                        .includes(searchQuery.toLowerCase())) ||
                item.date.includes(searchQuery);

            const matchesType =
                selectedTypeFilter === 'ALL' ||
                item.type === selectedTypeFilter;

            return matchesQuery && matchesType;
        });
    }, [exceptions, searchQuery, selectedTypeFilter]);

    const tableColumns = [
        {
            key: 'date',
            header: 'Tanggal',
            width: '24%',
            render: (item: CalendarExceptionItem) => (
                <div className="space-y-0.5">
                    <div className="font-medium text-slate-900">
                        {formatDateDisplay(item.date)}
                    </div>
                    <div className="font-mono text-[11px] text-slate-400">
                        {item.date}
                    </div>
                </div>
            ),
        },
        {
            key: 'type',
            header: 'Tipe Pengecualian',
            width: '20%',
            render: (item: CalendarExceptionItem) => (
                <Badge variant={getTypeBadgeVariant(item.type)} size="sm">
                    {getTypeLabel(item.type)}
                </Badge>
            ),
        },
        {
            key: 'title',
            header: 'Judul & Catatan',
            width: '28%',
            render: (item: CalendarExceptionItem) => (
                <div className="space-y-0.5">
                    <div className="font-medium text-slate-900">
                        {item.title}
                    </div>
                    {item.note && (
                        <p className="line-clamp-2 text-[11px] text-slate-500">
                            {item.note}
                        </p>
                    )}
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Status Operasional',
            width: '18%',
            render: (item: CalendarExceptionItem) =>
                item.is_closed ? (
                    <Badge variant="breakdown" size="sm">
                        Toko Tutup Penuh
                    </Badge>
                ) : (
                    <div className="flex items-center gap-1.5 text-xs font-medium text-slate-700">
                        <Clock className="h-3.5 w-3.5 text-blue-600" />
                        <span>
                            {item.open_time} - {item.close_time}
                        </span>
                    </div>
                ),
        },
        {
            key: 'actions',
            header: 'Aksi',
            width: '10%',
            align: 'right' as const,
            render: (item: CalendarExceptionItem) => (
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-8 w-8 p-0 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                    onClick={() => setItemToDelete(item)}
                    aria-label={`Hapus ${item.title}`}
                >
                    <Trash2 className="h-4 w-4" />
                </Button>
            ),
        },
    ];

    return (
        <OwnerLayout
            title="Kalender & Hari Libur"
            subtitle="Atur hari libur nasional, pemblokiran tanggal tertentu (blackout), dan jam buka/tutup khusus."
        >
            <div className="mx-auto max-w-6xl space-y-6">
                {/* Header Information Card */}
                <div className="flex flex-col gap-4 rounded-[14px] border border-slate-200 bg-white p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-3.5">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                            <CalendarDays className="h-5 w-5" />
                        </div>
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <h3 className="text-sm font-semibold text-slate-900">
                                    Pengecualian Kalender ({business.name})
                                </h3>
                                <Badge variant="neutral" size="sm">
                                    Zona Waktu: {business.timezone}
                                </Badge>
                            </div>
                            <p className="text-xs text-slate-500">
                                Setiap entri di sini akan diprioritaskan di atas
                                jadwal mingguan reguler saat pelanggan mengecek
                                ketersediaan slot booking.
                            </p>
                        </div>
                    </div>
                    <Button
                        variant="primary"
                        onClick={handleOpenCreateModal}
                        leftIcon={<Plus className="h-4 w-4" />}
                    >
                        Tambah Pengecualian
                    </Button>
                </div>

                {/* Filter and Search Bar */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="relative w-full sm:w-72">
                        <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <Search className="h-4 w-4" />
                        </div>
                        <input
                            type="text"
                            placeholder="Cari berdasarkan judul atau tanggal..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="w-full rounded-lg border border-slate-200 bg-white py-2 pr-3 pl-9 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:ring-1 focus:ring-blue-600 focus:outline-none"
                        />
                    </div>

                    <div className="flex items-center gap-2">
                        <Filter className="h-3.5 w-3.5 text-slate-400" />
                        <span className="text-xs font-medium text-slate-600">
                            Filter Tipe:
                        </span>
                        <select
                            value={selectedTypeFilter}
                            onChange={(e) =>
                                setSelectedTypeFilter(e.target.value)
                            }
                            className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-800 focus:border-blue-600 focus:ring-1 focus:ring-blue-600 focus:outline-none"
                        >
                            <option value="ALL">
                                Semua Tipe ({exceptions.length})
                            </option>
                            <option value="HOLIDAY">Hari Libur</option>
                            <option value="BLACKOUT">Blackout Date</option>
                            <option value="SPECIAL_OPEN">
                                Jam Buka Khusus
                            </option>
                            <option value="SPECIAL_CLOSE">Tutup Awal</option>
                        </select>
                    </div>
                </div>

                {/* Exceptions Data Table */}
                <DataTable
                    columns={tableColumns}
                    data={filteredExceptions}
                    keyExtractor={(item) => item.id}
                    emptyText={
                        searchQuery || selectedTypeFilter !== 'ALL'
                            ? 'Tidak ada pengecualian yang cocok dengan filter yang dipilih.'
                            : 'Belum ada hari libur atau pengecualian kalender yang didaftarkan.'
                    }
                    emptyAction={
                        !searchQuery && selectedTypeFilter === 'ALL' ? (
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={handleOpenCreateModal}
                                leftIcon={<Plus className="h-3.5 w-3.5" />}
                            >
                                Buat Pengecualian Pertama
                            </Button>
                        ) : undefined
                    }
                />
            </div>

            {/* Modal Tambah Pengecualian */}
            <Modal
                isOpen={isCreateModalOpen}
                onClose={handleCloseCreateModal}
                title="Tambah Pengecualian Kalender"
                description="Tentukan tanggal libur atau pengaturan jam khusus yang berlaku."
                size="md"
                footer={
                    <>
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={handleCloseCreateModal}
                            disabled={processing}
                        >
                            Batal
                        </Button>
                        <Button
                            variant="primary"
                            size="sm"
                            onClick={handleSubmitCreate}
                            isLoading={processing}
                        >
                            Simpan Pengecualian
                        </Button>
                    </>
                }
            >
                <form onSubmit={handleSubmitCreate} className="space-y-4">
                    <Select
                        label="Tipe Pengecualian"
                        value={data.type}
                        onChange={(e) => handleTypeChange(e.target.value)}
                        error={errors.type}
                        required
                    >
                        {types.map((t) => (
                            <option key={t.value} value={t.value}>
                                {t.label}
                            </option>
                        ))}
                    </Select>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Input
                            label="Tanggal"
                            type="date"
                            value={data.date}
                            onChange={(e) => setData('date', e.target.value)}
                            error={errors.date}
                            required
                        />
                        <Input
                            label="Judul / Nama Acara"
                            placeholder="cth: Hari Raya Idul Fitri"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            error={errors.title}
                            required
                        />
                    </div>

                    {/* Tutup Penuh Toggle */}
                    <div className="rounded-lg border border-slate-200 bg-slate-50/70 p-3.5">
                        <label className="flex cursor-pointer items-start gap-3">
                            <input
                                type="checkbox"
                                checked={data.is_closed}
                                onChange={(e) =>
                                    setData('is_closed', e.target.checked)
                                }
                                className="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-600"
                            />
                            <div>
                                <span className="text-xs font-semibold text-slate-800">
                                    Tutup Penuh Sepanjang Hari
                                </span>
                                <p className="text-[11px] text-slate-500">
                                    Bisnis tidak akan menerima pesanan apa pun
                                    pada tanggal ini.
                                </p>
                            </div>
                        </label>
                    </div>

                    {/* Jam Buka Khusus (jika tidak tutup penuh) */}
                    {!data.is_closed && (
                        <div className="space-y-2 rounded-lg border border-blue-100 bg-blue-50/40 p-3.5">
                            <div className="flex items-center gap-1.5 text-xs font-semibold text-slate-900">
                                <Clock className="h-3.5 w-3.5 text-blue-600" />
                                <span>Jam Operasional Khusus</span>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <Input
                                    label="Jam Buka"
                                    type="time"
                                    value={data.open_time}
                                    onChange={(e) =>
                                        setData('open_time', e.target.value)
                                    }
                                    error={errors.open_time}
                                    required={!data.is_closed}
                                />
                                <Input
                                    label="Jam Tutup"
                                    type="time"
                                    value={data.close_time}
                                    onChange={(e) =>
                                        setData('close_time', e.target.value)
                                    }
                                    error={errors.close_time}
                                    required={!data.is_closed}
                                />
                            </div>
                        </div>
                    )}

                    <Textarea
                        label="Catatan / Keterangan (Opsional)"
                        placeholder="Tambahkan informasi internal atau keterangan khusus untuk tim..."
                        value={data.note}
                        onChange={(e) => setData('note', e.target.value)}
                        error={errors.note}
                        rows={3}
                    />
                </form>
            </Modal>

            {/* Confirmation Dialog Delete */}
            <ConfirmationDialog
                isOpen={itemToDelete !== null}
                onClose={() => setItemToDelete(null)}
                onConfirm={handleConfirmDelete}
                title="Hapus Pengecualian Kalender"
                message={
                    itemToDelete ? (
                        <span>
                            Apakah Anda yakin ingin menghapus pengecualian{' '}
                            <strong>{itemToDelete.title}</strong> pada tanggal{' '}
                            <strong>{itemToDelete.date}</strong>? Jadwal
                            operasional akan kembali mengikuti jam kerja
                            mingguan.
                        </span>
                    ) : (
                        ''
                    )
                }
                confirmLabel="Hapus"
                cancelLabel="Batal"
                variant="danger"
                isLoading={isDeleting}
            />
        </OwnerLayout>
    );
}
