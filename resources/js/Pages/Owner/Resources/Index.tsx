import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    Archive,
    ArchiveRestore,
    Box,
    CalendarOff,
    Camera,
    CheckCircle2,
    Cpu,
    DoorClosed,
    Edit2,
    FolderPlus,
    Plus,
    Search,
    Trash2,
    User,
    Users,
    Wrench,
} from 'lucide-react';
import React, { useState } from 'react';
import {
    Button,
    ConfirmationDialog,
    Input,
    Modal,
    useToast,
} from '../../../Components/ui';
import { OwnerLayout } from '../../../Layouts/OwnerLayout';

interface ResourceTypeItem {
    id: number;
    code: string;
    name: string;
    icon: string;
    is_staff: boolean;
    is_space: boolean;
    is_equipment: boolean;
}

interface ResourceGroupItem {
    id: number;
    name: string;
    description: string | null;
}

interface ResourceItem {
    id: number;
    uuid: string;
    name: string;
    code: string | null;
    capacity: number;
    visibility: 'PUBLIC' | 'INTERNAL';
    state: 'AVAILABLE' | 'BLOCKED' | 'MAINTENANCE' | 'INACTIVE';
    skills: string[];
    metadata: Record<string, unknown> | null;
    archived_at: string | null;
    is_archived: boolean;
    resource_type: ResourceTypeItem | null;
    group: { id: number; name: string } | null;
    schedules_count: number;
    time_blocks_count: number;
}

interface QuotaUsage {
    current: number;
    limit: number;
    remaining: number;
    is_unlimited: boolean;
}

interface IndexProps {
    resources: ResourceItem[];
    resource_types: ResourceTypeItem[];
    resource_groups: ResourceGroupItem[];
    quota: QuotaUsage;
    filters: {
        search: string;
        type_id: number | null;
        state: string;
        status: string;
    };
}

