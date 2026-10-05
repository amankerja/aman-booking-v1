import { Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    Calendar,
    CheckCircle2,
    Clock,
    Download,
    Eye,
    FileText,
    GitMerge,
    History,
    Phone,
    Plus,
    Search,
    ShieldAlert,
    Trash2,
    User,
    UserCheck,
    XCircle,
} from 'lucide-react';
import React, { useState } from 'react';
import {
    Button,
    DataTable,
    Drawer,
    Input,
    Modal,
    Textarea,
    useToast,
} from '../../../Components/ui';
import { OwnerLayout } from '../../../Layouts/OwnerLayout';

interface BookingItem {
    id: number;
    code: string;
    status_category: string;
    payment_status?: string;
    start_at: string;
    end_at: string;
    total_idr: number;
    notes?: string | null;
    service?: {
        id: number;
        name: string;
    } | null;
    allocations?: Array<{
        id: number;
        resource?: {
            id: number;
            name: string;
        } | null;
    }>;
}

export interface CustomerItem {
    id: number;
    tenant_id: number;
    name: string;
    phone_e164: string;
    email: string | null;
    tags: string[] | null;
    notes: string | null;
    marketing_consent_at: string | null;
    no_show_count: number;
    is_verified?: boolean;
    anonymized_at?: string | null;
    created_at: string;
    updated_at: string;
    bookings_count: number;
}

export interface CustomerStats {
    total_bookings: number;
    completed_bookings: number;
    cancelled_bookings: number;
    no_show_bookings: number;
    no_show_count: number;
    total_spent_idr: number;
    first_booking_at: string | null;
    last_booking_at: string | null;
    has_marketing_consent: boolean;
    is_anonymized: boolean;
}

