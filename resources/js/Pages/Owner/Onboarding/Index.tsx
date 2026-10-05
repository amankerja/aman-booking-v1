import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Armchair,
    ArrowLeft,
    ArrowRight,
    Car,
    Check,
    CheckCircle2,
    Copy,
    ExternalLink,
    HeartHandshake,
    Info,
    LayoutDashboard,
    Phone,
    Scissors,
    Sliders,
    Sparkles,
    Trophy,
    User,
} from 'lucide-react';
import React, { useState } from 'react';
import { Badge } from '../../../Components/ui/Badge';
import { Button } from '../../../Components/ui/Button';
import { Input } from '../../../Components/ui/Input';
import { Modal } from '../../../Components/ui/Modal';
import { Select } from '../../../Components/ui/Select';
import { Textarea } from '../../../Components/ui/Textarea';
import { ToastProvider, useToast } from '../../../Components/ui/Toast';

interface BusinessData {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    logo_url?: string | null;
    whatsapp?: string;
    phone?: string;
    email?: string;
    address?: string;
    timezone: string;
    description?: string;
    is_published: boolean;
    published_at?: string | null;
    public_url: string;
}

interface CustomizationQuestion {
    key: string;
    label: string;
    type: 'text' | 'number' | 'boolean' | 'time' | 'select';
    default: string | number | boolean;
    min?: number;
    max?: number;
    options?: Record<string, string>;
}

interface BusinessTemplate {
    id: string;
    name: string;
    category_name: string;
    description: string;
    icon: string;
    version: string;
    origin: string;
    customization_questions?: CustomizationQuestion[];
    services?: Array<{
        name: string;
        price_idr: number;
        duration_minutes: number;
        capacity: number;
        is_featured: boolean;
    }>;
    default_resources?: Array<{
        name: string;
        type_code: string;
        capacity: number;
    }>;
}

interface ServiceItem {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    price_idr: number;
    duration_minutes: number;
    buffer_after: number;
    capacity: number;
    is_active: boolean;
    is_featured: boolean;
}

interface ResourceItem {
    id: number;
    name: string;
    capacity: number;
    state: string;
    type_code: string;
    type_name: string;
}

interface HourItem {
    id: number;
    day_of_week: number;
    is_open: boolean;
    open_time: string;
    close_time: string;
    breaks?: Array<{ name: string; start_time: string; end_time: string }>;
}

interface OnboardingProps {
    business: BusinessData;
    templates: BusinessTemplate[];
    onboarding: {
        completed: boolean;
        current_step: number;
        template_id?: string | null;
        installed_at?: string | null;
        version?: string | null;
    };
    services: ServiceItem[];
    resources: ResourceItem[];
    hours: HourItem[];
    landing?: Record<string, unknown>;
    workflow?: Record<string, unknown>;
}

const DAY_NAMES = [
    'Minggu',
    'Senin',
    'Selasa',
    'Rabu',
    'Kamis',
    'Jumat',
    'Sabtu',
];

