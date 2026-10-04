import {
    Activity,
    Mail,
    Maximize2,
    Monitor,
    Palette,
    Phone,
    Plus,
    Send,
    Smartphone,
    Tablet,
    Trash2,
    User,
} from 'lucide-react';
import React, { useState } from 'react';
import {
    Badge,
    Button,
    Column,
    ConfirmationDialog,
    DataTable,
    Drawer,
    EmptyState,
    ErrorState,
    Input,
    Modal,
    Select,
    Skeleton,
    Tabs,
    Textarea,
    Tooltip,
    useToast,
} from '../../Components/ui';
import { OwnerLayout } from '../../Layouts/OwnerLayout';

interface SampleRow {
    id: string;
    code: string;
    customer: string;
    service: string;
    status: 'ACTIVE' | 'QUEUING' | 'HAULING' | 'BREAKDOWN';
    amount: number;
    date: string;
}

const sampleData: SampleRow[] = [
    {
        id: '1',
        code: 'BK-2026-001',
        customer: 'Budi Santoso',
        service: 'Full Fleet Hauling',
        status: 'HAULING',
        amount: 2500000,
        date: '2026-10-04 10:30',
    },
    {
        id: '2',
        code: 'BK-2026-002',
        customer: 'PT Maju Logistik',
        service: 'Heavy Equipment Dispatch',
        status: 'ACTIVE',
        amount: 5000000,
        date: '2026-10-04 11:15',
    },
    {
        id: '3',
        code: 'BK-2026-003',
        customer: 'Siti Rahmawati',
        service: 'Express Delivery',
        status: 'QUEUING',
        amount: 750000,
        date: '2026-10-04 12:00',
    },
    {
        id: '4',
        code: 'BK-2026-004',
        customer: 'CV Berkah Tambang',
        service: 'Dump Truck Maintenance',
        status: 'BREAKDOWN',
        amount: 1800000,
        date: '2026-10-04 12:45',
    },
];

