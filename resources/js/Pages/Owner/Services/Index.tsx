import { Link, router } from '@inertiajs/react';
import {
    Archive,
    ArchiveRestore,
    Clock,
    Edit2,
    FolderPlus,
    Layers,
    Plus,
    Search,
    Tag,
    Trash2,
    Users,
} from 'lucide-react';
import React, { useMemo, useState } from 'react';
import {
    Badge,
    Button,
    ConfirmationDialog,
    DataTable,
    Input,
    Modal,
    useToast,
} from '../../../Components/ui';
import { OwnerLayout } from '../../../Layouts/OwnerLayout';

interface ServiceCategoryItem {
    id: number;
    name: string;
    slug: string;
    order: number;
}

interface ServiceVariantItem {
    id: number;
    name: string;
    price_idr: number;
    duration_minutes: number;
    is_active: boolean;
}

interface ServiceAddonItem {
    id: number;
    name: string;
    price_idr: number;
    duration_minutes: number;
    is_active: boolean;
}

interface ServiceItem {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    description: string | null;
    image_url: string | null;
    price_idr: number;
    duration_type: 'FIXED' | 'PER_QUANTITY' | 'PER_UNIT_SIZE' | 'VARIABLE';
    duration_minutes: number;
    duration_rule: Record<string, unknown> | null;
    buffer_before: number;
    buffer_after: number;
    total_duration: number;
    capacity: number;
    is_active: boolean;
    is_featured: boolean;
    is_archived: boolean;
    archived_at: string | null;
    category: { id: number; name: string } | null;
    variants_count: number;
    addons_count: number;
    variants: ServiceVariantItem[];
    addons: ServiceAddonItem[];
}

interface ServicesIndexProps {
    services: ServiceItem[];
    categories: ServiceCategoryItem[];
    filters: {
        search: string;
        category_id: number | null;
        status: 'ALL' | 'ACTIVE' | 'ARCHIVED';
    };
    usage: {
        current: number;
        limit: number;
        remaining: number;
        is_unlimited: boolean;
    };
    business: {
        id: number;
        name: string;
    };
}