interface PaginatedCustomers {
    data: CustomerItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface CustomersIndexProps {
    customers: PaginatedCustomers;
    filters: {
        search: string;
        tag: string;
        segment?: string;
    };
    tags: string[];
    canExport: boolean;
}

const SEGMENT_OPTIONS = [
    { key: 'ALL', label: 'Semua' },
    { key: 'VIP', label: 'VIP' },
    { key: 'REPEAT', label: 'Pelanggan Repeat' },
    { key: 'NO_SHOW_RISK', label: 'Risiko No-Show' },
    { key: 'WITH_CONSENT', label: 'Consent Aktif' },
    { key: 'WITHOUT_CONSENT', label: 'Tanpa Consent' },
    { key: 'ANONYMIZED', label: 'Data Anonim' },
];

function normalizePhonePreview(raw: string): string {
    const trimmed = raw.trim();
    if (!trimmed) return '';
    const hasPlus = trimmed.startsWith('+');
    const digits = trimmed.replace(/\D/g, '');
    if (!digits) return '';
    if (hasPlus) return '+' + digits;
    if (digits.startsWith('08')) return '+62' + digits.substring(1);
    if (digits.startsWith('628')) return '+' + digits;
    if (digits.startsWith('8') && digits.length >= 9 && digits.length <= 13) {
        return '+62' + digits;
    }
    return '+' + digits;
}

export default function CustomersIndex({
    customers,
    filters,
    tags,
    canExport,
}: CustomersIndexProps) {
    const toast = useToast();

    // Filters state
    const [search, setSearch] = useState(filters.search || '');
    const [selectedTag, setSelectedTag] = useState(filters.tag || 'ALL');
    const [selectedSegment, setSelectedSegment] = useState(
        filters.segment || 'ALL'
    );

    // Create modal state
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [createForm, setCreateForm] = useState({
        name: '',
        phone: '',
        email: '',
        tagsInput: 'regular',
        notes: '',
        marketing_consent: false,
    });
    const [createErrors, setCreateErrors] = useState<Record<string, string>>({});
    const [isSubmittingCreate, setIsSubmittingCreate] = useState(false);

    // Edit modal state
    const [isEditOpen, setIsEditOpen] = useState(false);
    const [editingCustomer, setEditingCustomer] = useState<CustomerItem | null>(
        null
    );
    const [editForm, setEditForm] = useState({
        name: '',
        phone: '',
        email: '',
        tagsInput: '',
        notes: '',
        marketing_consent: false,
        no_show_count: 0,
    });
    const [editErrors, setEditErrors] = useState<Record<string, string>>({});
    const [isSubmittingEdit, setIsSubmittingEdit] = useState(false);

    // Merge modal state
    const [isMergeOpen, setIsMergeOpen] = useState(false);
    const [targetCustomer, setTargetCustomer] = useState<CustomerItem | null>(
        null
    );
    const [selectedSourceId, setSelectedSourceId] = useState<string>('');
    const [isSubmittingMerge, setIsSubmittingMerge] = useState(false);

    // Detail drawer state
    const [isDrawerOpen, setIsDrawerOpen] = useState(false);
    const [activeCustomer, setActiveCustomer] = useState<CustomerItem | null>(
        null
    );
    const [customerStats, setCustomerStats] = useState<CustomerStats | null>(
        null
    );
    const [customerBookings, setCustomerBookings] = useState<BookingItem[]>([]);
    const [isLoadingDetail, setIsLoadingDetail] = useState(false);

    // Internal note state
    const [newNote, setNewNote] = useState('');
    const [isSubmittingNote, setIsSubmittingNote] = useState(false);

    // Anonymize modal state
    const [isAnonymizeOpen, setIsAnonymizeOpen] = useState(false);
    const [anonymizeReason, setAnonymizeReason] = useState('');
    const [isSubmittingAnonymize, setIsSubmittingAnonymize] = useState(false);

    const applyFilters = (newParams: {
        search?: string;
        tag?: string;
        segment?: string;
    }) => {
        const s = newParams.search !== undefined ? newParams.search : search;
        const t = newParams.tag !== undefined ? newParams.tag : selectedTag;
        const seg =
            newParams.segment !== undefined
                ? newParams.segment
                : selectedSegment;

        router.get(
            '/app/customers',
            {
                search: s.trim() || undefined,
                tag: t !== 'ALL' ? t : undefined,
                segment: seg !== 'ALL' ? seg : undefined,
            },
            { preserveState: true, replace: true }
        );
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters({ search });
    };

    const handleTagChange = (newTag: string) => {
        setSelectedTag(newTag);
        applyFilters({ tag: newTag });
    };

    const handleSegmentChange = (newSeg: string) => {
        setSelectedSegment(newSeg);
        applyFilters({ segment: newSeg });
    };

    const handleResetFilter = () => {
        setSearch('');
        setSelectedTag('ALL');
        setSelectedSegment('ALL');
        router.get('/app/customers', {}, { preserveState: true, replace: true });
    };

    const handleOpenDetail = async (c: CustomerItem) => {
        setActiveCustomer(c);
        setIsDrawerOpen(true);
        setIsLoadingDetail(true);
        setNewNote('');
        try {
            const res = await fetch(`/app/customers/${c.id}`, {
                headers: { Accept: 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                if (data.customer) {
                    setActiveCustomer(data.customer);
                }
                setCustomerStats(data.stats || null);
                setCustomerBookings(data.bookings || []);
            }
        } catch {
            toast.error('Gagal memuat data riwayat profil pelanggan.');
        } finally {
            setIsLoadingDetail(false);
        }
    };

    const handleOpenEdit = (c: CustomerItem) => {
        setEditingCustomer(c);
        setEditForm({
            name: c.name,
            phone: c.phone_e164,
            email: c.email || '',
            tagsInput: Array.isArray(c.tags) ? c.tags.join(', ') : '',
            notes: c.notes || '',
            marketing_consent: c.marketing_consent_at !== null,
            no_show_count: c.no_show_count || 0,
        });
        setEditErrors({});
        setIsEditOpen(true);
    };

    const handleOpenMerge = (c: CustomerItem) => {
        setTargetCustomer(c);
        setSelectedSourceId('');
        setIsMergeOpen(true);
    };

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmittingCreate(true);
        setCreateErrors({});

        const parsedTags = createForm.tagsInput
            .split(',')
            .map((t) => t.trim())
            .filter((t) => t.length > 0);

        router.post(
            '/app/customers',
            {
                name: createForm.name,
                phone: createForm.phone,
                email: createForm.email || null,
                tags: parsedTags,
                notes: createForm.notes || null,
                marketing_consent: createForm.marketing_consent,
            },
            {
                onSuccess: () => {
                    setIsCreateOpen(false);
                    setCreateForm({
                        name: '',
                        phone: '',
                        email: '',
                        tagsInput: 'regular',
                        notes: '',
                        marketing_consent: false,
                    });
                    toast.success('Pelanggan berhasil ditambahkan.');
                },
                onError: (errors) => {
                    setCreateErrors(errors as Record<string, string>);
                },
                onFinish: () => setIsSubmittingCreate(false),
            }
        );
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingCustomer) return;
        setIsSubmittingEdit(true);
        setEditErrors({});

        const parsedTags = editForm.tagsInput
            .split(',')
            .map((t) => t.trim())
            .filter((t) => t.length > 0);

        router.put(
            `/app/customers/${editingCustomer.id}`,
            {
                name: editForm.name,
                phone: editForm.phone,
                email: editForm.email || null,
                tags: parsedTags,
                notes: editForm.notes || null,
                marketing_consent: editForm.marketing_consent,
                no_show_count: Number(editForm.no_show_count),
            },
            {
                onSuccess: () => {
                    setIsEditOpen(false);
                    setEditingCustomer(null);
                    toast.success('Profil pelanggan berhasil diperbarui.');
                },
                onError: (errors) => {
                    setEditErrors(errors as Record<string, string>);
                },
                onFinish: () => setIsSubmittingEdit(false),
            }
        );
    };

    const handleMergeSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!targetCustomer || !selectedSourceId) return;
        setIsSubmittingMerge(true);