export default function Styleguide() {
    const toast = useToast();

    // Viewport Simulation State
    const [viewportWidth, setViewportWidth] = useState<
        'full' | '1280' | '768' | '360'
    >('full');

    // Interactive component states
    const [isButtonLoading, setIsButtonLoading] = useState(false);
    const [activeTabLine, setActiveTabLine] = useState('tab-1');
    const [activeTabPills, setActiveTabPills] = useState('pill-1');

    // Dialog & Drawer states
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isDrawerRightOpen, setIsDrawerRightOpen] = useState(false);
    const [isDrawerBottomOpen, setIsDrawerBottomOpen] = useState(false);
    const [isConfirmOpen, setIsConfirmOpen] = useState(false);
    const [isConfirmLoading, setIsConfirmLoading] = useState(false);

    // Form inputs states
    const [inputValue, setInputValue] = useState('');
    const [inputError, setInputError] = useState('');
    const [textareaValue, setTextareaValue] = useState('');
    const [selectedOption, setSelectedOption] = useState('pro');

    // Table state
    const [tableData, setTableData] = useState<SampleRow[]>(sampleData);
    const [tableSortKey, setTableSortKey] = useState<string>('date');
    const [tableSortDirection, setTableSortDirection] = useState<
        'asc' | 'desc'
    >('desc');
    const [isTableLoading, setIsTableLoading] = useState(false);
    const [isTableEmpty, setIsTableEmpty] = useState(false);

    const handleSort = (key: string) => {
        if (tableSortKey === key) {
            setTableSortDirection((prev) => (prev === 'asc' ? 'desc' : 'asc'));
        } else {
            setTableSortKey(key);
            setTableSortDirection('asc');
        }
    };

    const handleConfirmDelete = () => {
        setIsConfirmLoading(true);
        setTimeout(() => {
            setIsConfirmLoading(false);
            setIsConfirmOpen(false);
            toast.success('Entitas berhasil dihapus dengan aman.');
        }, 1200);
    };

    const columns: Column<SampleRow>[] = [
        {
            key: 'code',
            header: 'Kode Booking',
            sortable: true,
            render: (row) => (
                <span className="font-mono font-semibold text-blue-600">
                    {row.code}
                </span>
            ),
        },
        {
            key: 'customer',
            header: 'Pelanggan',
            sortable: true,
            render: (row) => (
                <div>
                    <p className="font-medium text-slate-900">{row.customer}</p>
                    <p className="text-[10px] text-slate-400">{row.service}</p>
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            sortable: true,
            render: (row) => {
                const variantMap = {
                    ACTIVE: 'active',
                    QUEUING: 'queuing',
                    HAULING: 'hauling',
                    BREAKDOWN: 'breakdown',
                } as const;
                return (
                    <Badge variant={variantMap[row.status]} size="sm">
                        {row.status}
                    </Badge>
                );
            },
        },
        {
            key: 'amount',
            header: 'Nilai Transaksi',
            sortable: true,
            align: 'right',
            render: (row) => (
                <span className="font-mono text-slate-700">
                    Rp {row.amount.toLocaleString('id-ID')}
                </span>
            ),
        },
        {
            key: 'date',
            header: 'Waktu (UTC)',
            sortable: true,
            align: 'right',
            render: (row) => (
                <span className="font-mono text-[11px] text-slate-500">
                    {row.date}
                </span>
            ),
        },
    ];

    const getViewportContainerClass = () => {
        switch (viewportWidth) {
            case '360':
                return 'max-w-[360px] mx-auto border-x border-slate-300 shadow-sm';
            case '768':
                return 'max-w-[768px] mx-auto border-x border-slate-300 shadow-sm';
            case '1280':
                return 'max-w-[1280px] mx-auto';
            default:
                return 'w-full';
        }
    };

    return (
        <OwnerLayout
            title="Design System & Styleguide"
            breadcrumbs={[
                { label: 'Workspace', href: '/app/dashboard' },
                { label: 'Design System & UI Components' },
            ]}
        >
            <div className="space-y-8 pb-12">
                {/* Header Section */}
                <div className="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-lg font-bold text-slate-900">
                                OFALabs Design System (AMAN BOOKING)
                            </h1>
                            <Badge variant="hauling" size="sm">
                                v1.2 Specification
                            </Badge>
                        </div>
                        <p className="mt-1 max-w-2xl text-xs leading-relaxed text-slate-500">
                            Arsitektur komponen antarmuka data-dense berbasis
                            headless React 19, Tailwind CSS v4, Lucide stroke
                            icons, dan standar kontras aksesibilitas tinggi.
                            Dirancang tanpa gradien tebal, tanpa bayangan pekat,
                            dan flat industrial.
                        </p>
                    </div>

                    {/* Viewport Simulation Controls */}
                    <div className="flex shrink-0 items-center gap-1 rounded-lg border border-slate-200 bg-white p-1">
                        <button
                            type="button"
                            onClick={() => setViewportWidth('full')}
                            className={`flex items-center gap-1 rounded p-1.5 text-xs font-medium transition-colors ${
                                viewportWidth === 'full'
                                    ? 'bg-blue-50 text-blue-700'
                                    : 'text-slate-600 hover:text-slate-900'
                            }`}
                            title="Full Width"
                        >
                            <Maximize2 className="h-3.5 w-3.5" />
                            <span className="hidden md:inline">Full</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setViewportWidth('1280')}
                            className={`flex items-center gap-1 rounded p-1.5 text-xs font-medium transition-colors ${
                                viewportWidth === '1280'
                                    ? 'bg-blue-50 text-blue-700'
                                    : 'text-slate-600 hover:text-slate-900'
                            }`}
                            title="Desktop 1280px"
                        >
                            <Monitor className="h-3.5 w-3.5" />
                            <span className="hidden md:inline">1280px</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setViewportWidth('768')}
                            className={`flex items-center gap-1 rounded p-1.5 text-xs font-medium transition-colors ${
                                viewportWidth === '768'
                                    ? 'bg-blue-50 text-blue-700'
                                    : 'text-slate-600 hover:text-slate-900'
                            }`}
                            title="Tablet 768px"
                        >
                            <Tablet className="h-3.5 w-3.5" />
                            <span className="hidden md:inline">768px</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setViewportWidth('360')}
                            className={`flex items-center gap-1 rounded p-1.5 text-xs font-medium transition-colors ${
                                viewportWidth === '360'
                                    ? 'bg-blue-50 text-blue-700'
                                    : 'text-slate-600 hover:text-slate-900'
                            }`}
                            title="Mobile 360px"
                        >
                            <Smartphone className="h-3.5 w-3.5" />
                            <span className="hidden md:inline">360px</span>
                        </button>
                    </div>
                </div>

                {/* Viewport Frame Container */}
                <div
                    className={`space-y-8 transition-all ${getViewportContainerClass()}`}
                >
                    {/* SECTION 1: Color Palette & Semantic Status Tokens */}
                    <div className="space-y-4 rounded-[14px] border border-slate-200 bg-white p-5">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h2 className="flex items-center gap-2 text-xs font-bold tracking-wider text-slate-900 uppercase">
                                <Palette className="h-4 w-4 text-blue-600" />
                                1. Token Warna & Status Semantik (Strict
                                OFALabs)
                            </h2>
                            <span className="font-mono text-[11px] text-slate-400">
                                PRD 12.2 / System Prompt
                            </span>
                        </div>

                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 md:grid-cols-6">
                            <div className="rounded-lg border border-slate-200 bg-slate-50 p-2.5">
                                <div className="mb-2 h-8 rounded border border-slate-200 bg-[#f8fafc]" />
                                <p className="text-xs font-semibold text-slate-900">
                                    Page Bg
                                </p>
                                <p className="font-mono text-[10px] text-slate-500">
                                    #f8fafc (Slate 50)
                                </p>
                            </div>
                            <div className="rounded-lg border border-slate-200 bg-white p-2.5">
                                <div className="mb-2 h-8 rounded border border-slate-200 bg-[#ffffff]" />
                                <p className="text-xs font-semibold text-slate-900">
                                    Surface Card
                                </p>
                                <p className="font-mono text-[10px] text-slate-500">
                                    #ffffff (White)
                                </p>
                            </div>
                            <div className="rounded-lg border border-slate-200 bg-white p-2.5">
                                <div className="mb-2 h-8 rounded bg-[#2563eb]" />
                                <p className="text-xs font-semibold text-slate-900">
                                    Action/Brand
                                </p>
                                <p className="font-mono text-[10px] text-slate-500">
                                    #2563eb (Blue 600)
                                </p>
                            </div>
                            <div className="rounded-lg border border-slate-200 bg-white p-2.5">
                                <div className="mb-2 h-8 rounded bg-[#0f172a]" />
                                <p className="text-xs font-semibold text-slate-900">
                                    Primary Text
                                </p>
                                <p className="font-mono text-[10px] text-slate-500">
                                    #0f172a (Slate 900)
                                </p>
                            </div>
                            <div className="rounded-lg border border-slate-200 bg-white p-2.5">
                                <div className="mb-2 h-8 rounded bg-[#64748b]" />
                                <p className="text-xs font-semibold text-slate-900">
                                    Secondary Text
                                </p>
                                <p className="font-mono text-[10px] text-slate-500">
                                    #64748b (Slate 500)
                                </p>
                            </div>
                            <div className="rounded-lg border border-slate-200 bg-white p-2.5">
                                <div className="mb-2 h-8 rounded bg-[#e2e8f0]" />
                                <p className="text-xs font-semibold text-slate-900">
                                    Border Divider
                                </p>
                                <p className="font-mono text-[10px] text-slate-500">
                                    #e2e8f0 (Slate 200)
                                </p>
                            </div>
                        </div>

                        {/* Semantic Badges Section */}
                        <div className="pt-2">
                            <p className="mb-2.5 text-xs font-semibold text-slate-700">
                                Status Badge (Wajib kombinasi Icon + Teks +
                                Warna):
                            </p>
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant="active">LOADING / ACTIVE</Badge>
                                <Badge variant="queuing">
                                    DUMPING / QUEUING
                                </Badge>
                                <Badge variant="hauling">
                                    HAULING / ON ROUTE
                                </Badge>
                                <Badge variant="breakdown">
                                    BREAKDOWN / DANGER
                                </Badge>
                                <Badge variant="neutral">NEUTRAL / DRAFT</Badge>
                                <Badge variant="active" size="sm">
                                    Active (Small)
                                </Badge>
                                <Badge variant="breakdown" size="sm">
                                    Critical (Small)
                                </Badge>
                            </div>
                        </div>
                    </div>

                    {/* SECTION 2: Buttons & Actions */}
                    <div className="space-y-4 rounded-[14px] border border-slate-200 bg-white p-5">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h2 className="flex items-center gap-2 text-xs font-bold tracking-wider text-slate-900 uppercase">
                                <Activity className="h-4 w-4 text-blue-600" />
                                2. Button Components
                            </h2>
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={() =>
                                    setIsButtonLoading(!isButtonLoading)
                                }
                            >
                                Toggle Loading State
                            </Button>
                        </div>

                        <div className="space-y-3">
                            <div className="flex flex-wrap items-center gap-2">
                                <Button
                                    variant="primary"
                                    isLoading={isButtonLoading}
                                >
                                    Primary Action
                                </Button>
                                <Button
                                    variant="secondary"
                                    isLoading={isButtonLoading}
                                >
                                    Secondary Action
                                </Button>
                                <Button
                                    variant="outline"
                                    isLoading={isButtonLoading}
                                >
                                    Outline Action
                                </Button>
                                <Button
                                    variant="danger"
                                    isLoading={isButtonLoading}
                                >
                                    Danger Action
                                </Button>
                                <Button
                                    variant="ghost"
                                    isLoading={isButtonLoading}
                                >
                                    Ghost Action
                                </Button>
                                <Button variant="primary" disabled>
                                    Disabled
                                </Button>
                            </div>

                            <div className="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-2">
                                <Button
                                    variant="primary"
                                    size="sm"
                                    leftIcon={<Plus className="h-3 w-3" />}
                                >
                                    Tambah Data (sm)
                                </Button>
                                <Button
                                    variant="secondary"
                                    size="md"
                                    leftIcon={<Mail className="h-3.5 w-3.5" />}
                                >
                                    Kirim Notifikasi (md)
                                </Button>
                                <Button
                                    variant="outline"
                                    size="lg"
                                    rightIcon={<Send className="h-4 w-4" />}
                                >
                                    Submit Form (lg)
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* SECTION 3: Form Controls (Input, Select, Textarea) */}
                    <div className="space-y-4 rounded-[14px] border border-slate-200 bg-white p-5">
                        <div className="border-b border-slate-100 pb-3">
                            <h2 className="text-xs font-bold tracking-wider text-slate-900 uppercase">
                                3. Form Controls (Input, Select, Textarea)
                            </h2>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <Input
                                label="Nama Lengkap"
                                placeholder="e.g. John Doe"
                                value={inputValue}
                                onChange={(e) => {
                                    setInputValue(e.target.value);
                                    if (e.target.value.length < 3) {
                                        setInputError('Minimal 3 karakter.');
                                    } else {
                                        setInputError('');
                                    }
                                }}
                                error={inputError}
                                helperText={
                                    !inputError
                                        ? 'Gunakan nama resmi.'
                                        : undefined
                                }
                                leftIcon={<User className="h-3.5 w-3.5" />}
                                required
                            />

                            <Select
                                label="Paket Langganan"
                                value={selectedOption}
                                onChange={(e) =>
                                    setSelectedOption(e.target.value)
                                }
                                options={[
                                    {
                                        value: 'basic',
                                        label: 'Paket Basic (Rp 49.000)',
                                    },
                                    {
                                        value: 'pro',
                                        label: 'Paket Pro (Rp 149.000)',
                                    },
                                    {
                                        value: 'business',
                                        label: 'Paket Business (Rp 499.000)',
                                    },
                                ]}
                                helperText="Tingkatkan kuota booking kapan saja."
                                required
                            />

                            <Input
                                label="Nomor WhatsApp"
                                placeholder="08123456789"
                                leftIcon={<Phone className="h-3.5 w-3.5" />}
                                helperText="Format: 08xx atau 628xx"
                            />
                        </div>

                        <div className="pt-2">
                            <Textarea
                                label="Catatan Tambahan"
                                placeholder="Tulis instruksi khusus untuk staf atau sopir..."
                                value={textareaValue}
                                onChange={(e) =>
                                    setTextareaValue(e.target.value)
                                }
                                maxLength={200}
                                showCount
                                rows={3}
                                helperText="Maksimal 200 karakter."
                            />
                        </div>
                    </div>

                    {/* SECTION 4: Feedback & Interactive Overlays (Modal, Drawer, Toast, Tooltip) */}
                    <div className="space-y-4 rounded-[14px] border border-slate-200 bg-white p-5">
                        <div className="border-b border-slate-100 pb-3">
                            <h2 className="text-xs font-bold tracking-wider text-slate-900 uppercase">
                                4. Feedback, Dialog & Overlay Components
                            </h2>
                        </div>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-4">
                            {/* Modal Trigger */}
                            <div className="flex flex-col justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <div>
                                    <h3 className="text-xs font-semibold text-slate-900">
                                        Modal Dialog
                                    </h3>
                                    <p className="mt-0.5 text-[11px] text-slate-500">
                                        Accessible dialog dengan backdrop & key
                                        trapping.
                                    </p>
                                </div>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    onClick={() => setIsModalOpen(true)}
                                >
                                    Buka Modal
                                </Button>
                            </div>

                            {/* Drawer Trigger */}
                            <div className="flex flex-col justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <div>
                                    <h3 className="text-xs font-semibold text-slate-900">
                                        Drawer Panel
                                    </h3>
                                    <p className="mt-0.5 text-[11px] text-slate-500">
                                        Slide-over panel dari samping atau
                                        bawah.
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        className="flex-1"
                                        onClick={() =>
                                            setIsDrawerRightOpen(true)
                                        }
                                    >
                                        Right
                                    </Button>
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        className="flex-1"
                                        onClick={() =>
                                            setIsDrawerBottomOpen(true)
                                        }
                                    >
                                        Bottom
                                    </Button>
                                </div>
                            </div>

                            {/* Toast Triggers */}
                            <div className="flex flex-col justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <div>
                                    <h3 className="text-xs font-semibold text-slate-900">
                                        Toast Alerts
                                    </h3>
                                    <p className="mt-0.5 text-[11px] text-slate-500">
                                        Notifikasi auto-dismissing dengan stroke
                                        icon.
                                    </p>
                                </div>
                                <div className="grid grid-cols-2 gap-1.5">
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        onClick={() =>
                                            toast.success(
                                                'Operasi berhasil diselesaikan.'
                                            )
                                        }
                                    >
                                        Success
                                    </Button>
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        onClick={() =>
                                            toast.error(
                                                'Terjadi kegagalan jaringan.'
                                            )
                                        }
                                    >
                                        Error
                                    </Button>
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        onClick={() =>
                                            toast.warning(
                                                'Kuota plan hampir habis.'
                                            )
                                        }
                                    >
                                        Warning
                                    </Button>
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        onClick={() =>
                                            toast.info(
                                                'Sinkronisasi data aktif.'
                                            )
                                        }
                                    >
                                        Info
                                    </Button>
                                </div>
                            </div>

                            {/* Confirmation Dialog Trigger */}
                            <div className="flex flex-col justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <div>
                                    <h3 className="text-xs font-semibold text-slate-900">
                                        Confirm Dialog
                                    </h3>
                                    <p className="mt-0.5 text-[11px] text-slate-500">
                                        Konfirmasi tindakan berisiko/destruktif.
                                    </p>
                                </div>
                                <Button
                                    variant="danger"
                                    size="sm"
                                    leftIcon={<Trash2 className="h-3 w-3" />}
                                    onClick={() => setIsConfirmOpen(true)}
                                >
                                    Hapus Entitas
                                </Button>
                            </div>
                        </div>

                        {/* Tooltip Demonstration */}
                        <div className="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3">
                            <span className="text-xs font-semibold text-slate-700">
                                Tooltip:
                            </span>
                            <Tooltip
                                content="Tooltip atas aktif"
                                position="top"
                            >
                                <Button variant="secondary" size="sm">
                                    Top
                                </Button>
                            </Tooltip>
                            <Tooltip
                                content="Tooltip bawah aktif"
                                position="bottom"
                            >
                                <Button variant="secondary" size="sm">
                                    Bottom
                                </Button>
                            </Tooltip>
                            <Tooltip
                                content="Tooltip kiri aktif"
                                position="left"
                            >
                                <Button variant="secondary" size="sm">
                                    Left
                                </Button>
                            </Tooltip>
                            <Tooltip
                                content="Tooltip kanan aktif"
                                position="right"
                            >
                                <Button variant="secondary" size="sm">
                                    Right
                                </Button>
                            </Tooltip>
                        </div>
                    </div>

                    {/* SECTION 5: Tabs Component */}
                    <div className="space-y-4 rounded-[14px] border border-slate-200 bg-white p-5">
                        <div className="border-b border-slate-100 pb-3">
                            <h2 className="text-xs font-bold tracking-wider text-slate-900 uppercase">
                                5. Accessible Tabs Navigation (Keyboard Arrow
                                Supported)
                            </h2>
                        </div>

                        <div className="space-y-4">
                            <div>
                                <p className="mb-2 text-[11px] text-slate-500">
                                    Variant: Line Tabs
                                </p>
                                <Tabs
                                    tabs={[
                                        {
                                            id: 'tab-1',
                                            label: 'Semua Booking',
                                            badge: 24,
                                        },
                                        {
                                            id: 'tab-2',
                                            label: 'Menunggu Konfirmasi',
                                            badge: 3,
                                        },
                                        {
                                            id: 'tab-3',
                                            label: 'Selesai',
                                            badge: 120,
                                        },
                                        {
                                            id: 'tab-4',
                                            label: 'Dibatalkan',
                                            disabled: true,
                                        },
                                    ]}
                                    activeTab={activeTabLine}
                                    onChange={setActiveTabLine}
                                    variant="line"
                                />
                            </div>

                            <div>
                                <p className="mb-2 text-[11px] text-slate-500">
                                    Variant: Pills Tabs
                                </p>
                                <Tabs
                                    tabs={[
                                        { id: 'pill-1', label: 'Harian' },
                                        { id: 'pill-2', label: 'Mingguan' },
                                        { id: 'pill-3', label: 'Bulanan' },
                                    ]}
                                    activeTab={activeTabPills}
                                    onChange={setActiveTabPills}
                                    variant="pills"
                                />
                            </div>
                        </div>
                    </div>

                    {/* SECTION 6: Data-Dense DataTable */}
                    <div className="space-y-4 rounded-[14px] border border-slate-200 bg-white p-5">
                        <div className="flex flex-col gap-3 border-b border-slate-100 pb-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 className="text-xs font-bold tracking-wider text-slate-900 uppercase">
                                    6. Data-Dense DataTable
                                </h2>
                                <p className="text-[11px] text-slate-500">
                                    Tabel data padat, sorting kolom, format mata
                                    uang, status badge semantik.
                                </p>
                            </div>

                            <div className="flex items-center gap-2">
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    onClick={() =>
                                        setIsTableLoading(!isTableLoading)
                                    }
                                >
                                    {isTableLoading
                                        ? 'Stop Loading'
                                        : 'Set Loading'}
                                </Button>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    onClick={() => {
                                        setIsTableEmpty(!isTableEmpty);
                                        setTableData(
                                            isTableEmpty ? sampleData : []
                                        );
                                    }}
                                >
                                    {isTableEmpty
                                        ? 'Isi Data'
                                        : 'Kosongkan Data'}
                                </Button>
                            </div>
                        </div>

                        <DataTable
                            columns={columns}
                            data={tableData}
                            keyExtractor={(item) => item.id}
                            sortKey={tableSortKey}
                            sortDirection={tableSortDirection}
                            onSort={handleSort}
                            isLoading={isTableLoading}
                            emptyText="Belum ada transaksi booking tercatat."
                            emptyAction={
                                <Button
                                    variant="primary"
                                    size="sm"
                                    onClick={() => {
                                        setTableData(sampleData);
                                        setIsTableEmpty(false);
                                    }}
                                >
                                    Muat Data Contoh
                                </Button>
                            }
                        />
                    </div>

                    {/* SECTION 7: EmptyState, ErrorState, & Skeletons */}
                    <div className="space-y-4 rounded-[14px] border border-slate-200 bg-white p-5">
                        <div className="border-b border-slate-100 pb-3">
                            <h2 className="text-xs font-bold tracking-wider text-slate-900 uppercase">
                                7. States & Loaders (EmptyState, ErrorState,
                                Skeleton)
                            </h2>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <EmptyState
                                title="Tidak Ada Jadwal Booking Aktif"
                                description="Pelanggan Anda belum membuat reservasi baru hari ini. Bagikan tautan booking publik ke pelanggan Anda."
                                action={
                                    <Button variant="primary" size="sm">
                                        Salin Link Booking
                                    </Button>
                                }
                            />

                            <ErrorState
                                title="Gagal Menyinkronkan Armada"
                                message="Koneksi telematika server IoT terputus sementara. Periksa koneksi gateway atau coba lagi."
                                code="ERR_FLEET_IOT_TIMEOUT"
                                onRetry={() =>
                                    toast.info('Mencoba menyambung kembali...')
                                }
                            />
                        </div>

                        <div className="space-y-2 border-t border-slate-100 pt-3">
                            <p className="text-xs font-semibold text-slate-700">
                                Skeleton Loaders:
                            </p>
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                                <div className="rounded-lg border border-slate-200 p-3">
                                    <Skeleton variant="text" lines={3} />
                                </div>
                                <div className="flex items-center gap-3 rounded-lg border border-slate-200 p-3">
                                    <Skeleton
                                        variant="circle"
                                        width={40}
                                        height={40}
                                    />
                                    <div className="flex-1">
                                        <Skeleton variant="text" lines={2} />
                                    </div>
                                </div>
                                <div className="rounded-lg border border-slate-200 p-3">
                                    <Skeleton variant="rect" height={60} />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Interactive Modal Instance */}
            <Modal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                title="Informasi Detail Armada"
                description="ID Entitas: FLT-8829-JKT"
                footer={
                    <>
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={() => setIsModalOpen(false)}
                        >
                            Tutup
                        </Button>
                        <Button
                            variant="primary"
                            size="sm"
                            onClick={() => {
                                setIsModalOpen(false);
                                toast.success('Perubahan armada disimpan.');
                            }}
                        >
                            Simpan Perubahan
                        </Button>
                    </>
                }
            >
                <div className="space-y-3">
                    <p className="leading-relaxed">
                        Ini adalah modal dialog yang sepenuhnya terakses dengan
                        WAI-ARIA, dapat ditutup menggunakan tombol Escape,
                        mengunci gulir halaman (body scroll-lock), dan memiliki
                        focus boundary yang aman.
                    </p>
                    <Input label="Nama Unit / Plat" defaultValue="B 9281 UZA" />
                </div>
            </Modal>

            {/* Interactive Right Drawer Instance */}
            <Drawer
                isOpen={isDrawerRightOpen}
                onClose={() => setIsDrawerRightOpen(false)}
                title="Panel Pengaturan Cepat"
                position="right"
                footer={
                    <Button
                        variant="primary"
                        size="sm"
                        onClick={() => setIsDrawerRightOpen(false)}
                    >
                        Terapkan
                    </Button>
                }
            >
                <div className="space-y-4">
                    <p className="text-xs text-slate-600">
                        Drawer sisi kanan memudahkan navigasi inspeksi data
                        tanpa meninggalkan halaman saat ini.
                    </p>
                    <Select
                        label="Status Operasional"
                        defaultValue="loading"
                        options={[
                            { value: 'active', label: 'LOADING / ACTIVE' },
                            { value: 'queuing', label: 'DUMPING / QUEUING' },
                            { value: 'hauling', label: 'HAULING' },
                        ]}
                    />
                    <Textarea
                        label="Catatan Petugas"
                        placeholder="Tulis instruksi..."
                        rows={4}
                    />
                </div>
            </Drawer>

            {/* Interactive Bottom Drawer Instance */}
            <Drawer
                isOpen={isDrawerBottomOpen}
                onClose={() => setIsDrawerBottomOpen(false)}
                title="Opsi Filter Cepat (Bottom Sheet)"
                position="bottom"
                footer={
                    <Button
                        variant="primary"
                        size="sm"
                        onClick={() => setIsDrawerBottomOpen(false)}
                    >
                        Selesai
                    </Button>
                }
            >
                <div className="space-y-2">
                    <p className="text-xs text-slate-600">
                        Bottom sheet ideal untuk perangkat mobile dengan
                        jangkauan jempol (one-hand thumb zone).
                    </p>
                    <div className="flex gap-2">
                        <Badge variant="active">Semua Status</Badge>
                        <Badge variant="queuing">Hanya Queuing</Badge>
                        <Badge variant="breakdown">Hanya Breakdown</Badge>
                    </div>
                </div>
            </Drawer>

            {/* Interactive Confirmation Dialog Instance */}
            <ConfirmationDialog
                isOpen={isConfirmOpen}
                onClose={() => setIsConfirmOpen(false)}
                onConfirm={handleConfirmDelete}
                title="Hapus Booking Ini?"
                message="Tindakan ini tidak dapat dibatalkan. Riwayat audit log akan tetap merekam penghapusan ini untuk kepatuhan tata kelola."
                confirmLabel="Hapus Permanen"
                variant="danger"
                isLoading={isConfirmLoading}
            />
        </OwnerLayout>
    );
}