export default function ResourcesIndex({
    resources,
    resource_types,
    resource_groups,
    quota,
    filters,
}: IndexProps) {
    const toast = useToast();

    // Filters state
    const [search, setSearch] = useState(filters.search || '');
    const [selectedType, setSelectedType] = useState<number | null>(
        filters.type_id
    );
    const [selectedState, setSelectedState] = useState(filters.state || 'ALL');
    const [selectedStatus, setSelectedStatus] = useState(
        filters.status || 'ACTIVE'
    );

    // Dialog state
    const [archiveTarget, setArchiveTarget] = useState<ResourceItem | null>(
        null
    );
    const [unarchiveTarget, setUnarchiveTarget] = useState<ResourceItem | null>(
        null
    );
    const [deleteTarget, setDeleteTarget] = useState<ResourceItem | null>(null);

    // Group Modal state
    const [isGroupModalOpen, setIsGroupModalOpen] = useState(false);
    const [groupName, setGroupName] = useState('');
    const [groupDesc, setGroupDesc] = useState('');
    const [isSubmittingGroup, setIsSubmittingGroup] = useState(false);

    // Quick Time Block Modal state
    const [isBlockModalOpen, setIsBlockModalOpen] = useState(false);
    const [blockResourceId, setBlockResourceId] = useState<number | ''>('');
    const [blockStart, setBlockStart] = useState('');
    const [blockEnd, setBlockEnd] = useState('');
    const [blockReason, setBlockReason] = useState('');
    const [blockIsAll, setBlockIsAll] = useState(false);
    const [isSubmittingBlock, setIsSubmittingBlock] = useState(false);

    const applyFilter = (key: string, value: unknown) => {
        router.get(
            '/app/resources',
            {
                search: key === 'search' ? (value as string) : search,
                type_id:
                    key === 'type_id' ? (value as number | null) : selectedType,
                state: key === 'state' ? (value as string) : selectedState,
                status: key === 'status' ? (value as string) : selectedStatus,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilter('search', search);
    };

    const handleCreateGroup = (e: React.FormEvent) => {
        e.preventDefault();
        if (!groupName.trim()) return;

        setIsSubmittingGroup(true);
        router.post(
            '/app/resource-groups',
            {
                name: groupName,
                description: groupDesc,
            },
            {
                onSuccess: () => {
                    setIsGroupModalOpen(false);
                    setGroupName('');
                    setGroupDesc('');
                    toast.success('Grup resource berhasil dibuat.');
                },
                onError: (err) => {
                    toast.error(Object.values(err)[0] as string);
                },
                onFinish: () => setIsSubmittingGroup(false),
            }
        );
    };

    const handleCreateTimeBlock = (e: React.FormEvent) => {
        e.preventDefault();
        if (!blockStart || !blockEnd || !blockReason.trim()) {
            toast.error(
                'Lengkapi tanggal mulai, tanggal selesai, dan alasan block.'
            );
            return;
        }

        setIsSubmittingBlock(true);
        router.post(
            '/app/time-blocks',
            {
                resource_id:
                    blockIsAll || !blockResourceId ? null : blockResourceId,
                is_all_resources: blockIsAll,
                start_at: blockStart,
                end_at: blockEnd,
                reason: blockReason,
            },
            {
                onSuccess: () => {
                    setIsBlockModalOpen(false);
                    setBlockResourceId('');
                    setBlockStart('');
                    setBlockEnd('');
                    setBlockReason('');
                    setBlockIsAll(false);
                    toast.success('Block waktu / cuti berhasil ditambahkan.');
                },
                onError: (err) => {
                    toast.error(Object.values(err)[0] as string);
                },
                onFinish: () => setIsSubmittingBlock(false),
            }
        );
    };

    const handleArchiveConfirm = () => {
        if (!archiveTarget) return;
        router.post(
            `/app/resources/${archiveTarget.id}/archive`,
            {},
            {
                onSuccess: () => {
                    setArchiveTarget(null);
                    toast.success(
                        `Resource '${archiveTarget.name}' berhasil diarsipkan.`
                    );
                },
            }
        );
    };

    const handleUnarchiveConfirm = () => {
        if (!unarchiveTarget) return;
        router.post(
            `/app/resources/${unarchiveTarget.id}/unarchive`,
            {},
            {
                onSuccess: () => {
                    setUnarchiveTarget(null);
                    toast.success(
                        `Resource '${unarchiveTarget.name}' berhasil diaktifkan kembali.`
                    );
                },
            }
        );
    };

    const handleDeleteConfirm = () => {
        if (!deleteTarget) return;
        router.delete(`/app/resources/${deleteTarget.id}`, {
            onSuccess: () => {
                setDeleteTarget(null);
                toast.success(
                    `Resource '${deleteTarget.name}' berhasil dihapus.`
                );
            },
        });
    };

    const renderTypeIcon = (code?: string) => {
        switch (code) {
            case 'STAFF':
                return <User className="h-4 w-4 text-blue-600" />;
            case 'ROOM':
                return <DoorClosed className="h-4 w-4 text-indigo-600" />;
            case 'COURT':
                return <Activity className="h-4 w-4 text-emerald-600" />;
            case 'STUDIO':
                return <Camera className="h-4 w-4 text-amber-600" />;
            case 'MACHINE':
            case 'EQUIPMENT':
                return <Cpu className="h-4 w-4 text-rose-600" />;
            default:
                return <Box className="h-4 w-4 text-slate-600" />;
        }
    };

    const getStateBadge = (state: string) => {
        switch (state) {
            case 'AVAILABLE':
                return (
                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                        <CheckCircle2 className="h-3 w-3" /> Tersedia
                    </span>
                );
            case 'MAINTENANCE':
                return (
                    <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">
                        <Wrench className="h-3 w-3" /> Maintenance
                    </span>
                );
            case 'BLOCKED':
                return (
                    <span className="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-medium text-rose-700">
                        <CalendarOff className="h-3 w-3" /> Terblokir
                    </span>
                );
            case 'INACTIVE':
            default:
                return (
                    <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                        Nonaktif
                    </span>
                );
        }
    };

    return (
        <OwnerLayout
            title="Resource & Tim"
            breadcrumbs={[
                { label: 'Dashboard', href: '/app/dashboard' },
                { label: 'Resource & Tim' },
            ]}
            actions={
                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setIsGroupModalOpen(true)}
                        leftIcon={<FolderPlus className="h-4 w-4" />}
                    >
                        Grup / Pool
                    </Button>
                    <Link href="/app/time-blocks">
                        <Button
                            variant="outline"
                            size="sm"
                            leftIcon={<CalendarOff className="h-4 w-4" />}
                        >
                            Cuti & Block
                        </Button>
                    </Link>
                    <Link href="/app/resources/create">
                        <Button
                            variant="primary"
                            size="sm"
                            leftIcon={<Plus className="h-4 w-4" />}
                        >
                            Tambah Resource
                        </Button>
                    </Link>
                </div>
            }
        >
            <Head title="Resource, Tim & Ruang - AMAN BOOKING" />

            <div className="space-y-6">
                {/* Quota Banner */}
                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                                <Users className="h-5 w-5" />
                            </div>
                            <div>
                                <h3 className="text-sm font-semibold text-slate-900">
                                    Kapasitas Resource & Tim
                                </h3>
                                <p className="text-xs text-slate-500">
                                    {quota.is_unlimited
                                        ? `${quota.current} resource aktif digunakan (Paket Unlimited)`
                                        : `${quota.current} dari ${quota.limit} slot resource terpakai (${quota.remaining} tersisa)`}
                                </p>
                            </div>
                        </div>

                        {!quota.is_unlimited && (
                            <div className="w-full sm:w-48">
                                <div className="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                    <div
                                        className={`h-full transition-all duration-300 ${
                                            quota.current >= quota.limit
                                                ? 'bg-rose-500'
                                                : quota.current >=
                                                    quota.limit * 0.8
                                                  ? 'bg-amber-500'
                                                  : 'bg-blue-600'
                                        }`}
                                        style={{
                                            width: `${Math.min(100, (quota.current / quota.limit) * 100)}%`,
                                        }}
                                    />
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                {/* Filters & Search */}
                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        {/* Status Tabs */}
                        <div className="flex flex-wrap items-center gap-1 border-b border-slate-200 pb-2 sm:border-none sm:pb-0">
                            {[
                                { key: 'ACTIVE', label: 'Aktif' },
                                { key: 'ARCHIVED', label: 'Diarsipkan' },
                                { key: 'ALL', label: 'Semua' },
                            ].map((tab) => (
                                <button
                                    key={tab.key}
                                    type="button"
                                    onClick={() => {
                                        setSelectedStatus(tab.key);
                                        applyFilter('status', tab.key);
                                    }}
                                    className={`rounded-lg px-3 py-1.5 text-xs font-medium transition-colors ${
                                        selectedStatus === tab.key
                                            ? 'bg-blue-50 text-blue-700'
                                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                                    }`}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>

                        {/* Search & Type filter */}
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <form
                                onSubmit={handleSearchSubmit}
                                className="relative w-full sm:w-64"
                            >
                                <Search className="absolute top-2.5 left-3 h-4 w-4 text-slate-400" />
                                <Input
                                    type="text"
                                    placeholder="Cari nama atau kode..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-9 text-xs"
                                />
                            </form>

                            <select
                                value={selectedType ?? ''}
                                onChange={(e) => {
                                    const val = e.target.value
                                        ? Number(e.target.value)
                                        : null;
                                    setSelectedType(val);
                                    applyFilter('type_id', val);
                                }}
                                className="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-700 focus:border-blue-500 focus:outline-none"
                            >
                                <option value="">Semua Tipe Resource</option>
                                {resource_types.map((type) => (
                                    <option key={type.id} value={type.id}>
                                        {type.name}
                                    </option>
                                ))}
                            </select>

                            <select
                                value={selectedState}
                                onChange={(e) => {
                                    setSelectedState(e.target.value);
                                    applyFilter('state', e.target.value);
                                }}
                                className="h-9 rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-700 focus:border-blue-500 focus:outline-none"
                            >
                                <option value="ALL">Semua Kondisi</option>
                                <option value="AVAILABLE">Tersedia</option>
                                <option value="MAINTENANCE">Maintenance</option>
                                <option value="BLOCKED">Terblokir</option>
                                <option value="INACTIVE">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                </div>

                {/* Resource List Table */}
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Resource
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Tipe & Grup
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Kapasitas & Akses
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Keahlian / Skill
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 text-slate-700">
                                {resources.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-4 py-12 text-center text-slate-500"
                                        >
                                            <Users className="mx-auto mb-2 h-8 w-8 text-slate-300" />
                                            <p className="text-sm font-medium text-slate-700">
                                                Belum ada resource yang sesuai
                                                filter.
                                            </p>
                                            <p className="mt-1 text-xs text-slate-400">
                                                Tambahkan staff, ruangan, atau
                                                peralatan untuk mulai
                                                menjadwalkan booking.
                                            </p>
                                            <div className="mt-4">
                                                <Link href="/app/resources/create">
                                                    <Button
                                                        variant="primary"
                                                        size="sm"
                                                    >
                                                        Tambah Resource Baru
                                                    </Button>
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    resources.map((resource) => (
                                        <tr
                                            key={resource.id}
                                            className={`transition-colors hover:bg-slate-50/70 ${
                                                resource.is_archived
                                                    ? 'bg-slate-50/40 opacity-75'
                                                    : ''
                                            }`}
                                        >
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-3">
                                                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                                                        {renderTypeIcon(
                                                            resource
                                                                .resource_type
                                                                ?.code
                                                        )}
                                                    </div>
                                                    <div>
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-semibold text-slate-900">
                                                                {resource.name}
                                                            </span>
                                                            {resource.code && (
                                                                <span className="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] text-slate-600">
                                                                    {
                                                                        resource.code
                                                                    }
                                                                </span>
                                                            )}
                                                        </div>
                                                        <div className="text-[11px] text-slate-400">
                                                            {
                                                                resource.schedules_count
                                                            }{' '}
                                                            hari jadwal aktif
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <td className="px-4 py-3">
                                                <div className="font-medium text-slate-800">
                                                    {resource.resource_type
                                                        ?.name ?? '-'}
                                                </div>
                                                {resource.group && (
                                                    <span className="mt-0.5 inline-block rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-medium text-blue-700">
                                                        Pool:{' '}
                                                        {resource.group.name}
                                                    </span>
                                                )}
                                            </td>

                                            <td className="px-4 py-3">
                                                <div className="text-slate-800">
                                                    Kapasitas:{' '}
                                                    <span className="font-medium">
                                                        {resource.capacity}
                                                    </span>
                                                </div>
                                                <div className="mt-0.5">
                                                    {resource.visibility ===
                                                    'PUBLIC' ? (
                                                        <span className="rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700">
                                                            Customer Choice
                                                            (Public)
                                                        </span>
                                                    ) : (
                                                        <span className="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600">
                                                            Internal Only
                                                        </span>
                                                    )}
                                                </div>
                                            </td>

                                            <td className="px-4 py-3">
                                                {resource.skills &&
                                                resource.skills.length > 0 ? (
                                                    <div className="flex flex-wrap gap-1">
                                                        {resource.skills.map(
                                                            (skill, idx) => (
                                                                <span
                                                                    key={idx}
                                                                    className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] text-slate-700"
                                                                >
                                                                    {skill}
                                                                </span>
                                                            )
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400 italic">
                                                        -
                                                    </span>
                                                )}
                                            </td>

                                            <td className="px-4 py-3">
                                                {resource.is_archived ? (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-500">
                                                        <Archive className="h-3 w-3" />{' '}
                                                        Diarsipkan
                                                    </span>
                                                ) : (
                                                    getStateBadge(
                                                        resource.state
                                                    )
                                                )}
                                            </td>

                                            <td className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        title="Beri Cuti / Block Waktu"
                                                        onClick={() => {
                                                            setBlockResourceId(
                                                                resource.id
                                                            );
                                                            setBlockIsAll(
                                                                false
                                                            );
                                                            setIsBlockModalOpen(
                                                                true
                                                            );
                                                        }}
                                                    >
                                                        <CalendarOff className="h-3.5 w-3.5 text-slate-600" />
                                                    </Button>

                                                    <Link
                                                        href={`/app/resources/${resource.id}/edit`}
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            title="Edit Resource"
                                                        >
                                                            <Edit2 className="h-3.5 w-3.5 text-slate-600" />
                                                        </Button>
                                                    </Link>

                                                    {resource.is_archived ? (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            title="Aktifkan Kembali"
                                                            onClick={() =>
                                                                setUnarchiveTarget(
                                                                    resource
                                                                )
                                                            }
                                                        >
                                                            <ArchiveRestore className="h-3.5 w-3.5 text-emerald-600" />
                                                        </Button>
                                                    ) : (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            title="Arsipkan Resource"
                                                            onClick={() =>
                                                                setArchiveTarget(
                                                                    resource
                                                                )
                                                            }
                                                        >
                                                            <Archive className="h-3.5 w-3.5 text-amber-600" />
                                                        </Button>
                                                    )}

                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        title="Hapus Resource"
                                                        onClick={() =>
                                                            setDeleteTarget(
                                                                resource
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5 text-rose-600" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Modal Tambah Grup Resource */}
            <Modal
                isOpen={isGroupModalOpen}
                onClose={() => setIsGroupModalOpen(false)}
                title="Kelola Grup & Pool Resource"
                size="md"
            >
                <form onSubmit={handleCreateGroup} className="space-y-4">
                    <div>
                        <label className="block text-xs font-medium text-slate-700">
                            Nama Grup / Pool
                        </label>
                        <Input
                            placeholder="Contoh: Barber Chairs, Treatment Rooms, Armada Van"
                            value={groupName}
                            onChange={(e) => setGroupName(e.target.value)}
                            required
                            className="mt-1 text-xs"
                        />
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-slate-700">
                            Deskripsi (Opsional)
                        </label>
                        <Input
                            placeholder="Keterangan grup atau pool penugasan..."
                            value={groupDesc}
                            onChange={(e) => setGroupDesc(e.target.value)}
                            className="mt-1 text-xs"
                        />
                    </div>

                    <div className="rounded-lg bg-blue-50 p-3 text-xs text-blue-700">
                        <p className="font-medium">
                            Pool Penugasan Otomatis (PRD 176)
                        </p>
                        <p className="mt-0.5 text-[11px] text-blue-600">
                            Resource dalam grup yang sama dapat dipakai dalam
                            service dengan mode penugasan POOL (sistem memilih
                            resource mana pun yang sedang tersedia).
                        </p>
                    </div>

                    {resource_groups.length > 0 && (
                        <div className="border-t border-slate-100 pt-3">
                            <span className="text-xs font-medium text-slate-700">
                                Grup yang Sudah Ada:
                            </span>
                            <div className="mt-2 flex flex-wrap gap-1.5">
                                {resource_groups.map((g) => (
                                    <span
                                        key={g.id}
                                        className="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700"
                                    >
                                        {g.name}
                                    </span>
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="flex justify-end gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setIsGroupModalOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant="primary"
                            size="sm"
                            isLoading={isSubmittingGroup}
                        >
                            Simpan Grup
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Modal Quick Time Block / Cuti */}
            <Modal
                isOpen={isBlockModalOpen}
                onClose={() => setIsBlockModalOpen(false)}
                title="Beri Cuti atau Block Waktu Manual"
                size="md"
            >
                <form onSubmit={handleCreateTimeBlock} className="space-y-4">
                    <div className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="is_all"
                            checked={blockIsAll}
                            onChange={(e) => {
                                setBlockIsAll(e.target.checked);
                                if (e.target.checked) setBlockResourceId('');
                            }}
                            className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                        />
                        <label
                            htmlFor="is_all"
                            className="text-xs font-medium text-slate-700"
                        >
                            Terapkan ke SEMUA Resource (Seluruh Bisnis Ditutup)
                        </label>
                    </div>

                    {!blockIsAll && (
                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Pilih Resource Spesifik
                            </label>
                            <select
                                value={blockResourceId}
                                onChange={(e) =>
                                    setBlockResourceId(
                                        e.target.value
                                            ? Number(e.target.value)
                                            : ''
                                    )
                                }
                                className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-blue-500 focus:outline-none"
                                required={!blockIsAll}
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
                                Mulai (Waktu Mulai)
                            </label>
                            <Input
                                type="datetime-local"
                                value={blockStart}
                                onChange={(e) => setBlockStart(e.target.value)}
                                required
                                className="mt-1 text-xs"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Selesai (Waktu Berakhir)
                            </label>
                            <Input
                                type="datetime-local"
                                value={blockEnd}
                                onChange={(e) => setBlockEnd(e.target.value)}
                                required
                                className="mt-1 text-xs"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-slate-700">
                            Alasan Cuti / Maintenance / Block
                        </label>
                        <Input
                            placeholder="Contoh: Cuti Tahunan, Servis Mesin, Sakit, Izin Khusus"
                            value={blockReason}
                            onChange={(e) => setBlockReason(e.target.value)}
                            required
                            className="mt-1 text-xs"
                        />
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setIsBlockModalOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant="primary"
                            size="sm"
                            isLoading={isSubmittingBlock}
                        >
                            Simpan Block Waktu
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Confirmation Dialogs */}
            <ConfirmationDialog
                isOpen={!!archiveTarget}
                onClose={() => setArchiveTarget(null)}
                onConfirm={handleArchiveConfirm}
                title="Arsipkan Resource?"
                description={`Resource '${archiveTarget?.name}' akan dinonaktifkan dari jadwal booking publik dan membebaskan 1 kuota subscription Anda. Data riwayat booking tetap aman.`}
                confirmText="Arsipkan"
                variant="warning"
            />

            <ConfirmationDialog
                isOpen={!!unarchiveTarget}
                onClose={() => setUnarchiveTarget(null)}
                onConfirm={handleUnarchiveConfirm}
                title="Aktifkan Kembali Resource?"
                description={`Resource '${unarchiveTarget?.name}' akan diaktifkan kembali. Pastikan kuota subscription Anda masih mencukupi.`}
                confirmText="Aktifkan"
                variant="primary"
            />

            <ConfirmationDialog
                isOpen={!!deleteTarget}
                onClose={() => setDeleteTarget(null)}
                onConfirm={handleDeleteConfirm}
                title="Hapus Resource?"
                description={`Apakah Anda yakin ingin menghapus '${deleteTarget?.name}'? Jika resource ini memiliki riwayat booking, sistem akan mengarsipkannya demi integritas data.`}
                confirmText="Hapus / Arsipkan"
                variant="danger"
            />
        </OwnerLayout>
    );
}