export default function ServicesIndex({
    services,
    categories,
    filters,
    usage,
    business,
}: ServicesIndexProps) {
    const toast = useToast();

    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [selectedCategory, setSelectedCategory] = useState<number | null>(
        filters.category_id
    );
    const [statusFilter, setStatusFilter] = useState<
        'ALL' | 'ACTIVE' | 'ARCHIVED'
    >(filters.status || 'ALL');

    // Modal state for Category Management
    const [isCategoryModalOpen, setIsCategoryModalOpen] = useState(false);
    const [categoryName, setCategoryName] = useState('');
    const [isSavingCategory, setIsSavingCategory] = useState(false);

    // Action dialog states
    const [itemToArchive, setItemToArchive] = useState<ServiceItem | null>(
        null
    );
    const [itemToDelete, setItemToDelete] = useState<ServiceItem | null>(null);
    const [isActionLoading, setIsActionLoading] = useState(false);

    const handleCreateCategory = (e: React.FormEvent) => {
        e.preventDefault();
        if (!categoryName.trim()) return;

        setIsSavingCategory(true);
        router.post(
            route('owner.service-categories.store'),
            { name: categoryName.trim() },
            {
                onSuccess: () => {
                    setIsSavingCategory(false);
                    setCategoryName('');
                    setIsCategoryModalOpen(false);
                    toast.success('Kategori layanan berhasil dibuat.');
                },
                onError: () => {
                    setIsSavingCategory(false);
                    toast.error('Gagal menambahkan kategori.');
                },
            }
        );
    };

    const handleConfirmArchive = () => {
        if (!itemToArchive) return;
        setIsActionLoading(true);

        router.post(
            route('owner.services.archive', itemToArchive.id),
            {},
            {
                onSuccess: () => {
                    setIsActionLoading(false);
                    setItemToArchive(null);
                    toast.success(
                        itemToArchive.is_archived
                            ? 'Layanan berhasil diaktifkan kembali.'
                            : 'Layanan berhasil diarsipkan.'
                    );
                },
                onError: () => {
                    setIsActionLoading(false);
                    toast.error('Gagal memperbarui status arsip layanan.');
                },
            }
        );
    };

    const handleConfirmDelete = () => {
        if (!itemToDelete) return;
        setIsActionLoading(true);

        router.delete(route('owner.services.destroy', itemToDelete.id), {
            onSuccess: () => {
                setIsActionLoading(false);
                setItemToDelete(null);
                toast.success('Layanan berhasil dihapus.');
            },
            onError: () => {
                setIsActionLoading(false);
                toast.error('Gagal menghapus layanan.');
            },
        });
    };

    const formatRupiah = (amount: number) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(amount);
    };

    // Filter services locally for instantaneous feedback
    const filteredServices = useMemo(() => {
        return services.filter((item) => {
            const matchesSearch =
                searchQuery.trim() === '' ||
                item.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                (item.description &&
                    item.description
                        .toLowerCase()
                        .includes(searchQuery.toLowerCase()));

            const matchesCategory =
                selectedCategory === null ||
                item.category?.id === selectedCategory;

            const matchesStatus =
                statusFilter === 'ALL' ||
                (statusFilter === 'ACTIVE' &&
                    item.is_active &&
                    !item.is_archived) ||
                (statusFilter === 'ARCHIVED' && item.is_archived);

            return matchesSearch && matchesCategory && matchesStatus;
        });
    }, [services, searchQuery, selectedCategory, statusFilter]);

    const activeCount = useMemo(
        () => services.filter((s) => s.is_active && !s.is_archived).length,
        [services]
    );
    const archivedCount = useMemo(
        () => services.filter((s) => s.is_archived).length,
        [services]
    );

    const isPlanLimitReached =
        !usage.is_unlimited && usage.limit > 0 && usage.current >= usage.limit;

    const tableColumns = [
        {
            key: 'name',
            header: 'Layanan',
            width: '32%',
            render: (item: ServiceItem) => (
                <div className="flex items-start gap-3">
                    {item.image_url ? (
                        <img
                            src={item.image_url}
                            alt={item.name}
                            className="h-10 w-10 shrink-0 rounded-lg border border-slate-200 object-cover"
                        />
                    ) : (
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-slate-400">
                            <Layers className="h-5 w-5" />
                        </div>
                    )}
                    <div className="min-w-0 space-y-0.5">
                        <div className="flex items-center gap-1.5">
                            <span className="truncate font-semibold text-slate-900">
                                {item.name}
                            </span>
                            {item.is_featured && (
                                <Badge variant="warning" size="sm">
                                    Unggulan
                                </Badge>
                            )}
                        </div>
                        <div className="flex items-center gap-2">
                            {item.category && (
                                <span className="inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600">
                                    <Tag className="h-2.5 w-2.5" />
                                    {item.category.name}
                                </span>
                            )}
                            {item.variants_count > 0 && (
                                <span className="text-[10px] text-blue-600">
                                    {item.variants_count} varian
                                </span>
                            )}
                            {item.addons_count > 0 && (
                                <span className="text-[10px] text-emerald-600">
                                    +{item.addons_count} add-on
                                </span>
                            )}
                        </div>
                    </div>
                </div>
            ),
        },
        {
            key: 'pricing',
            header: 'Harga Dasar',
            width: '18%',
            render: (item: ServiceItem) => (
                <div>
                    <div className="font-semibold text-slate-900">
                        {formatRupiah(item.price_idr)}
                    </div>
                    {item.variants_count > 0 && (
                        <div className="text-[10px] text-slate-500">
                            Tersedia varian khusus
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'duration',
            header: 'Durasi & Buffer',
            width: '20%',
            render: (item: ServiceItem) => {
                const totalBuffer = item.buffer_before + item.buffer_after;
                return (
                    <div className="space-y-0.5">
                        <div className="flex items-center gap-1 font-medium text-slate-800">
                            <Clock className="h-3.5 w-3.5 text-slate-400" />
                            <span>{item.duration_minutes} mnt</span>
                            {item.duration_type === 'PER_QUANTITY' && (
                                <span className="text-[10px] text-slate-400">
                                    /unit
                                </span>
                            )}
                            {item.duration_type === 'VARIABLE' && (
                                <span className="text-[10px] text-slate-400">
                                    (variabel)
                                </span>
                            )}
                        </div>
                        {totalBuffer > 0 && (
                            <div className="text-[10px] text-slate-500">
                                Buffer: +{totalBuffer} mnt ({item.buffer_before}
                                m / {item.buffer_after}m)
                            </div>
                        )}
                    </div>
                );
            },
        },
        {
            key: 'capacity',
            header: 'Kapasitas',
            width: '12%',
            render: (item: ServiceItem) => (
                <div className="flex items-center gap-1 text-slate-700">
                    <Users className="h-3.5 w-3.5 text-slate-400" />
                    <span>{item.capacity} orang</span>
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            width: '10%',
            render: (item: ServiceItem) => {
                if (item.is_archived) {
                    return (
                        <Badge variant="queuing" size="sm">
                            Arsip
                        </Badge>
                    );
                }
                return item.is_active ? (
                    <Badge variant="active" size="sm">
                        Aktif
                    </Badge>
                ) : (
                    <Badge variant="breakdown" size="sm">
                        Nonaktif
                    </Badge>
                );
            },
        },
        {
            key: 'actions',
            header: 'Aksi',
            width: '8%',
            align: 'right' as const,
            render: (item: ServiceItem) => (
                <div className="flex items-center justify-end gap-1">
                    <Link
                        href={route('owner.services.edit', item.id)}
                        className="rounded p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
                        title="Edit Layanan"
                    >
                        <Edit2 className="h-3.5 w-3.5" />
                    </Link>
                    <button
                        type="button"
                        onClick={() => setItemToArchive(item)}
                        className="rounded p-1.5 text-slate-400 transition-colors hover:bg-amber-50 hover:text-amber-700"
                        title={
                            item.is_archived
                                ? 'Aktifkan dari Arsip'
                                : 'Arsipkan Layanan'
                        }
                    >
                        {item.is_archived ? (
                            <ArchiveRestore className="h-3.5 w-3.5" />
                        ) : (
                            <Archive className="h-3.5 w-3.5" />
                        )}
                    </button>
                    <button
                        type="button"
                        onClick={() => setItemToDelete(item)}
                        className="rounded p-1.5 text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600"
                        title="Hapus Layanan"
                    >
                        <Trash2 className="h-3.5 w-3.5" />
                    </button>
                </div>
            ),
        },
    ];

    return (
        <OwnerLayout
            title="Katalog Layanan"
            subtitle="Kelola seluruh layanan, varian harga, add-on, durasi, dan pengaturan kuota booking bisnis Anda."
        >
            <div className="mx-auto max-w-6xl space-y-6">
                {/* Header Summary & Plan Limit Bar */}
                <div className="flex flex-col gap-4 rounded-[14px] border border-slate-200 bg-white p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-3.5">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                            <Layers className="h-5 w-5" />
                        </div>
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <h3 className="text-sm font-semibold text-slate-900">
                                    Katalog Layanan — {business.name}
                                </h3>
                                <Badge
                                    variant={
                                        isPlanLimitReached
                                            ? 'warning'
                                            : 'neutral'
                                    }
                                    size="sm"
                                >
                                    Kuota Plan:{' '}
                                    {usage.is_unlimited
                                        ? `${usage.current} (Tanpa Batas)`
                                        : `${usage.current} / ${usage.limit} Layanan`}
                                </Badge>
                            </div>
                            <p className="text-xs text-slate-500">
                                Layanan aktif langsung tersedia bagi pelanggan
                                saat melakukan pemesanan di halaman publik.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={() => setIsCategoryModalOpen(true)}
                            leftIcon={<FolderPlus className="h-4 w-4" />}
                        >
                            Kategori
                        </Button>
                        <Link href={route('owner.services.create')}>
                            <Button
                                variant="primary"
                                size="sm"
                                leftIcon={<Plus className="h-4 w-4" />}
                                disabled={isPlanLimitReached}
                            >
                                Tambah Layanan
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Filter and Search Bar */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    {/* Status Tabs */}
                    <div className="inline-flex rounded-lg border border-slate-200 bg-white p-1 text-xs">
                        <button
                            type="button"
                            onClick={() => setStatusFilter('ALL')}
                            className={`rounded-md px-3 py-1.5 font-medium transition-colors ${
                                statusFilter === 'ALL'
                                    ? 'bg-blue-600 text-white shadow-xs'
                                    : 'text-slate-600 hover:text-slate-900'
                            }`}
                        >
                            Semua ({services.length})
                        </button>
                        <button
                            type="button"
                            onClick={() => setStatusFilter('ACTIVE')}
                            className={`rounded-md px-3 py-1.5 font-medium transition-colors ${
                                statusFilter === 'ACTIVE'
                                    ? 'bg-blue-600 text-white shadow-xs'
                                    : 'text-slate-600 hover:text-slate-900'
                            }`}
                        >
                            Aktif ({activeCount})
                        </button>
                        <button
                            type="button"
                            onClick={() => setStatusFilter('ARCHIVED')}
                            className={`rounded-md px-3 py-1.5 font-medium transition-colors ${
                                statusFilter === 'ARCHIVED'
                                    ? 'bg-blue-600 text-white shadow-xs'
                                    : 'text-slate-600 hover:text-slate-900'
                            }`}
                        >
                            Arsip ({archivedCount})
                        </button>
                    </div>

                    {/* Search & Category Filter */}
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="relative w-full sm:w-64">
                            <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <Search className="h-3.5 w-3.5" />
                            </div>
                            <input
                                type="text"
                                placeholder="Cari layanan..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="w-full rounded-lg border border-slate-200 bg-white py-1.5 pr-3 pl-8 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:ring-1 focus:ring-blue-600 focus:outline-none"
                            />
                        </div>

                        {categories.length > 0 && (
                            <select
                                value={selectedCategory ?? ''}
                                onChange={(e) =>
                                    setSelectedCategory(
                                        e.target.value === ''
                                            ? null
                                            : Number(e.target.value)
                                    )
                                }
                                className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-800 focus:border-blue-600 focus:ring-1 focus:ring-blue-600 focus:outline-none"
                            >
                                <option value="">Semua Kategori</option>
                                {categories.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                        )}
                    </div>
                </div>

                {/* Services Table */}
                <DataTable
                    columns={tableColumns}
                    data={filteredServices}
                    keyExtractor={(item) => item.id}
                    emptyText={
                        searchQuery ||
                        selectedCategory !== null ||
                        statusFilter !== 'ALL'
                            ? 'Tidak ada layanan yang sesuai dengan filter pencarian.'
                            : 'Belum ada layanan yang ditambahkan pada katalog bisnis Anda.'
                    }
                    emptyAction={
                        !searchQuery &&
                        selectedCategory === null &&
                        statusFilter === 'ALL' ? (
                            <Link href={route('owner.services.create')}>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    leftIcon={<Plus className="h-3.5 w-3.5" />}
                                    disabled={isPlanLimitReached}
                                >
                                    Tambah Layanan Pertama
                                </Button>
                            </Link>
                        ) : undefined
                    }
                />
            </div>

            {/* Modal Manajemen Kategori */}
            <Modal
                isOpen={isCategoryModalOpen}
                onClose={() => setIsCategoryModalOpen(false)}
                title="Kelola Kategori Layanan"
                description="Kelompokkan layanan Anda ke dalam kategori (cth: Potong Rambut, Perawatan Wajah, Spa)."
                size="md"
                footer={
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => setIsCategoryModalOpen(false)}
                    >
                        Selesai
                    </Button>
                }
            >
                <div className="space-y-4">
                    <form
                        onSubmit={handleCreateCategory}
                        className="flex gap-2"
                    >
                        <Input
                            placeholder="Nama kategori baru..."
                            value={categoryName}
                            onChange={(e) => setCategoryName(e.target.value)}
                            className="flex-1"
                        />
                        <Button
                            variant="primary"
                            size="sm"
                            type="submit"
                            isLoading={isSavingCategory}
                        >
                            Tambah
                        </Button>
                    </form>

                    <div className="divide-y divide-slate-100 rounded-lg border border-slate-200">
                        {categories.length === 0 ? (
                            <p className="p-4 text-center text-xs text-slate-500">
                                Belum ada kategori yang dibuat.
                            </p>
                        ) : (
                            categories.map((cat) => (
                                <div
                                    key={cat.id}
                                    className="flex items-center justify-between px-3.5 py-2.5"
                                >
                                    <span className="text-xs font-medium text-slate-800">
                                        {cat.name}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            router.delete(
                                                route(
                                                    'owner.service-categories.destroy',
                                                    cat.id
                                                ),
                                                {
                                                    onSuccess: () =>
                                                        toast.success(
                                                            'Kategori berhasil dihapus.'
                                                        ),
                                                }
                                            );
                                        }}
                                        className="text-slate-400 transition-colors hover:text-rose-600"
                                        title="Hapus Kategori"
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </Modal>

            {/* Dialog Konfirmasi Arsipkan */}
            <ConfirmationDialog
                isOpen={itemToArchive !== null}
                onClose={() => setItemToArchive(null)}
                onConfirm={handleConfirmArchive}
                title={
                    itemToArchive?.is_archived
                        ? 'Aktifkan Kembali Layanan'
                        : 'Arsipkan Layanan'
                }
                message={
                    itemToArchive ? (
                        <span>
                            {itemToArchive.is_archived
                                ? `Apakah Anda ingin mengaktifkan kembali layanan "${itemToArchive.name}" ke katalog publik?`
                                : `Apakah Anda yakin ingin mengarsipkan "${itemToArchive.name}"? Layanan tidak akan muncul untuk booking baru, namun riwayat pemesanan lama tetap tersimpan utuh.`}
                        </span>
                    ) : (
                        ''
                    )
                }
                confirmLabel={
                    itemToArchive?.is_archived ? 'Aktifkan' : 'Arsipkan'
                }
                cancelLabel="Batal"
                variant={itemToArchive?.is_archived ? 'primary' : 'warning'}
                isLoading={isActionLoading}
            />

            {/* Dialog Konfirmasi Hapus */}
            <ConfirmationDialog
                isOpen={itemToDelete !== null}
                onClose={() => setItemToDelete(null)}
                onConfirm={handleConfirmDelete}
                title="Hapus Layanan"
                message={
                    itemToDelete ? (
                        <span>
                            Apakah Anda yakin ingin menghapus layanan{' '}
                            <strong>{itemToDelete.name}</strong>? Jika layanan
                            ini memiliki riwayat pemesanan pelanggan, sistem
                            akan mengarsipkannya otomatis untuk melindungi data
                            historis.
                        </span>
                    ) : (
                        ''
                    )
                }
                confirmLabel="Hapus Layanan"
                cancelLabel="Batal"
                variant="danger"
                isLoading={isActionLoading}
            />
        </OwnerLayout>
    );
}