const OnboardingContent: React.FC<OnboardingProps> = ({
    business,
    templates,
    onboarding,
    services,
    resources,
    hours,
}) => {
    const toast = useToast();
    const [currentStep, setCurrentStep] = useState<number>(
        onboarding.completed ? 8 : (onboarding.current_step ?? 1)
    );

    // Selected template preview modal
    const [selectedTemplate, setSelectedTemplate] =
        useState<BusinessTemplate | null>(null);
    const [customizationValues, setCustomizationValues] = useState<
        Record<string, string | number | boolean>
    >({});
    const [isInstalling, setIsInstalling] = useState(false);

    // Step 1 Form
    const profileForm = useForm({
        name: business.name || '',
        whatsapp: business.whatsapp || '',
        email: business.email || '',
        address: business.address || '',
        timezone: business.timezone || 'Asia/Jakarta',
        description: business.description || '',
    });

    // Step 3 Services state
    const [servicesDraft, setServicesDraft] = useState<ServiceItem[]>(services);

    // Step 4 Resources state
    const [resourcesDraft, setResourcesDraft] =
        useState<ResourceItem[]>(resources);

    // Step 5 Hours state
    const [hoursDraft, setHoursDraft] = useState<HourItem[]>(() => {
        if (hours && hours.length === 7) return hours;
        return [0, 1, 2, 3, 4, 5, 6].map((day) => ({
            id: day,
            day_of_week: day,
            is_open: day !== 0,
            open_time: '09:00',
            close_time: '17:00',
            breaks: [],
        }));
    });

    const getTemplateIcon = (iconName: string) => {
        switch (iconName) {
            case 'Scissors':
                return <Scissors className="h-5 w-5 text-blue-600" />;
            case 'Sparkles':
                return <Sparkles className="h-5 w-5 text-blue-600" />;
            case 'HeartHandshake':
                return <HeartHandshake className="h-5 w-5 text-blue-600" />;
            case 'Trophy':
                return <Trophy className="h-5 w-5 text-blue-600" />;
            case 'Car':
                return <Car className="h-5 w-5 text-blue-600" />;
            default:
                return <Sliders className="h-5 w-5 text-blue-600" />;
        }
    };

    // Step navigation
    const steps = [
        { num: 1, title: 'Profil Bisnis' },
        { num: 2, title: 'Pilih Template' },
        { num: 3, title: 'Layanan' },
        { num: 4, title: 'Resource & Staf' },
        { num: 5, title: 'Jam Operasional' },
        { num: 6, title: 'Alur & Notifikasi' },
        { num: 7, title: 'Tinjauan (Review)' },
        { num: 8, title: 'Go Live' },
    ];

    const handleSaveStep1 = (e: React.FormEvent) => {
        e.preventDefault();
        profileForm.post('/app/onboarding/step-1', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Profil usaha berhasil disimpan!');
                setCurrentStep(2);
            },
            onError: () => {
                toast.error('Periksa kembali data profil Anda.');
            },
        });
    };

    const handleOpenTemplateModal = (tpl: BusinessTemplate) => {
        setSelectedTemplate(tpl);
        const defaults: Record<string, string | number | boolean> = {};
        if (tpl.customization_questions) {
            tpl.customization_questions.forEach((q) => {
                defaults[q.key] = q.default;
            });
        }
        setCustomizationValues(defaults);
    };

    const handleInstallTemplate = () => {
        if (!selectedTemplate) return;
        setIsInstalling(true);
        router.post(
            '/app/onboarding/install-template',
            {
                template_id: selectedTemplate.id,
                customization: customizationValues,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsInstalling(false);
                    setSelectedTemplate(null);
                    toast.success(
                        `Template ${selectedTemplate.name} terinstal sebagai DRAFT!`
                    );
                    setCurrentStep(3);
                },
                onError: () => {
                    setIsInstalling(false);
                    toast.error('Gagal menginstal template.');
                },
            }
        );
    };

    const handleSaveServices = () => {
        router.post(
            '/app/onboarding/update-services',
            { services: servicesDraft },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Layanan berhasil diperbarui!');
                    setCurrentStep(4);
                },
                onError: () => {
                    toast.error('Gagal menyimpan layanan.');
                },
            }
        );
    };

    const handleSaveResources = () => {
        router.post(
            '/app/onboarding/update-resources',
            { resources: resourcesDraft },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Resource berhasil diperbarui!');
                    setCurrentStep(5);
                },
                onError: () => {
                    toast.error('Gagal menyimpan resource.');
                },
            }
        );
    };

    const handleSaveSchedule = () => {
        router.post(
            '/app/onboarding/update-schedule',
            { hours: hoursDraft },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Jadwal operasional berhasil disimpan!');
                    setCurrentStep(6);
                },
                onError: () => {
                    toast.error('Gagal menyimpan jadwal operasional.');
                },
            }
        );
    };

    const handleSaveWorkflow = () => {
        router.post(
            '/app/onboarding/update-workflow',
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Alur workflow terkonfirmasi!');
                    setCurrentStep(7);
                },
            }
        );
    };

    const handlePublish = () => {
        router.post(
            '/app/onboarding/publish',
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(
                        'Selamat! Website & link booking online Anda resmi LIVE!'
                    );
                    setCurrentStep(8);
                },
                onError: () => {
                    toast.error('Gagal mempublikasikan bisnis.');
                },
            }
        );
    };

    const handleReset = () => {
        if (
            !confirm(
                'Apakah Anda yakin ingin mereset template draft dan memilih template baru?'
            )
        )
            return;
        router.post(
            '/app/onboarding/reset',
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.info('Draft direset. Silakan pilih template baru.');
                    setCurrentStep(2);
                },
            }
        );
    };

    const copyPublicLink = () => {
        navigator.clipboard.writeText(business.public_url);
        toast.success('Link booking online berhasil disalin!');
    };

    return (
        <div className="flex min-h-screen flex-col bg-[#f8fafc] font-sans text-[#0f172a]">
            <Head title="Onboarding Wizard — AMAN BOOKING" />

            {/* Top Navigation */}
            <header className="sticky top-0 z-30 flex items-center justify-between border-b border-[#e2e8f0] bg-white px-6 py-4">
                <div className="flex items-center gap-3">
                    <span className="text-lg font-bold tracking-tight text-slate-900">
                        AMAN BOOKING
                    </span>
                    <span className="text-slate-300">/</span>
                    <span className="text-sm font-medium text-slate-600">
                        {business.name || 'Setup Bisnis Baru'}
                    </span>
                    {business.is_published ? (
                        <Badge variant="success" size="sm">
                            LIVE
                        </Badge>
                    ) : (
                        <Badge variant="warning" size="sm">
                            DRAFT SETUP
                        </Badge>
                    )}
                </div>

                <div className="flex items-center gap-3">
                    <Link
                        href="/app/dashboard"
                        className="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900"
                    >
                        <LayoutDashboard className="h-3.5 w-3.5" />
                        Dashboard Utama
                    </Link>
                </div>
            </header>

            {/* Stepper Progress Bar */}
            <div className="border-b border-[#e2e8f0] bg-white px-6 py-3">
                <div className="mx-auto flex max-w-5xl scrollbar-none items-center justify-between gap-1 overflow-x-auto pb-1">
                    {steps.map((st) => {
                        const isDone = currentStep > st.num;
                        const isCurrent = currentStep === st.num;
                        return (
                            <button
                                key={st.num}
                                type="button"
                                onClick={() => {
                                    // Can navigate back to previous steps
                                    if (isDone || isCurrent) {
                                        setCurrentStep(st.num);
                                    }
                                }}
                                disabled={!isDone && !isCurrent}
                                className={`flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs font-medium whitespace-nowrap transition-all ${
                                    isCurrent
                                        ? 'bg-blue-50 font-semibold text-blue-700 ring-1 ring-blue-600/30'
                                        : isDone
                                          ? 'cursor-pointer text-emerald-700 hover:bg-slate-50'
                                          : 'cursor-not-allowed text-slate-400'
                                }`}
                            >
                                <span
                                    className={`flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold ${
                                        isCurrent
                                            ? 'bg-blue-600 text-white'
                                            : isDone
                                              ? 'bg-emerald-600 text-white'
                                              : 'bg-slate-200 text-slate-500'
                                    }`}
                                >
                                    {isDone ? (
                                        <Check className="h-3 w-3 stroke-[3]" />
                                    ) : (
                                        st.num
                                    )}
                                </span>
                                <span>{st.title}</span>
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* Main Content Body */}
            <main className="mx-auto w-full max-w-4xl flex-1 p-6 md:p-8">
                {/* STEP 1: Profil Bisnis */}
                {currentStep === 1 && (
                    <div className="rounded-xl border border-[#e2e8f0] bg-white p-6 md:p-8">
                        <div className="mb-6">
                            <span className="text-xs font-semibold tracking-wider text-blue-600 uppercase">
                                Langkah 1 dari 8
                            </span>
                            <h2 className="mt-1 text-xl font-bold text-slate-900">
                                Profil & Identitas Usaha Anda
                            </h2>
                            <p className="mt-1 text-sm text-slate-500">
                                Masukkan nama usaha dan kontak WhatsApp resmi
                                yang akan digunakan untuk menerima notifikasi
                                booking.
                            </p>
                        </div>

                        <form onSubmit={handleSaveStep1} className="space-y-5">
                            <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                                <Input
                                    label="Nama Bisnis / Brand *"
                                    value={profileForm.data.name}
                                    onChange={(e) =>
                                        profileForm.setData(
                                            'name',
                                            e.target.value
                                        )
                                    }
                                    placeholder="Contoh: Barbershop Batavia"
                                    error={profileForm.errors.name}
                                    required
                                />

                                <Input
                                    label="Nomor WhatsApp Resmi *"
                                    value={profileForm.data.whatsapp}
                                    onChange={(e) =>
                                        profileForm.setData(
                                            'whatsapp',
                                            e.target.value
                                        )
                                    }
                                    placeholder="081234567890"
                                    helperText="Gunakan format nomor Indonesia aktif (+62 / 08xx)"
                                    error={profileForm.errors.whatsapp}
                                    required
                                />

                                <Input
                                    label="Email Usaha"
                                    type="email"
                                    value={profileForm.data.email}
                                    onChange={(e) =>
                                        profileForm.setData(
                                            'email',
                                            e.target.value
                                        )
                                    }
                                    placeholder="kontak@bisnisanda.com"
                                    error={profileForm.errors.email}
                                />

                                <Select
                                    label="Zona Waktu Operasional *"
                                    value={profileForm.data.timezone}
                                    onChange={(e) =>
                                        profileForm.setData(
                                            'timezone',
                                            e.target.value
                                        )
                                    }
                                    options={[
                                        {
                                            value: 'Asia/Jakarta',
                                            label: 'WIB (Asia/Jakarta - Jakarta, Bandung, Medan)',
                                        },
                                        {
                                            value: 'Asia/Makassar',
                                            label: 'WITA (Asia/Makassar - Bali, Makassar, Manado)',
                                        },
                                        {
                                            value: 'Asia/Jayapura',
                                            label: 'WIT (Asia/Jayapura - Jayapura, Ambon, Sorong)',
                                        },
                                    ]}
                                    error={profileForm.errors.timezone}
                                    required
                                />
                            </div>

                            <Textarea
                                label="Alamat Fisik / Lokasi"
                                value={profileForm.data.address}
                                onChange={(e) =>
                                    profileForm.setData(
                                        'address',
                                        e.target.value
                                    )
                                }
                                placeholder="Jl. Sudirman No. 12, Jakarta Pusat"
                                rows={2}
                                error={profileForm.errors.address}
                            />

                            <Textarea
                                label="Deskripsi Singkat Usaha (Ditampilkan pada Halaman Publik)"
                                value={profileForm.data.description}
                                onChange={(e) =>
                                    profileForm.setData(
                                        'description',
                                        e.target.value
                                    )
                                }
                                placeholder="Jelaskan secara singkat layanan unggulan dan kelebihan bisnis Anda..."
                                rows={3}
                                error={profileForm.errors.description}
                            />

                            <div className="flex justify-end pt-4">
                                <Button
                                    type="submit"
                                    variant="primary"
                                    isLoading={profileForm.processing}
                                    className="gap-2"
                                >
                                    <span>Lanjut ke Pilih Template</span>
                                    <ArrowRight className="h-4 w-4" />
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                {/* STEP 2: Pilihan Template Bisnis */}
                {currentStep === 2 && (
                    <div className="space-y-6">
                        <div className="rounded-xl border border-[#e2e8f0] bg-white p-6">
                            <span className="text-xs font-semibold tracking-wider text-blue-600 uppercase">
                                Langkah 2 dari 8
                            </span>
                            <h2 className="mt-1 text-xl font-bold text-slate-900">
                                Pilih Template Bisnis Anda
                            </h2>
                            <p className="mt-1 text-sm text-slate-500">
                                Template akan otomatis menyiapkan daftar layanan
                                awal, resource, jadwal buka, dan copy landing
                                page dalam status{' '}
                                <strong className="text-slate-700">
                                    DRAFT
                                </strong>{' '}
                                siap Anda review.
                            </p>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            {templates.map((tpl) => (
                                <div
                                    key={tpl.id}
                                    className="flex flex-col justify-between rounded-xl border border-[#e2e8f0] bg-white p-5 transition-all hover:border-blue-600/50 hover:bg-blue-50/10"
                                >
                                    <div>
                                        <div className="mb-3 flex items-center justify-between">
                                            <div className="flex h-10 w-10 items-center justify-center rounded-lg border border-blue-100 bg-blue-50">
                                                {getTemplateIcon(tpl.icon)}
                                            </div>
                                            <Badge variant="neutral" size="sm">
                                                {tpl.origin} v{tpl.version}
                                            </Badge>
                                        </div>

                                        <h3 className="text-base font-bold text-slate-900">
                                            {tpl.name}
                                        </h3>
                                        <p className="mt-1 line-clamp-2 text-xs text-slate-500">
                                            {tpl.description}
                                        </p>

                                        <div className="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-3 text-[11px] text-slate-600">
                                            <span className="rounded border border-slate-200 bg-slate-50 px-2 py-0.5">
                                                {tpl.services?.length || 4}{' '}
                                                Layanan Default
                                            </span>
                                            <span className="rounded border border-slate-200 bg-slate-50 px-2 py-0.5">
                                                {tpl.default_resources
                                                    ?.length || 2}{' '}
                                                Resource
                                            </span>
                                            <span className="rounded border border-slate-200 bg-slate-50 px-2 py-0.5">
                                                Zero-Config Workflow
                                            </span>
                                        </div>
                                    </div>

                                    <div className="mt-5 pt-3">
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            className="w-full justify-center"
                                            onClick={() =>
                                                handleOpenTemplateModal(tpl)
                                            }
                                        >
                                            Kustomisasi & Pasang Template
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="flex items-center justify-between pt-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setCurrentStep(1)}
                                className="gap-1.5 text-slate-600"
                            >
                                <ArrowLeft className="h-4 w-4" />
                                Kembali ke Profil
                            </Button>
                        </div>
                    </div>
                )}

                {/* STEP 3: Review Layanan */}
                {currentStep === 3 && (
                    <div className="space-y-6 rounded-xl border border-[#e2e8f0] bg-white p-6 md:p-8">
                        <div className="flex items-start justify-between">
                            <div>
                                <span className="text-xs font-semibold tracking-wider text-blue-600 uppercase">
                                    Langkah 3 dari 8
                                </span>
                                <h2 className="mt-1 text-xl font-bold text-slate-900">
                                    Layanan yang Terpasang (Status Draft)
                                </h2>
                                <p className="mt-1 text-sm text-slate-500">
                                    Sesuaikan nama, tarif (Rp), dan durasi menit
                                    sesuai dengan operasional bisnis Anda.
                                </p>
                            </div>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={handleReset}
                                className="text-xs text-red-600 hover:text-red-700"
                            >
                                Ganti Template
                            </Button>
                        </div>

                        <div className="space-y-4">
                            {servicesDraft.map((srv, idx) => (
                                <div
                                    key={srv.id}
                                    className="space-y-3 rounded-lg border border-[#e2e8f0] bg-slate-50/50 p-4"
                                >
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-semibold text-slate-400">
                                            Layanan #{idx + 1}
                                        </span>
                                        <label className="flex cursor-pointer items-center gap-2 text-xs font-medium text-slate-700">
                                            <input
                                                type="checkbox"
                                                checked={srv.is_active}
                                                onChange={(e) => {
                                                    const updated = [
                                                        ...servicesDraft,
                                                    ];
                                                    updated[idx].is_active =
                                                        e.target.checked;
                                                    setServicesDraft(updated);
                                                }}
                                                className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                            />
                                            Aktif
                                        </label>
                                    </div>

                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                                        <div className="md:col-span-1">
                                            <Input
                                                label="Nama Layanan"
                                                value={srv.name}
                                                onChange={(e) => {
                                                    const updated = [
                                                        ...servicesDraft,
                                                    ];
                                                    updated[idx].name =
                                                        e.target.value;
                                                    setServicesDraft(updated);
                                                }}
                                            />
                                        </div>
                                        <div>
                                            <Input
                                                label="Tarif (Rp)"
                                                type="number"
                                                value={srv.price_idr}
                                                onChange={(e) => {
                                                    const updated = [
                                                        ...servicesDraft,
                                                    ];
                                                    updated[idx].price_idr =
                                                        parseFloat(
                                                            e.target.value
                                                        ) || 0;
                                                    setServicesDraft(updated);
                                                }}
                                            />
                                        </div>
                                        <div>
                                            <Input
                                                label="Durasi (Menit)"
                                                type="number"
                                                value={srv.duration_minutes}
                                                onChange={(e) => {
                                                    const updated = [
                                                        ...servicesDraft,
                                                    ];
                                                    updated[
                                                        idx
                                                    ].duration_minutes =
                                                        parseInt(
                                                            e.target.value,
                                                            10
                                                        ) || 15;
                                                    setServicesDraft(updated);
                                                }}
                                            />
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="flex items-center justify-between border-t border-slate-100 pt-4">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setCurrentStep(2)}
                                className="gap-1.5 text-slate-600"
                            >
                                <ArrowLeft className="h-4 w-4" />
                                Kembali
                            </Button>
                            <Button
                                variant="primary"
                                onClick={handleSaveServices}
                                className="gap-2"
                            >
                                <span>Simpan & Atur Resource</span>
                                <ArrowRight className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                )}

                {/* STEP 4: Resource & Staf */}
                {currentStep === 4 && (
                    <div className="space-y-6 rounded-xl border border-[#e2e8f0] bg-white p-6 md:p-8">
                        <div>
                            <span className="text-xs font-semibold tracking-wider text-blue-600 uppercase">
                                Langkah 4 dari 8
                            </span>
                            <h2 className="mt-1 text-xl font-bold text-slate-900">
                                Resource, Staf & Fasilitas
                            </h2>
                            <p className="mt-1 text-sm text-slate-500">
                                Atur nama petugas (staf/terapis/barber) atau
                                unit fasilitas yang menangani pesanan pelanggan.
                            </p>
                        </div>

                        <div className="space-y-3">
                            {resourcesDraft.map((res, idx) => (
                                <div
                                    key={res.id}
                                    className="flex flex-col justify-between gap-4 rounded-lg border border-[#e2e8f0] bg-slate-50/50 p-4 md:flex-row md:items-center"
                                >
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">
                                            {res.type_code === 'STAFF' ? (
                                                <User className="h-4 w-4" />
                                            ) : (
                                                <Armchair className="h-4 w-4" />
                                            )}
                                        </div>
                                        <div>
                                            <span className="block text-xs font-semibold text-slate-400">
                                                {res.type_name}
                                            </span>
                                            <Input
                                                value={res.name}
                                                onChange={(e) => {
                                                    const updated = [
                                                        ...resourcesDraft,
                                                    ];
                                                    updated[idx].name =
                                                        e.target.value;
                                                    setResourcesDraft(updated);
                                                }}
                                                className="mt-1 font-semibold"
                                            />
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-3">
                                        <span className="text-xs text-slate-500">
                                            Kapasitas:
                                        </span>
                                        <Input
                                            type="number"
                                            value={res.capacity}
                                            onChange={(e) => {
                                                const updated = [
                                                    ...resourcesDraft,
                                                ];
                                                updated[idx].capacity =
                                                    parseInt(
                                                        e.target.value,
                                                        10
                                                    ) || 1;
                                                setResourcesDraft(updated);
                                            }}
                                            className="w-20"
                                        />
                                        <Badge variant="success" size="sm">
                                            {res.state}
                                        </Badge>
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="flex items-center justify-between border-t border-slate-100 pt-4">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setCurrentStep(3)}
                                className="gap-1.5 text-slate-600"
                            >
                                <ArrowLeft className="h-4 w-4" />
                                Kembali ke Layanan
                            </Button>
                            <Button
                                variant="primary"
                                onClick={handleSaveResources}
                                className="gap-2"
                            >
                                <span>Simpan & Atur Jam Operasional</span>
                                <ArrowRight className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                )}

                {/* STEP 5: Jam Operasional */}
                {currentStep === 5 && (
                    <div className="space-y-6 rounded-xl border border-[#e2e8f0] bg-white p-6 md:p-8">
                        <div>
                            <span className="text-xs font-semibold tracking-wider text-blue-600 uppercase">
                                Langkah 5 dari 8
                            </span>
                            <h2 className="mt-1 text-xl font-bold text-slate-900">
                                Jadwal Buka & Jam Operasional
                            </h2>
                            <p className="mt-1 text-sm text-slate-500">
                                Tentukan hari dan jam operasional ketika slot
                                booking dapat dipilih oleh pelanggan online.
                            </p>
                        </div>

                        <div className="space-y-2">
                            {hoursDraft.map((h, idx) => (
                                <div
                                    key={h.day_of_week}
                                    className={`flex items-center justify-between rounded-lg border p-3 transition-colors ${
                                        h.is_open
                                            ? 'border-[#e2e8f0] bg-white'
                                            : 'border-slate-200 bg-slate-50 text-slate-400'
                                    }`}
                                >
                                    <div className="flex w-36 items-center gap-3">
                                        <input
                                            type="checkbox"
                                            checked={h.is_open}
                                            onChange={(e) => {
                                                const updated = [...hoursDraft];
                                                updated[idx].is_open =
                                                    e.target.checked;
                                                setHoursDraft(updated);
                                            }}
                                            className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                        />
                                        <span className="text-sm font-semibold text-slate-800">
                                            {DAY_NAMES[h.day_of_week]}
                                        </span>
                                    </div>

                                    {h.is_open ? (
                                        <div className="flex items-center gap-2">
                                            <input
                                                type="time"
                                                value={h.open_time}
                                                onChange={(e) => {
                                                    const updated = [
                                                        ...hoursDraft,
                                                    ];
                                                    updated[idx].open_time =
                                                        e.target.value;
                                                    setHoursDraft(updated);
                                                }}
                                                className="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium"
                                            />
                                            <span className="text-xs text-slate-400">
                                                s/d
                                            </span>
                                            <input
                                                type="time"
                                                value={h.close_time}
                                                onChange={(e) => {
                                                    const updated = [
                                                        ...hoursDraft,
                                                    ];
                                                    updated[idx].close_time =
                                                        e.target.value;
                                                    setHoursDraft(updated);
                                                }}
                                                className="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium"
                                            />
                                        </div>
                                    ) : (
                                        <span className="text-xs font-semibold text-slate-400 italic">
                                            Tutup (Libur)
                                        </span>
                                    )}
                                </div>
                            ))}
                        </div>

                        <div className="flex items-center justify-between border-t border-slate-100 pt-4">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setCurrentStep(4)}
                                className="gap-1.5 text-slate-600"
                            >
                                <ArrowLeft className="h-4 w-4" />
                                Kembali
                            </Button>
                            <Button
                                variant="primary"
                                onClick={handleSaveSchedule}
                                className="gap-2"
                            >
                                <span>Simpan & Atur Alur Notifikasi</span>
                                <ArrowRight className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                )}

                {/* STEP 6: Workflow & Notifikasi */}
                {currentStep === 6 && (
                    <div className="space-y-6 rounded-xl border border-[#e2e8f0] bg-white p-6 md:p-8">
                        <div>
                            <span className="text-xs font-semibold tracking-wider text-blue-600 uppercase">
                                Langkah 6 dari 8
                            </span>
                            <h2 className="mt-1 text-xl font-bold text-slate-900">
                                Zero-Config Default Workflow (PRD 49)
                            </h2>
                            <p className="mt-1 text-sm text-slate-500">
                                Alur kerja otomatis bawaan sistem yang menjamin
                                setiap reservasi baru langsung terorganisir
                                dengan rapi.
                            </p>
                        </div>

                        {/* Workflow Visual Diagram */}
                        <div className="flex flex-col items-center justify-between gap-4 rounded-xl border border-[#e2e8f0] bg-slate-50 p-6 md:flex-row">
                            <div className="w-full rounded-lg border border-slate-200 bg-white p-4 text-center shadow-sm md:w-56">
                                <span className="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                                    Trigger
                                </span>
                                <span className="mt-1 block text-sm font-bold text-slate-900">
                                    Booking Dibuat
                                </span>
                                <span className="mt-0.5 block text-[11px] text-slate-500">
                                    Oleh Pelanggan Online
                                </span>
                            </div>

                            <div className="flex items-center text-sm font-bold text-slate-400">
                                <ArrowRight className="hidden h-5 w-5 md:block" />
                                <span className="md:hidden">↓</span>
                            </div>

                            <div className="w-full rounded-lg border border-blue-200 bg-white p-4 text-center shadow-sm md:w-56">
                                <span className="block text-[10px] font-bold tracking-wider text-blue-600 uppercase">
                                    Status 1
                                </span>
                                <span className="mt-1 block text-sm font-bold text-blue-700">
                                    CONFIRMED
                                </span>
                                <span className="mt-0.5 block text-[11px] text-slate-500">
                                    Kirim WA Konfirmasi + QR
                                </span>
                            </div>

                            <div className="flex items-center text-sm font-bold text-slate-400">
                                <ArrowRight className="hidden h-5 w-5 md:block" />
                                <span className="md:hidden">↓</span>
                            </div>

                            <div className="w-full rounded-lg border border-emerald-200 bg-white p-4 text-center shadow-sm md:w-56">
                                <span className="block text-[10px] font-bold tracking-wider text-emerald-600 uppercase">
                                    Status Akhir
                                </span>
                                <span className="mt-1 block text-sm font-bold text-emerald-700">
                                    COMPLETED
                                </span>
                                <span className="mt-0.5 block text-[11px] text-slate-500">
                                    Layanan Selesai Diberikan
                                </span>
                            </div>
                        </div>

                        {/* Notification summary */}
                        <div className="space-y-3 rounded-lg border border-slate-200 p-4">
                            <h4 className="flex items-center gap-2 text-xs font-bold tracking-wider text-slate-700 uppercase">
                                <Phone className="h-4 w-4 text-emerald-600" />
                                Pesan Notifikasi WhatsApp Otomatis
                            </h4>
                            <div className="grid grid-cols-1 gap-3 text-xs text-slate-600 md:grid-cols-2">
                                <div className="rounded border border-slate-200 bg-slate-50 p-3">
                                    <span className="mb-1 block font-semibold text-slate-800">
                                        1. Konfirmasi Instan
                                    </span>
                                    Pelanggan menerima kode booking, ringkasan
                                    jadwal, dan link kelola/reschedule mandiri.
                                </div>
                                <div className="rounded border border-slate-200 bg-slate-50 p-3">
                                    <span className="mb-1 block font-semibold text-slate-800">
                                        2. Pengingat H-1 (Reminder)
                                    </span>
                                    Sistem otomatis mengirimkan pengingat 24 jam
                                    sebelum waktu reservasi untuk menekan
                                    no-show.
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center justify-between border-t border-slate-100 pt-4">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setCurrentStep(5)}
                                className="gap-1.5 text-slate-600"
                            >
                                <ArrowLeft className="h-4 w-4" />
                                Kembali ke Jadwal
                            </Button>
                            <Button
                                variant="primary"
                                onClick={handleSaveWorkflow}
                                className="gap-2"
                            >
                                <span>Lanjut ke Tinjauan (Review)</span>
                                <ArrowRight className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                )}

                {/* STEP 7: Review Komprehensif (PRD 168: Owner Reviews) */}
                {currentStep === 7 && (
                    <div className="space-y-6 rounded-xl border border-[#e2e8f0] bg-white p-6 md:p-8">
                        <div className="flex items-start justify-between">
                            <div>
                                <span className="text-xs font-semibold tracking-wider text-blue-600 uppercase">
                                    Langkah 7 dari 8
                                </span>
                                <h2 className="mt-1 text-xl font-bold text-slate-900">
                                    Tinjauan Akhir Setup Bisnis
                                </h2>
                                <p className="mt-1 text-sm text-slate-500">
                                    Periksa seluruh konfigurasi sebelum
                                    mempublikasikannya secara publik.
                                </p>
                            </div>
                            <Badge variant="warning" size="md">
                                STATUS: DRAFT
                            </Badge>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2 rounded-lg border border-slate-200 p-4">
                                <span className="block text-xs font-bold tracking-wider text-slate-400 uppercase">
                                    Identitas Bisnis
                                </span>
                                <p className="text-base font-bold text-slate-900">
                                    {business.name}
                                </p>
                                <p className="text-xs text-slate-500">
                                    WhatsApp: {business.whatsapp || '-'}
                                </p>
                                <p className="text-xs text-slate-500">
                                    Zona Waktu: {business.timezone}
                                </p>
                                <p className="text-xs text-slate-500">
                                    Alamat: {business.address || '-'}
                                </p>
                            </div>

                            <div className="space-y-2 rounded-lg border border-slate-200 p-4">
                                <span className="block text-xs font-bold tracking-wider text-slate-400 uppercase">
                                    Link Publik Anda
                                </span>
                                <p className="rounded border border-blue-100 bg-blue-50 p-2 font-mono text-xs break-all text-blue-600">
                                    {business.public_url}
                                </p>
                                <p className="text-[11px] text-slate-500">
                                    Link ini akan aktif dan dapat diakses
                                    pelanggan setelah Anda menekan tombol Go
                                    Live di bawah.
                                </p>
                            </div>
                        </div>

                        {/* Services & Resources count summary */}
                        <div className="grid grid-cols-2 gap-3 text-center md:grid-cols-4">
                            <div className="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <span className="block text-xs text-slate-500">
                                    Layanan Aktif
                                </span>
                                <span className="text-lg font-bold text-slate-900">
                                    {
                                        servicesDraft.filter((s) => s.is_active)
                                            .length
                                    }
                                </span>
                            </div>
                            <div className="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <span className="block text-xs text-slate-500">
                                    Resource Bertugas
                                </span>
                                <span className="text-lg font-bold text-slate-900">
                                    {resourcesDraft.length}
                                </span>
                            </div>
                            <div className="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <span className="block text-xs text-slate-500">
                                    Hari Buka
                                </span>
                                <span className="text-lg font-bold text-slate-900">
                                    {hoursDraft.filter((h) => h.is_open).length}{' '}
                                    / 7 Hari
                                </span>
                            </div>
                            <div className="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <span className="block text-xs text-slate-500">
                                    Alur Workflow
                                </span>
                                <span className="mt-0.5 block text-sm font-bold text-emerald-600">
                                    Siap Aktif
                                </span>
                            </div>
                        </div>

                        <div className="flex items-center justify-between border-t border-slate-100 pt-4">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setCurrentStep(6)}
                                className="gap-1.5 text-slate-600"
                            >
                                <ArrowLeft className="h-4 w-4" />
                                Kembali
                            </Button>
                            <Button
                                variant="primary"
                                size="lg"
                                onClick={handlePublish}
                                className="gap-2 bg-emerald-600 font-bold text-white hover:bg-emerald-700"
                            >
                                <CheckCircle2 className="h-5 w-5" />
                                <span>Publikasikan Sekarang (Go Live)</span>
                            </Button>
                        </div>
                    </div>
                )}

                {/* STEP 8: Go Live & Sukses */}
                {currentStep === 8 && (
                    <div className="space-y-6 rounded-xl border border-emerald-200 bg-white p-8 text-center">
                        <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                            <CheckCircle2 className="h-10 w-10" />
                        </div>

                        <div>
                            <Badge variant="success" size="md">
                                WEBSITE & BOOKING ONLINE RESMI LIVE
                            </Badge>
                            <h2 className="mt-2 text-2xl font-extrabold text-slate-900">
                                Selamat, {business.name}!
                            </h2>
                            <p className="mx-auto mt-2 max-w-lg text-sm text-slate-500">
                                Usaha Anda kini telah siap menerima reservasi
                                online secara otomatis 24 jam tanpa takut jadwal
                                bentrok.
                            </p>
                        </div>

                        {/* Public Link Card */}
                        <div className="mx-auto max-w-md space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <span className="block text-xs font-semibold text-slate-500">
                                Tautan Booking Publik Anda:
                            </span>
                            <div className="flex items-center gap-2">
                                <input
                                    type="text"
                                    readOnly
                                    value={business.public_url}
                                    className="flex-1 rounded-md border border-slate-200 bg-white px-3 py-2 font-mono text-xs text-slate-800"
                                />
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    onClick={copyPublicLink}
                                    className="shrink-0 gap-1.5"
                                >
                                    <Copy className="h-3.5 w-3.5" />
                                    Salin
                                </Button>
                            </div>
                        </div>

                        {/* Quick CTA Actions */}
                        <div className="flex flex-col items-center justify-center gap-3 pt-2 sm:flex-row">
                            <a
                                href={business.public_url}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50 sm:w-auto"
                            >
                                <ExternalLink className="h-4 w-4 text-slate-500" />
                                <span>Lihat Halaman Publik</span>
                            </a>

                            <Link
                                href="/app/dashboard"
                                className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 sm:w-auto"
                            >
                                <LayoutDashboard className="h-4 w-4" />
                                <span>Buka Dashboard Pemilik</span>
                            </Link>
                        </div>
                    </div>
                )}
            </main>

            {/* Customization & Preview Modal (PRD 169 & 192) */}
            <Modal
                isOpen={!!selectedTemplate}
                onClose={() => setSelectedTemplate(null)}
                title={`Kustomisasi Template: ${selectedTemplate?.name}`}
                size="lg"
            >
                {selectedTemplate && (
                    <div className="space-y-5">
                        <div className="flex items-start gap-2.5 rounded-lg border border-blue-100 bg-blue-50 p-3 text-xs text-blue-900">
                            <Info className="mt-0.5 h-4 w-4 shrink-0 text-blue-600" />
                            <span>
                                Pertanyaan kustomisasi ini akan langsung
                                menyesuaikan kapasitas staf, jadwal buka, dan
                                layanan awal Anda secara otomatis.
                            </span>
                        </div>

                        {/* Customization Questions */}
                        {selectedTemplate.customization_questions && (
                            <div className="space-y-4">
                                {selectedTemplate.customization_questions.map(
                                    (q) => (
                                        <div key={q.key}>
                                            {q.type === 'number' && (
                                                <Input
                                                    label={q.label}
                                                    type="number"
                                                    value={
                                                        customizationValues[
                                                            q.key
                                                        ] ?? q.default
                                                    }
                                                    onChange={(e) =>
                                                        setCustomizationValues({
                                                            ...customizationValues,
                                                            [q.key]: parseInt(
                                                                e.target.value,
                                                                10
                                                            ),
                                                        })
                                                    }
                                                    min={q.min}
                                                    max={q.max}
                                                />
                                            )}

                                            {q.type === 'text' && (
                                                <Input
                                                    label={q.label}
                                                    value={
                                                        customizationValues[
                                                            q.key
                                                        ] ?? q.default
                                                    }
                                                    onChange={(e) =>
                                                        setCustomizationValues({
                                                            ...customizationValues,
                                                            [q.key]:
                                                                e.target.value,
                                                        })
                                                    }
                                                />
                                            )}

                                            {q.type === 'time' && (
                                                <Input
                                                    label={q.label}
                                                    type="time"
                                                    value={
                                                        customizationValues[
                                                            q.key
                                                        ] ?? q.default
                                                    }
                                                    onChange={(e) =>
                                                        setCustomizationValues({
                                                            ...customizationValues,
                                                            [q.key]:
                                                                e.target.value,
                                                        })
                                                    }
                                                />
                                            )}

                                            {q.type === 'boolean' && (
                                                <label className="flex cursor-pointer items-center gap-2.5 text-xs font-semibold text-slate-800">
                                                    <input
                                                        type="checkbox"
                                                        checked={
                                                            customizationValues[
                                                                q.key
                                                            ] ?? q.default
                                                        }
                                                        onChange={(e) =>
                                                            setCustomizationValues(
                                                                {
                                                                    ...customizationValues,
                                                                    [q.key]:
                                                                        e.target
                                                                            .checked,
                                                                }
                                                            )
                                                        }
                                                        className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                                    />
                                                    {q.label}
                                                </label>
                                            )}

                                            {q.type === 'select' &&
                                                q.options && (
                                                    <Select
                                                        label={q.label}
                                                        value={
                                                            customizationValues[
                                                                q.key
                                                            ] ?? q.default
                                                        }
                                                        onChange={(e) =>
                                                            setCustomizationValues(
                                                                {
                                                                    ...customizationValues,
                                                                    [q.key]:
                                                                        e.target
                                                                            .value,
                                                                }
                                                            )
                                                        }
                                                        options={Object.entries(
                                                            q.options
                                                        ).map(([val, lbl]) => ({
                                                            value: val,
                                                            label: lbl,
                                                        }))}
                                                    />
                                                )}
                                        </div>
                                    )
                                )}
                            </div>
                        )}

                        <div className="flex justify-end gap-2 border-t border-slate-100 pt-4">
                            <Button
                                variant="ghost"
                                onClick={() => setSelectedTemplate(null)}
                            >
                                Batal
                            </Button>
                            <Button
                                variant="primary"
                                onClick={handleInstallTemplate}
                                isLoading={isInstalling}
                                className="gap-2"
                            >
                                <span>Pasang Template Ini (DRAFT)</span>
                                <ArrowRight className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                )}
            </Modal>
        </div>
    );
};

export default function Onboarding(props: OnboardingProps) {
    return (
        <ToastProvider>
            <OnboardingContent {...props} />
        </ToastProvider>
    );
}
