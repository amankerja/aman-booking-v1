import { router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Check,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    ExternalLink,
    Eye,
    EyeOff,
    Globe,
    HelpCircle,
    Info,
    Layers,
    Layout,
    Monitor,
    Palette,
    Phone,
    Plus,
    RefreshCw,
    RotateCcw,
    Save,
    Smartphone,
    Tablet,
    Trash2,
} from 'lucide-react';
import React, { useRef, useState } from 'react';
import {
    Badge,
    Button,
    ConfirmationDialog,
    Input,
    Textarea,
    useToast,
} from '../../../Components/ui';
import { OwnerLayout } from '../../../Layouts/OwnerLayout';

interface SectionData {
    id: string;
    type: string;
    enabled: boolean;
    title: string;
    data: Record<string, unknown>;
}

interface ThemeConfig {
    primary_color: string;
    font_preset: string;
    banner_image_url: string | null;
}

interface LandingConfig {
    theme: ThemeConfig;
    sections: SectionData[];
}

interface BusinessInfo {
    id: number;
    name: string;
    slug: string;
    logo_url: string | null;
    whatsapp: string | null;
    phone: string | null;
    address: string | null;
    city: string | null;
    timezone: string;
    published_at: string | null;
    is_published: boolean;
}

interface LandingBuilderProps {
    business: BusinessInfo;
    config: LandingConfig;
    servicesCount: number;
    publicUrl: string;
}

type DeviceMode = 'desktop' | 'tablet' | 'mobile';

const PRESET_COLORS = [
    { label: 'Royal Blue', hex: '#2563eb' },
    { label: 'Emerald Green', hex: '#059669' },
    { label: 'Indigo Modern', hex: '#4f46e5' },
    { label: 'Deep Violet', hex: '#7c3aed' },
    { label: 'Vibrant Rose', hex: '#e11d48' },
    { label: 'Warm Amber', hex: '#d97706' },
    { label: 'Slate Charcoal', hex: '#334155' },
    { label: 'Minimal Black', hex: '#0f172a' },
];

