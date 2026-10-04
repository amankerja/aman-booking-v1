import { Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    Clock,
    Download,
    Eye,
    GitMerge,
    History,
    Phone,
    Plus,
    Search,
    User,
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
    start_at: string;
    end_at: string;
    total_idr: number;
    notes?: string | null;
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
    is_verified: boolean;
    created_at: string;
    updated_at: string;
    bookings_count: number;
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
    };
    tags: string[];
    canExport: boolean;
}

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
    const [createErrors, setCreateErrors] = useState<Record<string, string>>(
        {}
    );
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
    const [customerBookings, setCustomerBookings] = useState<BookingItem[]>([]);
    const [isLoadingDetail, setIsLoadingDetail] = useState(false);

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/app/customers',
            {
                search: search.trim() || undefined,
                tag: selectedTag !== 'ALL' ? selectedTag : undefined,
            },
            { preserveState: true, replace: true }
        );
    };

    const handleTagChange = (newTag: string) => {
        setSelectedTag(newTag);
        router.get(
            '/app/customers',
            {
                search: search.trim() || undefined,
                tag: newTag !== 'ALL' ? newTag : undefined,
            },
            { preserveState: true, replace: true }
        );
    };

    const handleResetFilter = () => {
        setSearch('');
        setSelectedTag('ALL');
        router.get(
            '/app/customers',
            {},
            { preserveState: true, replace: true }
        );
    };

    const handleOpenDetail = async (c: CustomerItem) => {
        setActiveCustomer(c);
        setIsDrawerOpen(true);
        setIsLoadingDetail(true);
        try {
            const res = await fetch(`/app/customers/${c.id}`, {
                headers: { Accept: 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                setCustomerBookings(data.customer?.bookings || []);
            }
        } catch {
            toast.error('Gagal memuat data riwayat booking.');
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
                },
                onFinish: () => setIsSubmittingMerge(false),
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
            render: (c: CustomerItem) => (
                <div className="flex items-center gap-3">
                    <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-700">
                        {c.name.substring(0, 2).toUpperCase()}
                    </div>
                    <div className="min-w-0">
                        <button
                            type="button"
                            onClick={() => handleOpenDetail(c)}
                            className="block truncate text-left font-medium text-slate-900 hover:text-blue-600 hover:underline"
                        >
                            {c.name}
                        </button>
                        <div className="truncate text-[11px] text-slate-500">
                            {c.email || 'Tanpa email'}
                        </div>
                    </div>
                </div>
            ),
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
                const hasConsent = c.marketing_consent_at !== null;
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
            title="Data Pelanggan"
            breadcrumbs={breadcrumbs}
            actions={actions}
        >
            <div className="space-y-4">
                {/* Search & Filter Bar */}
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
                            {(search || selectedTag !== 'ALL') && (
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
                                            ? 'bg-blue-600 text-white'
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
                                                ? 'bg-blue-600 text-white'
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
                        emptyText="Belum ada data pelanggan yang cocok dengan pencarian."
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
                                .filter((c) => c.id !== targetCustomer?.id)
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

            {/* Detail & Booking History Drawer */}
            <Drawer
                isOpen={isDrawerOpen}
                onClose={() => setIsDrawerOpen(false)}
                title={activeCustomer?.name || 'Detail Pelanggan'}
                size="md"
            >
                {activeCustomer && (
                    <div className="space-y-4 text-xs">
                        {/* Summary Card */}
                        <div className="space-y-2 rounded-[10px] border border-slate-200 bg-slate-50 p-3">
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-semibold text-slate-900">
                                    {activeCustomer.name}
                                </span>
                                <span
                                    className={`rounded-full border px-2 py-0.5 text-[10px] font-medium ${
                                        activeCustomer.marketing_consent_at !==
                                        null
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                            : 'border-slate-200 bg-slate-100 text-slate-500'
                                    }`}
                                >
                                    {activeCustomer.marketing_consent_at !==
                                    null
                                        ? 'Marketing Consent Aktif'
                                        : 'Tanpa Marketing Consent'}
                                </span>
                            </div>

                            <div className="grid grid-cols-2 gap-2 pt-1 text-[11px] text-slate-600">
                                <div>
                                    <span className="block text-slate-400">
                                        Telepon
                                    </span>
                                    <span className="font-mono font-medium text-slate-900">
                                        {activeCustomer.phone_e164}
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
                                <div>
                                    <span className="block text-slate-400">
                                        Total Booking
                                    </span>
                                    <span className="font-medium text-slate-900">
                                        {activeCustomer.bookings_count} kali
                                    </span>
                                </div>
                                <div>
                                    <span className="block text-slate-400">
                                        No-Show Count
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
                            </div>

                            {activeCustomer.notes && (
                                <div className="border-t border-slate-200 pt-2 text-[11px]">
                                    <span className="mb-0.5 block font-medium text-slate-400">
                                        Catatan Internal:
                                    </span>
                                    <p className="rounded border border-slate-200 bg-white p-2 whitespace-pre-wrap text-slate-700">
                                        {activeCustomer.notes}
                                    </p>
                                </div>
                            )}
                        </div>

                        {/* Recent Bookings List */}
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <h4 className="flex items-center gap-1.5 font-semibold text-slate-900">
                                    <History className="h-3.5 w-3.5 text-slate-400" />
                                    Riwayat Booking Terbaru
                                </h4>
                                <span className="text-[11px] text-slate-400">
                                    Maksimal 20 transaksi
                                </span>
                            </div>

                            {isLoadingDetail ? (
                                <div className="py-8 text-center text-slate-400">
                                    Memuat riwayat booking...
                                </div>
                            ) : customerBookings.length === 0 ? (
                                <div className="rounded-[8px] border border-dashed border-slate-200 p-6 text-center text-xs text-slate-400">
                                    Belum ada transaksi booking tercatat untuk
                                    pelanggan ini.
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
                                                                : 'bg-slate-100 text-slate-600'
                                                    }`}
                                                >
                                                    {b.status_category}
                                                </span>
                                            </div>

                                            <div className="flex items-center justify-between text-[11px] text-slate-500">
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