        router.post(
            `/app/customers/${targetCustomer.id}/merge`,
            {
                source_id: Number(selectedSourceId),
            },
            {
                onSuccess: () => {
                    setIsMergeOpen(false);
                    setTargetCustomer(null);
                    setSelectedSourceId('');
                    toast.success('Akun pelanggan berhasil digabungkan.');
                },
                onFinish: () => setIsSubmittingMerge(false),
            }
        );
    };

    const handleAddNoteSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!activeCustomer || !newNote.trim()) return;

        setIsSubmittingNote(true);
        router.post(
            `/app/customers/${activeCustomer.id}/notes`,
            { note: newNote.trim() },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setNewNote('');
                    toast.success('Catatan internal berhasil disimpan.');
                    handleOpenDetail(activeCustomer);
                },
                onError: () => {
                    toast.error('Gagal menambahkan catatan internal.');
                },
                onFinish: () => setIsSubmittingNote(false),
            }
        );
    };

    const handleAnonymizeSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!activeCustomer) return;

        setIsSubmittingAnonymize(true);
        router.post(
            `/app/customers/${activeCustomer.id}/anonymize`,
            { reason: anonymizeReason.trim() || null },
            {
                onSuccess: () => {
                    setIsAnonymizeOpen(false);
                    setAnonymizeReason('');
                    setIsDrawerOpen(false);
                    toast.success(
                        'Data pelanggan berhasil dianonimkan (PRD 54).'
                    );
                },
                onError: () => {
                    toast.error('Gagal menganonimkan data pelanggan.');
                },
                onFinish: () => setIsSubmittingAnonymize(false),
            }
        );
    };

    const handleExport = () => {
        if (!canExport) {
            toast.error(
                'Anda tidak memiliki izin untuk mengekspor data customer (customer.export).'
            );
            return;
        }
        window.location.href = '/app/customers/export';
    };

    const columns = [
        {
            key: 'customer',
            header: 'PELANGGAN',
            render: (c: CustomerItem) => {
                const isAnon = Boolean(c.anonymized_at);
                return (
                    <div className="flex items-center gap-3">
                        <div
                            className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold ${
                                isAnon
                                    ? 'bg-rose-100 text-rose-700'
                                    : 'bg-slate-100 text-slate-700'
                            }`}
                        >
                            {isAnon
                                ? 'AN'
                                : c.name.substring(0, 2).toUpperCase()}
                        </div>
                        <div className="min-w-0">
                            <div className="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    onClick={() => handleOpenDetail(c)}
                                    className="block truncate text-left font-medium text-slate-900 hover:text-blue-600 hover:underline"
                                >
                                    {c.name}
                                </button>
                                {isAnon && (
                                    <span className="rounded-full border border-rose-200 bg-rose-50 px-1.5 py-0.2 text-[9px] font-semibold text-rose-700">
                                        ANONIM
                                    </span>
                                )}
                            </div>
                            <div className="truncate text-[11px] text-slate-500">
                                {isAnon ? (
                                    <span className="italic text-slate-400">
                                        Data pribadi dihapus (PRD 54)
                                    </span>
                                ) : (
                                    c.email || 'Tanpa email'
                                )}
                            </div>
                        </div>
                    </div>
                );
            },
        },
        {
            key: 'phone',
            header: 'NOMOR TELEPON (E.164)',
            render: (c: CustomerItem) => (
                <div className="flex items-center gap-1.5 font-mono text-xs text-slate-700">
                    <Phone className="h-3 w-3 shrink-0 text-slate-400" />
                    <span>{c.phone_e164}</span>
                </div>
            ),
        },
        {
            key: 'tags',
            header: 'TAG / SEGMEN',
            render: (c: CustomerItem) => {
                const tagsList = Array.isArray(c.tags) ? c.tags : [];
                if (tagsList.length === 0) {
                    return (
                        <span className="text-[11px] text-slate-400">-</span>
                    );
                }
                return (
                    <div className="flex flex-wrap gap-1">
                        {tagsList.slice(0, 3).map((tag, idx) => (
                            <span
                                key={idx}
                                className="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-700"
                            >
                                {tag}
                            </span>
                        ))}
                        {tagsList.length > 3 && (
                            <span className="text-[10px] text-slate-400">
                                +{tagsList.length - 3}
                            </span>
                        )}
                    </div>
                );
            },
        },
        {
            key: 'consent',
            header: 'MARKETING CONSENT',
            render: (c: CustomerItem) => {
                const hasConsent =
                    c.marketing_consent_at !== null && !c.anonymized_at;
                return (
                    <div className="flex items-center gap-1.5">
                        <span
                            className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium ${
                                hasConsent
                                    ? 'border border-emerald-200 bg-emerald-50 text-emerald-700'
                                    : 'border border-slate-200 bg-slate-100 text-slate-500'
                            }`}
                        >
                            {hasConsent ? (
                                <>
                                    <CheckCircle2 className="h-2.5 w-2.5" />
                                    Setuju
                                </>
                            ) : (
                                <>
                                    <XCircle className="h-2.5 w-2.5" />
                                    Tidak
                                </>
                            )}
                        </span>
                    </div>
                );
            },
        },
        {
            key: 'bookings_count',
            header: 'BOOKINGS',
            align: 'center' as const,
            render: (c: CustomerItem) => (
                <span className="text-xs font-medium text-slate-700">
                    {c.bookings_count}x
                </span>
            ),
        },
        {
            key: 'no_show_count',
            header: 'NO-SHOW',
            align: 'center' as const,
            render: (c: CustomerItem) => (
                <span
                    className={`inline-flex items-center rounded px-1.5 py-0.5 text-[11px] font-medium ${
                        c.no_show_count > 0
                            ? 'border border-rose-200 bg-rose-50 text-rose-700'
                            : 'text-slate-500'
                    }`}
                >
                    {c.no_show_count}
                </span>
            ),
        },
        {
            key: 'actions',
            header: 'AKSI',
            align: 'right' as const,
            render: (c: CustomerItem) => (
                <div className="flex items-center justify-end gap-1.5">
                    <button
                        type="button"
                        onClick={() => handleOpenDetail(c)}
                        title="Lihat Riwayat & Detail"
                        className="rounded p-1 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900"
                    >
                        <Eye className="h-3.5 w-3.5" />
                    </button>
                    {!c.anonymized_at && (
                        <>
                            <button
                                type="button"
                                onClick={() => handleOpenEdit(c)}
                                title="Edit Profil"
                                className="rounded p-1 text-slate-500 transition-colors hover:bg-slate-100 hover:text-blue-600"
                            >
                                <User className="h-3.5 w-3.5" />
                            </button>
                            <button
                                type="button"
                                onClick={() => handleOpenMerge(c)}
                                title="Gabungkan Akun Duplikat"
                                className="rounded p-1 text-slate-500 transition-colors hover:bg-slate-100 hover:text-amber-600"
                            >
                                <GitMerge className="h-3.5 w-3.5" />
                            </button>
                        </>
                    )}
                </div>
            ),
        },
    ];

    const breadcrumbs = [
        { label: 'Dashboard', href: '/app/dashboard' },
        { label: 'Pelanggan' },
    ];

    const actions = (
        <div className="flex items-center gap-2">
            <Button
                variant="outline"
                size="sm"
                onClick={handleExport}
                className="gap-1.5"
                disabled={!canExport}
                title={
                    !canExport
                        ? 'Membutuhkan izin customer.export untuk mengunduh data'
                        : 'Ekspor data pelanggan ke file CSV'
                }
            >
                <Download className="h-3.5 w-3.5" />
                <span>Ekspor CSV</span>
            </Button>
            <Button
                variant="primary"
                size="sm"
                onClick={() => setIsCreateOpen(true)}
                className="gap-1.5"
            >
                <Plus className="h-3.5 w-3.5" />
                <span>Tambah Pelanggan</span>
            </Button>
        </div>
    );

    return (
        <OwnerLayout
            title="Data Pelanggan & CRM"
            breadcrumbs={breadcrumbs}
            actions={actions}
        >
            <div className="space-y-4">
                {/* Segment Presets Navigation */}
                <div className="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs">
                    {SEGMENT_OPTIONS.map((seg) => {
                        const active = selectedSegment === seg.key;
                        return (
                            <button
                                key={seg.key}
                                type="button"
                                onClick={() => handleSegmentChange(seg.key)}
                                className={`rounded-full px-3 py-1 text-xs font-medium whitespace-nowrap transition-colors ${
                                    active
                                        ? 'bg-blue-600 text-white'
                                        : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-100'
                                }`}
                            >
                                {seg.label}
                            </button>
                        );
                    })}
                </div>

                {/* Search & Tag Filter Bar */}
                <div className="rounded-[12px] border border-slate-200 bg-white p-3 shadow-none">
                    <form
                        onSubmit={handleSearchSubmit}
                        className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div className="flex flex-1 items-center gap-2">
                            <div className="relative flex-1">
                                <Search className="absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Cari nama, telepon (+62/08), email, atau kode booking (BK-...)"
                                    className="h-9 w-full rounded-[8px] border border-slate-200 bg-[#f8fafc] pr-3 pl-8 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:bg-white focus:outline-none"
                                />
                            </div>
                            <Button type="submit" variant="secondary" size="sm">
                                Cari
                            </Button>
                            {(search ||
                                selectedTag !== 'ALL' ||
                                selectedSegment !== 'ALL') && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={handleResetFilter}
                                    className="text-slate-500"
                                >
                                    Reset
                                </Button>
                            )}
                        </div>

                        {tags.length > 0 && (
                            <div className="flex items-center gap-1.5 overflow-x-auto text-xs">
                                <span className="shrink-0 text-[11px] font-medium text-slate-400">
                                    Tag:
                                </span>
                                <button
                                    type="button"
                                    onClick={() => handleTagChange('ALL')}
                                    className={`rounded-full px-2.5 py-1 text-[11px] font-medium transition-colors ${
                                        selectedTag === 'ALL'
                                            ? 'bg-slate-800 text-white'
                                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                    }`}
                                >
                                    Semua
                                </button>
                                {tags.map((t) => (
                                    <button
                                        key={t}
                                        type="button"
                                        onClick={() => handleTagChange(t)}
                                        className={`rounded-full px-2.5 py-1 text-[11px] font-medium transition-colors ${
                                            selectedTag === t
                                                ? 'bg-slate-800 text-white'
                                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                        }`}
                                    >
                                        {t}
                                    </button>
                                ))}
                            </div>
                        )}
                    </form>
                </div>

                {/* Customers Table */}
                <div className="space-y-3">
                    <DataTable
                        columns={columns}
                        data={customers.data}
                        keyExtractor={(item) => item.id}
                        emptyText="Belum ada data pelanggan yang cocok dengan segmen atau pencarian."
                    />

                    {/* Pagination */}
                    {customers.total > customers.per_page && (
                        <div className="flex items-center justify-between rounded-[12px] border border-slate-200 bg-white px-4 py-2.5 text-xs text-slate-500">
                            <div>
                                Menampilkan{' '}
                                <span className="font-medium text-slate-900">
                                    {customers.from || 0}
                                </span>{' '}
                                -{' '}
                                <span className="font-medium text-slate-900">
                                    {customers.to || 0}
                                </span>{' '}
                                dari{' '}
                                <span className="font-medium text-slate-900">
                                    {customers.total}
                                </span>{' '}
                                pelanggan
                            </div>

                            <div className="flex items-center gap-1">
                                {customers.links.map((link, idx) => {
                                    if (!link.url) {
                                        return (
                                            <span
                                                key={idx}
                                                className="px-2 py-1 text-slate-300 select-none"
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        );
                                    }
                                    return (
                                        <Link
                                            key={idx}
                                            href={link.url}
                                            preserveState
                                            className={`rounded px-2.5 py-1 text-xs font-medium transition-colors ${
                                                link.active
                                                    ? 'bg-blue-600 text-white'
                                                    : 'text-slate-600 hover:bg-slate-100'
                                            }`}
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Create Customer Modal */}
            <Modal
                isOpen={isCreateOpen}
                onClose={() => setIsCreateOpen(false)}
                title="Tambah Pelanggan Baru"
                description="Nomor telepon akan otomatis dinormalisasi ke standar E.164 (+62...)."
            >
                <form onSubmit={handleCreateSubmit} className="space-y-3">
                    <Input
                        label="Nama Lengkap"
                        required
                        value={createForm.name}
                        onChange={(e) =>
                            setCreateForm({
                                ...createForm,
                                name: e.target.value,
                            })
                        }
                        placeholder="Contoh: Budi Santoso"
                        error={createErrors.name}
                    />

                    <div>
                        <Input
                            label="Nomor Telepon"
                            required
                            value={createForm.phone}
                            onChange={(e) =>
                                setCreateForm({
                                    ...createForm,
                                    phone: e.target.value,
                                })
                            }
                            placeholder="Contoh: 08123456789 atau +628123456789"
                            error={createErrors.phone}
                        />
                        {createForm.phone.trim() && (
                            <div className="mt-1 font-mono text-[11px] text-slate-500">
                                Format E.164:{' '}
                                <span className="font-semibold text-blue-600">
                                    {normalizePhonePreview(createForm.phone)}
                                </span>
                            </div>
                        )}
                    </div>

                    <Input
                        label="Email (Opsional)"
                        type="email"
                        value={createForm.email}
                        onChange={(e) =>
                            setCreateForm({
                                ...createForm,
                                email: e.target.value,
                            })
                        }
                        placeholder="Contoh: budi@example.com"
                        error={createErrors.email}
                    />

                    <Input
                        label="Tag / Label (Pisahkan dengan koma)"
                        value={createForm.tagsInput}
                        onChange={(e) =>
                            setCreateForm({
                                ...createForm,
                                tagsInput: e.target.value,
                            })
                        }
                        placeholder="regular, vip, corporate"
                        error={createErrors.tags}
                    />

                    <Textarea
                        label="Catatan Pelanggan"
                        rows={2}
                        value={createForm.notes}
                        onChange={(e) =>
                            setCreateForm({
                                ...createForm,
                                notes: e.target.value,
                            })
                        }
                        placeholder="Preferensi khusus, alergi, catatan layanan, dll."
                        error={createErrors.notes}
                    />

                    <div className="rounded-[8px] border border-slate-200 bg-slate-50 p-2.5">
                        <label className="flex cursor-pointer items-start gap-2 text-xs">
                            <input
                                type="checkbox"
                                checked={createForm.marketing_consent}
                                onChange={(e) =>
                                    setCreateForm({
                                        ...createForm,
                                        marketing_consent: e.target.checked,
                                    })
                                }
                                className="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-0"
                            />
                            <div>
                                <span className="font-medium text-slate-900">
                                    Persetujuan Komunikasi Pemasaran (Marketing
                                    Consent)
                                </span>
                                <p className="text-[11px] text-slate-500">
                                    Sesuai PRD 40: Booking reguler tidak
                                    otomatis memberikan izin pemasaran. Hanya
                                    centang jika pelanggan secara eksplisit
                                    menyetujui.
                                </p>
                            </div>
                        </label>
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsCreateOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant="primary"
                            isLoading={isSubmittingCreate}
                        >
                            Simpan Pelanggan
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Edit Customer Modal */}
            <Modal
                isOpen={isEditOpen}
                onClose={() => setIsEditOpen(false)}
                title="Edit Profil Pelanggan"
                description="Perbarui informasi kontak, tag, catatan internal, dan persetujuan komunikasi."
            >
                <form onSubmit={handleEditSubmit} className="space-y-3">
                    <Input
                        label="Nama Lengkap"
                        required
                        value={editForm.name}
                        onChange={(e) =>
                            setEditForm({ ...editForm, name: e.target.value })
                        }
                        error={editErrors.name}
                    />

                    <div>
                        <Input
                            label="Nomor Telepon"
                            required
                            value={editForm.phone}
                            onChange={(e) =>
                                setEditForm({
                                    ...editForm,
                                    phone: e.target.value,
                                })
                            }
                            error={editErrors.phone}
                        />
                        {editForm.phone.trim() && (
                            <div className="mt-1 font-mono text-[11px] text-slate-500">
                                Format E.164:{' '}
                                <span className="font-semibold text-blue-600">
                                    {normalizePhonePreview(editForm.phone)}
                                </span>
                            </div>
                        )}
                    </div>

                    <Input
                        label="Email"
                        type="email"
                        value={editForm.email}
                        onChange={(e) =>
                            setEditForm({ ...editForm, email: e.target.value })
                        }
                        error={editErrors.email}
                    />

                    <Input
                        label="Tag / Label (Pisahkan dengan koma)"
                        value={editForm.tagsInput}
                        onChange={(e) =>
                            setEditForm({
                                ...editForm,
                                tagsInput: e.target.value,
                            })
                        }
                    />

                    <Input
                        label="Jumlah No-Show"
                        type="number"
                        min="0"
                        value={editForm.no_show_count}
                        onChange={(e) =>
                            setEditForm({
                                ...editForm,
                                no_show_count: Number(e.target.value),
                            })
                        }
                        error={editErrors.no_show_count}
                    />

                    <Textarea
                        label="Catatan Pelanggan"
                        rows={3}
                        value={editForm.notes}
                        onChange={(e) =>
                            setEditForm({ ...editForm, notes: e.target.value })
                        }
                    />

                    <div className="rounded-[8px] border border-slate-200 bg-slate-50 p-2.5">
                        <label className="flex cursor-pointer items-start gap-2 text-xs">
                            <input
                                type="checkbox"
                                checked={editForm.marketing_consent}
                                onChange={(e) =>
                                    setEditForm({
                                        ...editForm,
                                        marketing_consent: e.target.checked,
                                    })
                                }
                                className="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-0"
                            />
                            <div>
                                <span className="font-medium text-slate-900">
                                    Marketing Consent Aktif
                                </span>
                                <p className="text-[11px] text-slate-500">
                                    Centang jika pelanggan menyetujui pesan
                                    promosi berkala via WhatsApp / Email.
                                </p>
                            </div>
                        </label>
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsEditOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant="primary"
                            isLoading={isSubmittingEdit}
                        >
                            Simpan Perubahan
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Merge Modal (PRD 210 point 14) */}
            <Modal
                isOpen={isMergeOpen}
                onClose={() => setIsMergeOpen(false)}
                title="Gabungkan Pelanggan Duplikat"
                description={`Gabungkan data akun duplikat ke dalam akun target: ${targetCustomer?.name || ''}`}
            >
                <form onSubmit={handleMergeSubmit} className="space-y-3">
                    <div className="flex items-start gap-2 rounded-[8px] border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                        <div>
                            <span className="mb-0.5 block font-semibold">
                                Tindakan Tidak Dapat Dibatalkan!
                            </span>
                            Semua riwayat booking dari akun sumber akan
                            dipindahkan ke{' '}
                            <strong>{targetCustomer?.name}</strong>. Tag dan
                            catatan akan digabungkan, dan akun sumber akan
                            dihapus permanen.
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-medium text-slate-700">
                            Pilih Akun Sumber yang Ingin Digabungkan
                        </label>
                        <select
                            value={selectedSourceId}
                            onChange={(e) =>
                                setSelectedSourceId(e.target.value)
                            }
                            required
                            className="w-full rounded-[8px] border border-slate-200 bg-[#f8fafc] px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:bg-white focus:outline-none"
                        >
                            <option value="">-- Pilih Akun Sumber --</option>
                            {customers.data
                                .filter(
                                    (c) =>
                                        c.id !== targetCustomer?.id &&
                                        !c.anonymized_at
                                )
                                .map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name} ({c.phone_e164}) -{' '}
                                        {c.bookings_count} booking
                                    </option>
                                ))}
                        </select>
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsMergeOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant="danger"
                            disabled={!selectedSourceId}
                            isLoading={isSubmittingMerge}
                        >
                            Gabungkan Sekarang
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Anonymize Confirmation Modal (PRD 54) */}
            <Modal
                isOpen={isAnonymizeOpen}
                onClose={() => setIsAnonymizeOpen(false)}
                title="Hapus / Anonimkan Data Pribadi Pelanggan"
                description="Penghapusan data privasi (GDPR / UU PDP - PRD 54)."
            >
                <form onSubmit={handleAnonymizeSubmit} className="space-y-3">
                    <div className="flex items-start gap-2 rounded-[8px] border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800">
                        <ShieldAlert className="mt-0.5 h-4 w-4 shrink-0 text-rose-600" />
                        <div>
                            <span className="mb-0.5 block font-semibold">
                                Perhatian: Redaksi Identitas Permanen!
                            </span>
                            Nama, email, telepon, dan seluruh catatan pelanggan
                            akan dihapus dan diganti dengan identitas anonim.
                            Persetujuan pemasaran akan dicabut.
                            <br />
                            <strong>Catatan:</strong> Riwayat booking dan
                            transaksi keuangan tetap disimpan untuk keperluan
                            akuntansi dan audit laporan bisnis.
                        </div>
                    </div>

                    <Textarea
                        label="Alasan Penghapusan / Anonimisasi (Opsional)"
                        rows={2}
                        value={anonymizeReason}
                        onChange={(e) => setAnonymizeReason(e.target.value)}
                        placeholder="Contoh: Permintaan pemilik data via WhatsApp sesuai UU PDP."
                    />

                    <div className="flex justify-end gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsAnonymizeOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant="danger"
                            isLoading={isSubmittingAnonymize}
                        >
                            Konfirmasi & Anonimkan Data
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Detail, Notes & Booking History Drawer */}
            <Drawer
                isOpen={isDrawerOpen}
                onClose={() => setIsDrawerOpen(false)}
                title={activeCustomer?.name || 'Detail Pelanggan'}
                size="md"
            >
                {activeCustomer && (
                    <div className="space-y-4 text-xs">
                        {/* Summary & Lifetime Stats */}
                        <div className="space-y-3 rounded-[12px] border border-slate-200 bg-white p-3.5 shadow-none">
                            <div className="flex items-center justify-between">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm font-semibold text-slate-900">
                                            {activeCustomer.name}
                                        </span>
                                        {activeCustomer.anonymized_at && (
                                            <span className="rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-[9px] font-bold text-rose-700">
                                                ANONIM
                                            </span>
                                        )}
                                    </div>
                                    <span className="font-mono text-[11px] text-slate-500">
                                        {activeCustomer.phone_e164}
                                    </span>
                                </div>
                                <span
                                    className={`rounded-full border px-2.5 py-0.5 text-[10px] font-medium ${
                                        activeCustomer.marketing_consent_at !==
                                            null && !activeCustomer.anonymized_at
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                            : 'border-slate-200 bg-slate-100 text-slate-500'
                                    }`}
                                >
                                    {activeCustomer.marketing_consent_at !==
                                        null && !activeCustomer.anonymized_at
                                        ? 'Marketing Consent Aktif'
                                        : 'Tanpa Marketing Consent'}
                                </span>
                            </div>

                            {/* Lifetime KPI Grid */}
                            <div className="grid grid-cols-2 gap-2 rounded-[8px] border border-slate-100 bg-[#f8fafc] p-2.5 text-[11px]">
                                <div>
                                    <span className="block text-slate-400">
                                        Total Booking
                                    </span>
                                    <span className="font-medium text-slate-900">
                                        {customerStats?.total_bookings ??
                                            activeCustomer.bookings_count}{' '}
                                        kali
                                    </span>
                                    {customerStats && (
                                        <div className="mt-0.5 text-[10px] text-slate-500">
                                            {customerStats.completed_bookings}{' '}
                                            selesai •{' '}
                                            {customerStats.cancelled_bookings}{' '}
                                            batal
                                        </div>
                                    )}
                                </div>
                                <div>
                                    <span className="block text-slate-400">
                                        Total Pengeluaran (LTV)
                                    </span>
                                    <span className="font-semibold text-slate-900">
                                        Rp{' '}
                                        {customerStats?.total_spent_idr
                                            ? customerStats.total_spent_idr.toLocaleString(
                                                  'id-ID'
                                              )
                                            : '0'}
                                    </span>
                                </div>
                                <div>
                                    <span className="block text-slate-400">
                                        No-Show
                                    </span>
                                    <span
                                        className={`font-medium ${
                                            activeCustomer.no_show_count > 0
                                                ? 'text-rose-600'
                                                : 'text-slate-900'
                                        }`}
                                    >
                                        {activeCustomer.no_show_count} kali
                                    </span>
                                </div>
                                <div>
                                    <span className="block text-slate-400">
                                        Email
                                    </span>
                                    <span className="block truncate text-slate-900">
                                        {activeCustomer.email || '-'}
                                    </span>
                                </div>
                            </div>

                            {customerStats?.first_booking_at && (
                                <div className="flex items-center justify-between text-[10px] text-slate-400">
                                    <span>
                                        Pertama kali:{' '}
                                        {new Date(
                                            customerStats.first_booking_at
                                        ).toLocaleDateString('id-ID')}
                                    </span>
                                    {customerStats.last_booking_at && (
                                        <span>
                                            Terakhir:{' '}
                                            {new Date(
                                                customerStats.last_booking_at
                                            ).toLocaleDateString('id-ID')}
                                        </span>
                                    )}
                                </div>
                            )}

                            {/* Anonymize Action Button (PRD 54) */}
                            {!activeCustomer.anonymized_at && (
                                <div className="border-t border-slate-100 pt-2 text-right">
                                    <button
                                        type="button"
                                        onClick={() => setIsAnonymizeOpen(true)}
                                        className="inline-flex items-center gap-1 text-[11px] text-rose-600 hover:text-rose-700 hover:underline"
                                    >
                                        <Trash2 className="h-3 w-3" />
                                        Hapus / Anonimkan Data Pribadi (PRD 54)
                                    </button>
                                </div>
                            )}
                        </div>

                        {/* Internal Notes & Timeline Section (PRD 39) */}
                        <div className="space-y-2 rounded-[12px] border border-slate-200 bg-white p-3.5">
                            <div className="flex items-center justify-between">
                                <h4 className="flex items-center gap-1.5 font-semibold text-slate-900">
                                    <FileText className="h-3.5 w-3.5 text-slate-500" />
                                    Catatan Internal Tim
                                </h4>
                                <span className="text-[10px] text-slate-400">
                                    Hanya dapat dilihat staf
                                </span>
                            </div>

                            {activeCustomer.notes ? (
                                <div className="rounded-[8px] border border-slate-100 bg-[#f8fafc] p-2.5">
                                    <p className="whitespace-pre-wrap text-[11px] leading-relaxed text-slate-700">
                                        {activeCustomer.notes}
                                    </p>
                                </div>
                            ) : (
                                <div className="rounded-[8px] border border-dashed border-slate-200 p-2 text-center text-[11px] text-slate-400">
                                    Belum ada catatan internal untuk pelanggan
                                    ini.
                                </div>
                            )}

                            {!activeCustomer.anonymized_at && (
                                <form
                                    onSubmit={handleAddNoteSubmit}
                                    className="space-y-2 pt-1"
                                >
                                    <Textarea
                                        rows={2}
                                        value={newNote}
                                        onChange={(e) =>
                                            setNewNote(e.target.value)
                                        }
                                        placeholder="Tambahkan catatan khusus staf (misal: preferensi tempat duduk, request khusus)..."
                                        className="text-xs"
                                    />
                                    <div className="flex justify-end">
                                        <Button
                                            type="submit"
                                            size="sm"
                                            variant="secondary"
                                            disabled={!newNote.trim()}
                                            isLoading={isSubmittingNote}
                                            className="h-7 text-[11px]"
                                        >
                                            Simpan Catatan
                                        </Button>
                                    </div>
                                </form>
                            )}
                        </div>

                        {/* Recent Bookings History (PRD 39) */}
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <h4 className="flex items-center gap-1.5 font-semibold text-slate-900">
                                    <History className="h-3.5 w-3.5 text-slate-400" />
                                    Riwayat Booking & Layanan
                                </h4>
                                <span className="text-[11px] text-slate-400">
                                    {customerBookings.length} booking
                                </span>
                            </div>

                            {isLoadingDetail ? (
                                <div className="py-6 text-center text-slate-400">
                                    Memuat riwayat transaksi...
                                </div>
                            ) : customerBookings.length === 0 ? (
                                <div className="rounded-[8px] border border-dashed border-slate-200 p-6 text-center text-xs text-slate-400">
                                    Belum ada riwayat booking.
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {customerBookings.map((b) => (
                                        <div
                                            key={b.id}
                                            className="rounded-[8px] border border-slate-200 bg-white p-2.5 transition-colors hover:border-slate-300"
                                        >
                                            <div className="mb-1 flex items-center justify-between text-xs">
                                                <span className="font-mono font-medium text-slate-900">
                                                    {b.code}
                                                </span>
                                                <span
                                                    className={`rounded-full px-2 py-0.5 text-[10px] font-medium ${
                                                        b.status_category ===
                                                        'COMPLETED'
                                                            ? 'bg-emerald-50 text-emerald-700'
                                                            : b.status_category ===
                                                                  'CONFIRMED'
                                                                ? 'bg-blue-50 text-blue-700'
                                                                : b.status_category ===
                                                                      'CANCELLED'
                                                                    ? 'bg-rose-50 text-rose-700'
                                                                    : b.status_category ===
                                                                          'NO_SHOW'
                                                                        ? 'bg-amber-50 text-amber-700'
                                                                        : 'bg-slate-100 text-slate-600'
                                                    }`}
                                                >
                                                    {b.status_category}
                                                </span>
                                            </div>

                                            {b.service && (
                                                <div className="text-[11px] font-medium text-slate-800">
                                                    {b.service.name}
                                                </div>
                                            )}

                                            {b.allocations &&
                                                b.allocations.length > 0 && (
                                                    <div className="text-[10px] text-slate-500">
                                                        Staf / Resource:{' '}
                                                        {b.allocations
                                                            .map(
                                                                (a) =>
                                                                    a.resource
                                                                        ?.name
                                                            )
                                                            .filter(Boolean)
                                                            .join(', ')}
                                                    </div>
                                                )}

                                            <div className="mt-1 flex items-center justify-between text-[11px] text-slate-500">
                                                <span className="flex items-center gap-1">
                                                    <Clock className="h-3 w-3 text-slate-400" />
                                                    {new Date(
                                                        b.start_at
                                                    ).toLocaleString('id-ID', {
                                                        dateStyle: 'medium',
                                                        timeStyle: 'short',
                                                    })}
                                                </span>
                                                <span className="font-medium text-slate-900">
                                                    Rp{' '}
                                                    {Number(
                                                        b.total_idr
                                                    ).toLocaleString('id-ID')}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </Drawer>
        </OwnerLayout>
    );
}