export default function LandingBuilderIndex({
    business,
    config,
    servicesCount,
    publicUrl,
}: LandingBuilderProps) {
    const toast = useToast();
    const iframeRef = useRef<HTMLIFrameElement>(null);

    // State for local editing
    const [sections, setSections] = useState<SectionData[]>(config.sections || []);
    const [theme, setTheme] = useState<ThemeConfig>(
        config.theme || {
            primary_color: '#2563eb',
            font_preset: 'inter',
            banner_image_url: null,
        }
    );

    const [isPublished, setIsPublished] = useState(business.is_published);
    const [isSaving, setIsSaving] = useState(false);
    const [isPublishing, setIsPublishing] = useState(false);
    const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
    const [expandedSection, setExpandedSection] = useState<string | null>('hero');
    const [activeTab, setActiveTab] = useState<'sections' | 'theme'>('sections');
    const [deviceMode, setDeviceMode] = useState<DeviceMode>('desktop');
    const [isResetDialogOpen, setIsResetDialogOpen] = useState(false);
    const [isResetting, setIsResetting] = useState(false);
    const [iframeKey, setIframeKey] = useState(1);

    // Section moving (Reorder)
    const moveSection = (index: number, direction: 'up' | 'down') => {
        const targetIndex = direction === 'up' ? index - 1 : index + 1;
        if (targetIndex < 0 || targetIndex >= sections.length) return;

        const newSections = [...sections];
        const temp = newSections[index];
        newSections[index] = newSections[targetIndex];
        newSections[targetIndex] = temp;

        setSections(newSections);
        setHasUnsavedChanges(true);
    };

    // Toggle Section visibility
    const toggleSectionEnabled = (id: string) => {
        setSections((prev) =>
            prev.map((sec) =>
                sec.id === id ? { ...sec, enabled: !sec.enabled } : sec
            )
        );
        setHasUnsavedChanges(true);
    };

    // Update section data field
    const updateSectionData = (
        sectionId: string,
        key: string,
        value: unknown
    ) => {
        setSections((prev) =>
            prev.map((sec) => {
                if (sec.id !== sectionId) return sec;
                return {
                    ...sec,
                    data: {
                        ...sec.data,
                        [key]: value,
                    },
                };
            })
        );
        setHasUnsavedChanges(true);
    };

    // Trust Points handlers (Hero)
    const handleAddTrustPoint = () => {
        const hero = sections.find((s) => s.id === 'hero');
        const currentPoints: string[] = (hero?.data?.trust_points as string[]) || [];
        updateSectionData('hero', 'trust_points', [...currentPoints, 'Keunggulan Baru']);
    };

    const handleUpdateTrustPoint = (index: number, val: string) => {
        const hero = sections.find((s) => s.id === 'hero');
        const currentPoints: string[] = [...((hero?.data?.trust_points as string[]) || [])];
        currentPoints[index] = val;
        updateSectionData('hero', 'trust_points', currentPoints);
    };

    const handleRemoveTrustPoint = (index: number) => {
        const hero = sections.find((s) => s.id === 'hero');
        const currentPoints: string[] = ((hero?.data?.trust_points as string[]) || []).filter(
            (_pt: string, i: number) => i !== index
        );
        updateSectionData('hero', 'trust_points', currentPoints);
    };

    // Feature Items handlers
    const handleAddFeatureItem = () => {
        const feat = sections.find((s) => s.id === 'features');
        const currentItems: Array<{ title: string; desc: string }> =
            feat?.data?.items || [];
        updateSectionData('features', 'items', [
            ...currentItems,
            { title: 'Poin Keunggulan Baru', desc: 'Deskripsi singkat mengenai layanan atau kelebihan kami.' },
        ]);
    };

    const handleUpdateFeatureItem = (
        index: number,
        field: 'title' | 'desc',
        val: string
    ) => {
        const feat = sections.find((s) => s.id === 'features');
        const currentItems = [...(feat?.data?.items || [])];
        if (!currentItems[index]) return;
        currentItems[index] = {
            ...currentItems[index],
            [field]: val,
        };
        updateSectionData('features', 'items', currentItems);
    };

    const handleRemoveFeatureItem = (index: number) => {
        const feat = sections.find((s) => s.id === 'features');
        const currentItems = (
            (feat?.data?.items as Array<{ title: string; desc: string }>) || []
        ).filter((_, i: number) => i !== index);
        updateSectionData('features', 'items', currentItems);
    };

    // FAQ Items handlers
    const handleAddFaqItem = () => {
        const faq = sections.find((s) => s.id === 'faq');
        const currentItems: Array<{ q: string; a: string }> = faq?.data?.items || [];
        updateSectionData('faq', 'items', [
            ...currentItems,
            { q: 'Pertanyaan baru?', a: 'Tuliskan jawaban yang jelas dan membantu pelanggan Anda di sini.' },
        ]);
    };

    const handleUpdateFaqItem = (
        index: number,
        field: 'q' | 'a',
        val: string
    ) => {
        const faq = sections.find((s) => s.id === 'faq');
        const currentItems = [...(faq?.data?.items || [])];
        if (!currentItems[index]) return;
        currentItems[index] = {
            ...currentItems[index],
            [field]: val,
        };
        updateSectionData('faq', 'items', currentItems);
    };

    const handleRemoveFaqItem = (index: number) => {
        const faq = sections.find((s) => s.id === 'faq');
        const currentItems = (
            (faq?.data?.items as Array<{ q: string; a: string }>) || []
        ).filter((_, i: number) => i !== index);
        updateSectionData('faq', 'items', currentItems);
    };

    // Save changes
    const handleSave = () => {
        setIsSaving(true);
        router.put(
            '/app/landing-builder',
            {
                theme,
                sections,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Pengaturan landing page berhasil disimpan.');
                    setHasUnsavedChanges(false);
                    setIsSaving(false);
                    // Refresh iframe preview to show latest saved data
                    setIframeKey((prev) => prev + 1);
                },
                onError: (errors) => {
                    const firstMsg = Object.values(errors)[0] as string;
                    toast.error(firstMsg || 'Gagal menyimpan konfigurasi landing page.');
                    setIsSaving(false);
                },
            }
        );
    };

    // Toggle Publish Status
    const handleTogglePublish = () => {
        setIsPublishing(true);
        const nextState = !isPublished;
        router.post(
            '/app/landing-builder/publish',
            { publish: nextState },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsPublished(nextState);
                    setIsPublishing(false);
                    toast.success(
                        nextState
                            ? 'Landing page berhasil dipublikasikan dan live!'
                            : 'Landing page dinonaktifkan (akses publik ditutup).'
                    );
                },
                onError: () => {
                    setIsPublishing(false);
                    toast.error('Gagal memperbarui status publikasi.');
                },
            }
        );
    };

    // Reset Defaults
    const handleResetDefaults = () => {
        setIsResetting(true);
        router.post(
            '/app/landing-builder/reset-defaults',
            {},
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const pageProps = page.props as unknown as { config?: LandingConfig };
                    const newConfig = pageProps.config;
                    if (newConfig) {
                        setSections(newConfig.sections);
                        setTheme(newConfig.theme);
                    }
                    setIsResetting(false);
                    setIsResetDialogOpen(false);
                    setHasUnsavedChanges(false);
                    toast.success('Konfigurasi berhasil direset ke standar bawaan.');
                    setIframeKey((prev) => prev + 1);
                },
                onError: () => {
                    setIsResetting(false);
                    setIsResetDialogOpen(false);
                    toast.error('Gagal mereset konfigurasi.');
                },
            }
        );
    };

    // Section meta helpers
    const getSectionIcon = (id: string) => {
        switch (id) {
            case 'hero':
                return <Layout className="h-4 w-4" />;
            case 'services':
                return <Layers className="h-4 w-4" />;
            case 'features':
                return <CheckCircle2 className="h-4 w-4" />;
            case 'about':
                return <Info className="h-4 w-4" />;
            case 'faq':
                return <HelpCircle className="h-4 w-4" />;
            case 'contact':
                return <Phone className="h-4 w-4" />;
            default:
                return <Globe className="h-4 w-4" />;
        }
    };

    const getSectionDefaultName = (id: string) => {
        switch (id) {
            case 'hero':
                return 'Hero Banner';
            case 'services':
                return 'Katalog Layanan';
            case 'features':
                return 'Mengapa Memilih Kami (Keunggulan)';
            case 'about':
                return 'Tentang & Jam Operasional';
            case 'faq':
                return 'Pertanyaan Umum (FAQ)';
            case 'contact':
                return 'Kontak & Lokasi';
            default:
                return id;
        }
    };

    // Device preview width mapping
    const getPreviewContainerWidth = () => {
        switch (deviceMode) {
            case 'mobile':
                return 'max-w-[375px]';
            case 'tablet':
                return 'max-w-[768px]';
            default:
                return 'w-full';
        }
    };

    return (
        <OwnerLayout
            title="Landing Page Builder"
            breadcrumbs={[
                { label: 'Dashboard', href: '/app/dashboard' },
                { label: 'Landing Page Builder' },
            ]}
            actions={
                <div className="flex flex-wrap items-center gap-2">
                    {/* Status Pill */}
                    <Badge
                        variant={isPublished ? 'active' : 'queuing'}
                        size="md"
                    >
                        {isPublished ? '● Publik (Online)' : '○ Draf (Privat)'}
                    </Badge>

                    {/* Publish/Unpublish Toggle */}
                    <Button
                        variant={isPublished ? 'secondary' : 'primary'}
                        size="sm"
                        onClick={handleTogglePublish}
                        isLoading={isPublishing}
                    >
                        {isPublished ? (
                            <>
                                <EyeOff className="mr-1.5 h-3.5 w-3.5" />
                                Unpublish
                            </>
                        ) : (
                            <>
                                <Eye className="mr-1.5 h-3.5 w-3.5" />
                                Publikasikan
                            </>
                        )}
                    </Button>

                    {/* Save Button */}
                    <Button
                        variant="primary"
                        size="sm"
                        onClick={handleSave}
                        isLoading={isSaving}
                        className={hasUnsavedChanges ? 'ring-2 ring-blue-400' : ''}
                    >
                        <Save className="mr-1.5 h-3.5 w-3.5" />
                        Simpan Perubahan
                        {hasUnsavedChanges && ' *'}
                    </Button>
                </div>
            }
        >
            <div className="space-y-6">
                {/* Header Information Banner */}
                <div className="flex flex-col justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <span className="font-semibold text-slate-900 text-sm">
                                URL Publik Halaman Reservasi:
                            </span>
                            <a
                                href={publicUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1 font-mono text-xs font-medium text-blue-600 hover:text-blue-800 hover:underline"
                            >
                                {publicUrl}
                                <ExternalLink className="h-3.5 w-3.5" />
                            </a>
                        </div>
                        <p className="text-xs text-slate-500">
                            {isPublished
                                ? 'Halaman ini dapat diakses bebas oleh pelanggan secara publik.'
                                : 'Halaman dalam status draf (404 publik). Anda tetap dapat melihat preview di bawah.'}
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setIsResetDialogOpen(true)}
                            className="text-xs text-slate-500 hover:text-rose-600"
                        >
                            <RotateCcw className="mr-1.5 h-3.5 w-3.5" />
                            Reset ke Standar
                        </Button>
                    </div>
                </div>

                {/* Main 2-Column Builder Layout */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-12 items-start">
                    {/* LEFT COLUMN: Controls & Editor (5 of 12 cols on desktop) */}
                    <div className="space-y-4 lg:col-span-5">
                        {/* Tab Switcher: Sections vs Theme */}
                        <div className="flex rounded-lg border border-slate-200 bg-white p-1">
                            <button
                                type="button"
                                onClick={() => setActiveTab('sections')}
                                className={`flex flex-1 items-center justify-center gap-2 rounded-md py-2 text-xs font-semibold transition-colors ${
                                    activeTab === 'sections'
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-slate-600 hover:text-slate-900'
                                }`}
                            >
                                <Layers className="h-4 w-4" />
                                Tata Letak & Bagian ({sections.length})
                            </button>
                            <button
                                type="button"
                                onClick={() => setActiveTab('theme')}
                                className={`flex flex-1 items-center justify-center gap-2 rounded-md py-2 text-xs font-semibold transition-colors ${
                                    activeTab === 'theme'
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-slate-600 hover:text-slate-900'
                                }`}
                            >
                                <Palette className="h-4 w-4" />
                                Tema & Warna
                            </button>
                        </div>

                        {/* TAB 1: SECTIONS MANAGER */}
                        {activeTab === 'sections' && (
                            <div className="space-y-3">
                                <div className="rounded-lg border border-slate-200 bg-white p-3">
                                    <p className="text-xs font-semibold text-slate-900">
                                        Urutan & Visibilitas Bagian
                                    </p>
                                    <p className="text-[11px] text-slate-500">
                                        Gunakan tombol panah untuk menyusun tata letak. Klik pada judul bagian untuk mengubah teks dan pengaturannya.
                                    </p>
                                </div>

                                {/* List of Ordered Sections */}
                                {sections.map((section, index) => {
                                    const isExpanded = expandedSection === section.id;
                                    return (
                                        <div
                                            key={section.id}
                                            className={`rounded-xl border bg-white transition-shadow ${
                                                section.enabled
                                                    ? 'border-slate-200 shadow-xs'
                                                    : 'border-slate-200 bg-slate-50/60 opacity-75'
                                            }`}
                                        >
                                            {/* Section Header Row */}
                                            <div className="flex items-center justify-between p-3.5">
                                                <div className="flex items-center gap-2.5 min-w-0">
                                                    {/* Move Up/Down Controls */}
                                                    <div className="flex flex-col gap-0.5">
                                                        <button
                                                            type="button"
                                                            disabled={index === 0}
                                                            onClick={() => moveSection(index, 'up')}
                                                            className="rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30 disabled:hover:bg-transparent"
                                                            title="Geser ke atas"
                                                            aria-label={`Geser ${section.title} ke atas`}
                                                        >
                                                            <ArrowUp className="h-3.5 w-3.5" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            disabled={index === sections.length - 1}
                                                            onClick={() => moveSection(index, 'down')}
                                                            className="rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30 disabled:hover:bg-transparent"
                                                            title="Geser ke bawah"
                                                            aria-label={`Geser ${section.title} ke bawah`}
                                                        >
                                                            <ArrowDown className="h-3.5 w-3.5" />
                                                        </button>
                                                    </div>

                                                    {/* Icon & Name */}
                                                    <div
                                                        onClick={() =>
                                                            setExpandedSection(
                                                                isExpanded ? null : section.id
                                                            )
                                                        }
                                                        className="flex cursor-pointer items-center gap-2 min-w-0"
                                                    >
                                                        <span
                                                            className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-md ${
                                                                section.enabled
                                                                    ? 'bg-blue-50 text-blue-600'
                                                                    : 'bg-slate-100 text-slate-400'
                                                            }`}
                                                        >
                                                            {getSectionIcon(section.id)}
                                                        </span>
                                                        <div className="min-w-0">
                                                            <p className="truncate text-xs font-semibold text-slate-900">
                                                                {section.title || getSectionDefaultName(section.id)}
                                                            </p>
                                                            <p className="text-[10px] text-slate-400">
                                                                Posisi #{index + 1} • {section.enabled ? 'Aktif' : 'Disembunyikan'}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Toggle & Expand */}
                                                <div className="flex items-center gap-2 shrink-0">
                                                    <label className="relative inline-flex cursor-pointer items-center">
                                                        <input
                                                            type="checkbox"
                                                            checked={section.enabled}
                                                            onChange={() => toggleSectionEnabled(section.id)}
                                                            className="peer sr-only"
                                                            aria-label={`Aktifkan bagian ${section.title}`}
                                                        />
                                                        <div className="peer h-5 w-9 rounded-full bg-slate-200 peer-checked:bg-blue-600 peer-focus:outline-none after:absolute after:top-[2px] after:left-[2px] after:h-4 after:w-4 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white"></div>
                                                    </label>

                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setExpandedSection(
                                                                isExpanded ? null : section.id
                                                            )
                                                        }
                                                        className="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                                        aria-label="Buka editor konten bagian"
                                                    >
                                                        {isExpanded ? (
                                                            <ChevronUp className="h-4 w-4" />
                                                        ) : (
                                                            <ChevronDown className="h-4 w-4" />
                                                        )}
                                                    </button>
                                                </div>
                                            </div>

                                            {/* Expandable Section Content Editor */}
                                            {isExpanded && (
                                                <div className="border-t border-slate-100 bg-slate-50/50 p-4 space-y-4">
                                                    {/* Section Specific Editors */}
                                                    {section.id === 'hero' && (
                                                        <div className="space-y-3">
                                                            <Input
                                                                label="Judul Utama (Headline)"
                                                                value={section.data?.headline || ''}
                                                                onChange={(e) =>
                                                                    updateSectionData('hero', 'headline', e.target.value)
                                                                }
                                                                placeholder="Nama Bisnis atau Slogan Utama"
                                                            />
                                                            <Textarea
                                                                label="Sub-headline / Keterangan Pendukung"
                                                                value={section.data?.subheadline || ''}
                                                                onChange={(e) =>
                                                                    updateSectionData('hero', 'subheadline', e.target.value)
                                                                }
                                                                rows={3}
                                                                placeholder="Deskripsi singkat yang memikat pelanggan..."
                                                            />
                                                            <div className="grid grid-cols-2 gap-3">
                                                                <Input
                                                                    label="Label Tombol Reservasi (CTA)"
                                                                    value={section.data?.cta_text || ''}
                                                                    onChange={(e) =>
                                                                        updateSectionData('hero', 'cta_text', e.target.value)
                                                                    }
                                                                    placeholder="Booking Sekarang"
                                                                />
                                                                <div className="flex flex-col justify-end pb-1.5">
                                                                    <label className="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                                                                        <input
                                                                            type="checkbox"
                                                                            checked={section.data?.show_whatsapp ?? true}
                                                                            onChange={(e) =>
                                                                                updateSectionData('hero', 'show_whatsapp', e.target.checked)
                                                                            }
                                                                            className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                                                        />
                                                                        Tampilkan Tombol WhatsApp
                                                                    </label>
                                                                </div>
                                                            </div>

                                                            {/* Trust Points List */}
                                                            <div className="space-y-2 pt-2 border-t border-slate-200">
                                                                <div className="flex items-center justify-between">
                                                                    <label className="text-xs font-semibold text-slate-800">
                                                                        Poin Kepercayaan (Trust Badges)
                                                                    </label>
                                                                    <button
                                                                        type="button"
                                                                        onClick={handleAddTrustPoint}
                                                                        className="inline-flex items-center gap-1 text-[11px] font-semibold text-blue-600 hover:text-blue-800"
                                                                    >
                                                                        <Plus className="h-3 w-3" />
                                                                        Tambah Poin
                                                                    </button>
                                                                </div>
                                                                {(section.data?.trust_points || []).map(
                                                                    (pt: string, pIndex: number) => (
                                                                        <div
                                                                            key={pIndex}
                                                                            className="flex items-center gap-2"
                                                                        >
                                                                            <Input
                                                                                value={pt}
                                                                                onChange={(e) =>
                                                                                    handleUpdateTrustPoint(pIndex, e.target.value)
                                                                                }
                                                                                className="text-xs"
                                                                                placeholder="Contoh: Konfirmasi Instan"
                                                                            />
                                                                            <button
                                                                                type="button"
                                                                                onClick={() => handleRemoveTrustPoint(pIndex)}
                                                                                className="rounded p-1 text-slate-400 hover:bg-slate-200 hover:text-rose-600"
                                                                                title="Hapus poin"
                                                                            >
                                                                                <Trash2 className="h-3.5 w-3.5" />
                                                                            </button>
                                                                        </div>
                                                                    )
                                                                )}
                                                            </div>
                                                        </div>
                                                    )}

                                                    {section.id === 'services' && (
                                                        <div className="space-y-3">
                                                            <Input
                                                                label="Judul Bagian Layanan"
                                                                value={section.data?.title || ''}
                                                                onChange={(e) =>
                                                                    updateSectionData('services', 'title', e.target.value)
                                                                }
                                                                placeholder="Katalog Layanan"
                                                            />
                                                            <Textarea
                                                                label="Keterangan Subjudul"
                                                                value={section.data?.subtitle || ''}
                                                                onChange={(e) =>
                                                                    updateSectionData('services', 'subtitle', e.target.value)
                                                                }
                                                                rows={2}
                                                                placeholder="Pilih layanan yang Anda inginkan..."
                                                            />
                                                            <div className="rounded-lg border border-blue-100 bg-blue-50/50 p-2.5 text-xs text-blue-800">
                                                                <p className="font-semibold">Info Katalog Otomatis:</p>
                                                                <p className="text-[11px] text-blue-700">
                                                                    Daftar kartu layanan diambil langsung dari database ({servicesCount} layanan aktif). Kelola harga, foto, dan durasi di menu Layanan & Paket.
                                                                </p>
                                                            </div>
                                                        </div>
                                                    )}

                                                    {section.id === 'features' && (
                                                        <div className="space-y-3">
                                                            <Input
                                                                label="Judul Bagian Keunggulan"
                                                                value={section.data?.title || ''}
                                                                onChange={(e) =>
                                                                    updateSectionData('features', 'title', e.target.value)
                                                                }
                                                                placeholder="Mengapa Memilih Kami?"
                                                            />
                                                            <Textarea
                                                                label="Keterangan Subjudul"
                                                                value={section.data?.subtitle || ''}
                                                                onChange={(e) =>
                                                                    updateSectionData('features', 'subtitle', e.target.value)
                                                                }
                                                                rows={2}
                                                            />

                                                            {/* Features List */}
                                                            <div className="space-y-2 pt-2 border-t border-slate-200">
                                                                <div className="flex items-center justify-between">
                                                                    <label className="text-xs font-semibold text-slate-800">
                                                                        Daftar Poin Nilai Tambah
                                                                    </label>
                                                                    <button
                                                                        type="button"
                                                                        onClick={handleAddFeatureItem}
                                                                        className="inline-flex items-center gap-1 text-[11px] font-semibold text-blue-600 hover:text-blue-800"
                                                                    >
                                                                        <Plus className="h-3 w-3" />
                                                                        Tambah Nilai
                                                                    </button>
                                                                </div>

                                                                {(section.data?.items || []).map(
                                                                    (
                                                                        item: { title: string; desc: string },
                                                                        fIndex: number
                                                                    ) => (
                                                                        <div
                                                                            key={fIndex}
                                                                            className="rounded-lg border border-slate-200 bg-white p-3 space-y-2"
                                                                        >
                                                                            <div className="flex items-center justify-between">
                                                                                <span className="text-[10px] font-bold text-slate-400">
                                                                                    Keunggulan #{fIndex + 1}
                                                                                </span>
                                                                                <button
                                                                                    type="button"
                                                                                    onClick={() =>
                                                                                        handleRemoveFeatureItem(fIndex)
                                                                                    }
                                                                                    className="rounded p-0.5 text-slate-400 hover:text-rose-600"
                                                                                    title="Hapus"
                                                                                >
                                                                                    <Trash2 className="h-3.5 w-3.5" />
                                                                                </button>
                                                                            </div>
                                                                            <Input
                                                                                label="Judul"
                                                                                value={item.title}
                                                                                onChange={(e) =>
                                                                                    handleUpdateFeatureItem(
                                                                                        fIndex,
                                                                                        'title',
                                                                                        e.target.value
                                                                                    )
                                                                                }
                                                                            />
                                                                            <Textarea
                                                                                label="Keterangan"
                                                                                value={item.desc}
                                                                                onChange={(e) =>
                                                                                    handleUpdateFeatureItem(
                                                                                        fIndex,
                                                                                        'desc',
                                                                                        e.target.value
                                                                                    )
                                                                                }
                                                                                rows={2}
                                                                            />
                                                                        </div>
                                                                    )
                                                                )}
                                                            </div>
                                                        </div>
                                                    )}

                                                    {section.id === 'about' && (
                                                        <div className="space-y-3">
                                                            <Input
                                                                label="Judul Bagian Tentang"
                                                                value={section.data?.title || ''}
                                                                onChange={(e) =>
                                                                    updateSectionData('about', 'title', e.target.value)
                                                                }
                                                                placeholder="Tentang Bisnis"
                                                            />
                                                            <Textarea
                                                                label="Deskripsi Profil Bisnis"
                                                                value={section.data?.content || ''}
                                                                onChange={(e) =>
                                                                    updateSectionData('about', 'content', e.target.value)
                                                                }
                                                                rows={4}
                                                                placeholder="Tuliskan dedikasi, keunggulan staf, dan standar pelayanan..."
                                                            />
                                                            <div className="flex flex-col gap-2 pt-2 border-t border-slate-200">
                                                                <label className="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={section.data?.show_hours ?? true}
                                                                        onChange={(e) =>
                                                                            updateSectionData('about', 'show_hours', e.target.checked)
                                                                        }
                                                                        className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                                                    />
                                                                    Tampilkan Tabel Jadwal Jam Operasional Mingguan
                                                                </label>
                                                                <label className="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={section.data?.show_address ?? true}
                                                                        onChange={(e) =>
                                                                            updateSectionData('about', 'show_address', e.target.checked)
                                                                        }
                                                                        className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                                                    />
                                                                    Tampilkan Alamat & Kota Lokasi
                                                                </label>
                                                            </div>
                                                        </div>
                                                    )}

                                                    {section.id === 'faq' && (
                                                        <div className="space-y-3">
                                                            <Input
                                                                label="Judul Bagian FAQ"
                                                                value={section.data?.title || ''}
                                                                onChange={(e) =>
                                                                    updateSectionData('faq', 'title', e.target.value)
                                                                }
                                                                placeholder="Pertanyaan yang Sering Diajukan"
                                                            />
                                                            <Textarea
                                                                label="Keterangan Subjudul"
                                                                value={section.data?.subtitle || ''}
                                                                onChange={(e) =>
                                                                    updateSectionData('faq', 'subtitle', e.target.value)
                                                                }
                                                                rows={2}
                                                            />

                                                            {/* FAQ Items */}
                                                            <div className="space-y-2 pt-2 border-t border-slate-200">
                                                                <div className="flex items-center justify-between">
                                                                    <label className="text-xs font-semibold text-slate-800">
                                                                        Daftar Tanya Jawab
                                                                    </label>
                                                                    <button
                                                                        type="button"
                                                                        onClick={handleAddFaqItem}
                                                                        className="inline-flex items-center gap-1 text-[11px] font-semibold text-blue-600 hover:text-blue-800"
                                                                    >
                                                                        <Plus className="h-3 w-3" />
                                                                        Tambah Pertanyaan
                                                                    </button>
                                                                </div>

                                                                {(section.data?.items || []).map(
                                                                    (
                                                                        item: { q: string; a: string },
                                                                        qIndex: number
                                                                    ) => (
                                                                        <div
                                                                            key={qIndex}
                                                                            className="rounded-lg border border-slate-200 bg-white p-3 space-y-2"
                                                                        >
                                                                            <div className="flex items-center justify-between">
                                                                                <span className="text-[10px] font-bold text-slate-400">
                                                                                    Q&A #{qIndex + 1}
                                                                                </span>
                                                                                <button
                                                                                    type="button"
                                                                                    onClick={() =>
                                                                                        handleRemoveFaqItem(qIndex)
                                                                                    }
                                                                                    className="rounded p-0.5 text-slate-400 hover:text-rose-600"
                                                                                    title="Hapus pertanyaan"
                                                                                >
                                                                                    <Trash2 className="h-3.5 w-3.5" />
                                                                                </button>
                                                                            </div>
                                                                            <Input
                                                                                label="Pertanyaan"
                                                                                value={item.q}
                                                                                onChange={(e) =>
                                                                                    handleUpdateFaqItem(
                                                                                        qIndex,
                                                                                        'q',
                                                                                        e.target.value
                                                                                    )
                                                                                }
                                                                                placeholder="Contoh: Bagaimana cara memesan?"
                                                                            />
                                                                            <Textarea
                                                                                label="Jawaban"
                                                                                value={item.a}
                                                                                onChange={(e) =>
                                                                                    handleUpdateFaqItem(
                                                                                        qIndex,
                                                                                        'a',
                                                                                        e.target.value
                                                                                    )
                                                                                }
                                                                                rows={2}
                                                                                placeholder="Jelaskan secara ringkas dan informatif..."
                                                                            />
                                                                        </div>
                                                                    )
                                                                )}
                                                            </div>
                                                        </div>
                                                    )}

                                                    {section.id === 'contact' && (
                                                        <div className="space-y-3">
                                                            <Input
                                                                label="Judul Bagian Kontak"
                                                                value={section.data?.title || ''}
                                                                onChange={(e) =>
                                                                    updateSectionData('contact', 'title', e.target.value)
                                                                }
                                                                placeholder="Hubungi Kami"
                                                            />
                                                            <Textarea
                                                                label="Keterangan Subjudul"
                                                                value={section.data?.subtitle || ''}
                                                                onChange={(e) =>
                                                                    updateSectionData('contact', 'subtitle', e.target.value)
                                                                }
                                                                rows={2}
                                                            />
                                                            <div className="flex flex-col gap-2 pt-2 border-t border-slate-200">
                                                                <label className="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={section.data?.show_whatsapp ?? true}
                                                                        onChange={(e) =>
                                                                            updateSectionData('contact', 'show_whatsapp', e.target.checked)
                                                                        }
                                                                        className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                                                    />
                                                                    Tampilkan Kartu WhatsApp Resmi
                                                                </label>
                                                                <label className="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={section.data?.show_phone ?? true}
                                                                        onChange={(e) =>
                                                                            updateSectionData('contact', 'show_phone', e.target.checked)
                                                                        }
                                                                        className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                                                    />
                                                                    Tampilkan Nomor Telepon Kantor
                                                                </label>
                                                                <label className="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={section.data?.show_address ?? true}
                                                                        onChange={(e) =>
                                                                            updateSectionData('contact', 'show_address', e.target.checked)
                                                                        }
                                                                        className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                                                    />
                                                                    Tampilkan Alamat Lokasi
                                                                </label>
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        )}

                        {/* TAB 2: THEME & COLOR CUSTOMIZER */}
                        {activeTab === 'theme' && (
                            <div className="space-y-4">
                                {/* Primary Color Swatches & Picker */}
                                <div className="rounded-xl border border-slate-200 bg-white p-4 space-y-3">
                                    <div className="flex items-center justify-between">
                                        <label className="text-xs font-semibold text-slate-900">
                                            Warna Utama Brand (Primary Accent)
                                        </label>
                                        <div className="flex items-center gap-2">
                                            <span
                                                className="h-4 w-4 rounded-full border border-slate-300 shadow-xs"
                                                style={{ backgroundColor: theme.primary_color }}
                                            />
                                            <span className="font-mono text-xs font-semibold text-slate-700">
                                                {theme.primary_color}
                                            </span>
                                        </div>
                                    </div>
                                    <p className="text-[11px] text-slate-500">
                                        Warna aksen utama digunakan pada tombol pemesanan, badge status, dan link interaktif pada halaman publik Anda.
                                    </p>

                                    {/* Preset Swatches */}
                                    <div className="grid grid-cols-4 gap-2 pt-1">
                                        {PRESET_COLORS.map((preset) => {
                                            const isSelected =
                                                theme.primary_color.toLowerCase() ===
                                                preset.hex.toLowerCase();
                                            return (
                                                <button
                                                    key={preset.hex}
                                                    type="button"
                                                    onClick={() => {
                                                        setTheme({
                                                            ...theme,
                                                            primary_color: preset.hex,
                                                        });
                                                        setHasUnsavedChanges(true);
                                                    }}
                                                    className={`flex flex-col items-center gap-1.5 rounded-lg border p-2 text-center transition-all ${
                                                        isSelected
                                                            ? 'border-blue-600 bg-blue-50/50 ring-1 ring-blue-600'
                                                            : 'border-slate-200 hover:border-slate-300'
                                                    }`}
                                                >
                                                    <span
                                                        className="h-6 w-6 rounded-full border border-black/10 shadow-xs"
                                                        style={{ backgroundColor: preset.hex }}
                                                    />
                                                    <span className="text-[10px] font-medium text-slate-700 truncate w-full">
                                                        {preset.label}
                                                    </span>
                                                </button>
                                            );
                                        })}
                                    </div>

                                    {/* Custom Color Input */}
                                    <div className="flex items-center gap-3 pt-2 border-t border-slate-100">
                                        <input
                                            type="color"
                                            value={theme.primary_color}
                                            onChange={(e) => {
                                                setTheme({
                                                    ...theme,
                                                    primary_color: e.target.value,
                                                });
                                                setHasUnsavedChanges(true);
                                            }}
                                            className="h-9 w-12 cursor-pointer rounded border border-slate-200 bg-transparent p-1"
                                            title="Pilih warna bebas"
                                            aria-label="Pilih warna primer"
                                        />
                                        <div className="flex-1">
                                            <Input
                                                value={theme.primary_color}
                                                onChange={(e) => {
                                                    setTheme({
                                                        ...theme,
                                                        primary_color: e.target.value,
                                                    });
                                                    setHasUnsavedChanges(true);
                                                }}
                                                placeholder="#2563eb"
                                                className="font-mono text-xs uppercase"
                                            />
                                        </div>
                                    </div>
                                </div>

                                {/* Typography Presets */}
                                <div className="rounded-xl border border-slate-200 bg-white p-4 space-y-3">
                                    <label className="text-xs font-semibold text-slate-900">
                                        Pilihan Tipografi Font
                                    </label>
                                    <p className="text-[11px] text-slate-500">
                                        Pilih tipe huruf modern yang di-load otomatis dari Google Fonts tanpa menurunkan skor kecepatan web.
                                    </p>

                                    <div className="grid grid-cols-2 gap-3 pt-1">
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setTheme({
                                                    ...theme,
                                                    font_preset: 'inter',
                                                });
                                                setHasUnsavedChanges(true);
                                            }}
                                            className={`flex flex-col items-start rounded-xl border p-3 text-left transition-all ${
                                                theme.font_preset === 'inter'
                                                    ? 'border-blue-600 bg-blue-50/40 ring-1 ring-blue-600'
                                                    : 'border-slate-200 hover:border-slate-300'
                                            }`}
                                        >
                                            <div className="flex items-center justify-between w-full">
                                                <span className="text-xs font-bold text-slate-900 font-sans">
                                                    Inter
                                                </span>
                                                {theme.font_preset === 'inter' && (
                                                    <Check className="h-4 w-4 text-blue-600" />
                                                )}
                                            </div>
                                            <p className="mt-1 text-[11px] text-slate-500">
                                                Netral, clean, dan keterbacaan tinggi di segala ukuran layar.
                                            </p>
                                        </button>

                                        <button
                                            type="button"
                                            onClick={() => {
                                                setTheme({
                                                    ...theme,
                                                    font_preset: 'plus_jakarta',
                                                });
                                                setHasUnsavedChanges(true);
                                            }}
                                            className={`flex flex-col items-start rounded-xl border p-3 text-left transition-all ${
                                                theme.font_preset === 'plus_jakarta'
                                                    ? 'border-blue-600 bg-blue-50/40 ring-1 ring-blue-600'
                                                    : 'border-slate-200 hover:border-slate-300'
                                            }`}
                                        >
                                            <div className="flex items-center justify-between w-full">
                                                <span className="text-xs font-bold text-slate-900 font-sans">
                                                    Plus Jakarta Sans
                                                </span>
                                                {theme.font_preset === 'plus_jakarta' && (
                                                    <Check className="h-4 w-4 text-blue-600" />
                                                )}
                                            </div>
                                            <p className="mt-1 text-[11px] text-slate-500">
                                                Geometris, berkarakter tegas, modern & ramah.
                                            </p>
                                        </button>
                                    </div>
                                </div>

                                {/* Custom Banner Image URL */}
                                <div className="rounded-xl border border-slate-200 bg-white p-4 space-y-2">
                                    <label className="text-xs font-semibold text-slate-900">
                                        URL Foto Latar Hero (Opsional)
                                    </label>
                                    <Input
                                        value={theme.banner_image_url || ''}
                                        onChange={(e) => {
                                            setTheme({
                                                ...theme,
                                                banner_image_url: e.target.value.trim() || null,
                                            });
                                            setHasUnsavedChanges(true);
                                        }}
                                        placeholder="https://example.com/cover-hero.jpg"
                                        className="text-xs"
                                    />
                                    <p className="text-[10px] text-slate-400">
                                        Kosongkan untuk menggunakan gaya minimalis latar belakang standar.
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* RIGHT COLUMN: Live Responsive Preview (7 of 12 cols on desktop) */}
                    <div className="space-y-3 lg:col-span-7">
                        {/* Device Mode & Refresh Bar */}
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-2.5">
                            {/* Device Mode Switcher */}
                            <div className="flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 p-1">
                                <button
                                    type="button"
                                    onClick={() => setDeviceMode('desktop')}
                                    className={`flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-semibold transition-colors ${
                                        deviceMode === 'desktop'
                                            ? 'bg-white text-slate-900 shadow-xs'
                                            : 'text-slate-500 hover:text-slate-900'
                                    }`}
                                    title="Tampilan Desktop (100%)"
                                    aria-label="Tampilan Desktop"
                                >
                                    <Monitor className="h-3.5 w-3.5" />
                                    <span>Desktop</span>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setDeviceMode('tablet')}
                                    className={`flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-semibold transition-colors ${
                                        deviceMode === 'tablet'
                                            ? 'bg-white text-slate-900 shadow-xs'
                                            : 'text-slate-500 hover:text-slate-900'
                                    }`}
                                    title="Tampilan Tablet (768px)"
                                    aria-label="Tampilan Tablet"
                                >
                                    <Tablet className="h-3.5 w-3.5" />
                                    <span>Tablet</span>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setDeviceMode('mobile')}
                                    className={`flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-semibold transition-colors ${
                                        deviceMode === 'mobile'
                                            ? 'bg-white text-slate-900 shadow-xs'
                                            : 'text-slate-500 hover:text-slate-900'
                                    }`}
                                    title="Tampilan Mobile (375px)"
                                    aria-label="Tampilan Mobile"
                                >
                                    <Smartphone className="h-3.5 w-3.5" />
                                    <span>Mobile</span>
                                </button>
                            </div>

                            {/* Preview Controls */}
                            <div className="flex items-center gap-2">
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    onClick={() => setIframeKey((prev) => prev + 1)}
                                    title="Segarkan Pratinjau"
                                    className="text-xs"
                                >
                                    <RefreshCw className="mr-1 h-3.5 w-3.5" />
                                    Segarkan
                                </Button>
                                <a
                                    href="/app/landing-builder/preview"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    <span>Tab Baru</span>
                                    <ExternalLink className="h-3.5 w-3.5 text-slate-400" />
                                </a>
                            </div>
                        </div>

                        {/* Device Frame Wrapper */}
                        <div className="flex min-h-[680px] w-full items-center justify-center rounded-xl border border-slate-200 bg-slate-100/70 p-4">
                            <div
                                className={`w-full transition-all duration-300 ${getPreviewContainerWidth()} overflow-hidden rounded-xl border border-slate-300 bg-white shadow-xs`}
                            >
                                {/* Mock Browser Header */}
                                <div className="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-3 py-2">
                                    <div className="flex items-center gap-1.5">
                                        <span className="h-2.5 w-2.5 rounded-full bg-slate-300" />
                                        <span className="h-2.5 w-2.5 rounded-full bg-slate-300" />
                                        <span className="h-2.5 w-2.5 rounded-full bg-slate-300" />
                                    </div>
                                    <div className="flex-1 px-4 text-center">
                                        <span className="truncate inline-block max-w-[200px] font-mono text-[10px] text-slate-500">
                                            {publicUrl}
                                        </span>
                                    </div>
                                    <div className="text-[10px] font-medium text-slate-400">
                                        {deviceMode.toUpperCase()}
                                    </div>
                                </div>

                                {/* Iframe Preview */}
                                <iframe
                                    key={iframeKey}
                                    ref={iframeRef}
                                    src="/app/landing-builder/preview"
                                    title="Live Landing Page Preview"
                                    className="h-[620px] w-full border-0 bg-white"
                                />
                            </div>
                        </div>

                        {/* Unsaved changes notice */}
                        {hasUnsavedChanges && (
                            <div className="rounded-lg border border-amber-200 bg-amber-50 p-2.5 text-xs text-amber-800 flex items-center justify-between">
                                <span>
                                    Ada perubahan yang belum disimpan. Klik <strong>Simpan Perubahan</strong> agar pratinjau dan halaman publik terbarukan.
                                </span>
                                <Button
                                    variant="primary"
                                    size="sm"
                                    onClick={handleSave}
                                    isLoading={isSaving}
                                    className="shrink-0 text-xs"
                                >
                                    Simpan Sekarang
                                </Button>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Reset Confirmation Dialog */}
            <ConfirmationDialog
                isOpen={isResetDialogOpen}
                onClose={() => setIsResetDialogOpen(false)}
                onConfirm={handleResetDefaults}
                title="Reset ke Template Standar?"
                message="Tindakan ini akan mengembalikan seluruh urutan bagian, teks, dan tema warna ke pengaturan bawaan sistem. Apakah Anda yakin?"
                confirmLabel="Ya, Kembalikan ke Standar"
                cancelLabel="Batal"
                variant="danger"
                isLoading={isResetting}
            />
        </OwnerLayout>
    );
}
