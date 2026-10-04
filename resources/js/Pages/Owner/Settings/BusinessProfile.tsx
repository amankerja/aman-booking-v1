import { useForm, router } from '@inertiajs/react';
import {
    Building2,
    Check,
    Globe,
    Image,
    Mail,
    Phone,
    Shield,
    Trash2,
    Upload,
} from 'lucide-react';
import React, { useState } from 'react';
import {
    Button,
    Input,
    Select,
    Tabs,
    Textarea,
    useToast,
} from '../../../Components/ui';
import { OwnerLayout } from '../../../Layouts/OwnerLayout';

interface BusinessData {
    id: number;
    name: string;
    slug: string;
    logo_url: string | null;
    whatsapp: string;
    phone: string;
    email: string;
    address: string;
    city: string;
    province: string;
    postal_code: string;
    timezone: string;
    description: string;
    policies: {
        booking_policy: string;
        cancellation_policy: string;
        refund_policy: string;
        reschedule_policy: string;
    };
    booking_rules: {
        min_advance_minutes: number;
        max_advance_days: number;
        allow_same_day: boolean;
        cancellation_deadline_hours: number;
        reschedule_deadline_hours: number;
        max_reschedule_times: number;
    };
    social_links: {
        instagram: string;
        facebook: string;
        website: string;
        tiktok: string;
    };
}

interface BusinessProfileProps {
    business: BusinessData;
    timezones: Array<{ value: string; label: string }>;
}

export default function BusinessProfile({
    business,
    timezones,
}: BusinessProfileProps) {
    const toast = useToast();
    const [activeTab, setActiveTab] = useState('profile');
    const [logoFile, setLogoFile] = useState<File | null>(null);
    const [logoPreview, setLogoPreview] = useState<string | null>(
        business.logo_url
    );
    const [isUploadingLogo, setIsUploadingLogo] = useState(false);

    const { data, setData, put, processing, errors } = useForm({
        name: business.name,
        slug: business.slug,
        timezone: business.timezone,
        whatsapp: business.whatsapp,
        phone: business.phone,
        email: business.email,
        address: business.address,
        city: business.city,
        province: business.province,
        postal_code: business.postal_code,
        description: business.description,
        policies: {
            booking_policy: business.policies?.booking_policy || '',
            cancellation_policy: business.policies?.cancellation_policy || '',
            refund_policy: business.policies?.refund_policy || '',
            reschedule_policy: business.policies?.reschedule_policy || '',
        },
        booking_rules: {
            min_advance_minutes:
                business.booking_rules?.min_advance_minutes ?? 120,
            max_advance_days: business.booking_rules?.max_advance_days ?? 30,
            allow_same_day: business.booking_rules?.allow_same_day ?? true,
            cancellation_deadline_hours:
                business.booking_rules?.cancellation_deadline_hours ?? 24,
            reschedule_deadline_hours:
                business.booking_rules?.reschedule_deadline_hours ?? 12,
            max_reschedule_times:
                business.booking_rules?.max_reschedule_times ?? 2,
        },
        social_links: {
            instagram: business.social_links?.instagram || '',
            facebook: business.social_links?.facebook || '',
            website: business.social_links?.website || '',
            tiktok: business.social_links?.tiktok || '',
        },
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put('/app/settings/business', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    'Pengaturan profil dan aturan bisnis berhasil disimpan.'
                );
            },
            onError: () => {
                toast.error(
                    'Gagal menyimpan. Periksa kembali form isian Anda.'
                );
            },
        });
    };

    const handleLogoSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setLogoFile(file);
            setLogoPreview(URL.createObjectURL(file));
        }
    };

    const handleUploadLogo = () => {
        if (!logoFile) return;

        setIsUploadingLogo(true);
        const formData = new FormData();
        formData.append('logo', logoFile);

        router.post('/app/settings/business/logo', formData, {
            preserveScroll: true,
            onSuccess: () => {
                setIsUploadingLogo(false);
                setLogoFile(null);
                toast.success('Logo bisnis berhasil diunggah.');
            },
            onError: () => {
                setIsUploadingLogo(false);
                toast.error(
                    'Gagal mengunggah logo. Pastikan file gambar berukuran maks 2MB.'
                );
            },
        });
    };

    const handleRemoveLogo = () => {
        router.delete('/app/settings/business/logo', {
            preserveScroll: true,
            onSuccess: () => {
                setLogoPreview(null);
                setLogoFile(null);
                toast.success('Logo bisnis berhasil dihapus.');
            },
        });
    };

    return (
        <OwnerLayout
            title="Profil & Pengaturan Bisnis"
            breadcrumbs={[
                { label: 'Workspace', href: '/app/dashboard' },
                { label: 'Profil Bisnis' },
            ]}
        >
            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-base font-bold text-slate-900">
                            Profil & Pengaturan Usaha
                        </h1>
                        <p className="text-xs text-slate-500">
                            Kelola identitas usaha, kontak, zona waktu, serta
                            aturan reservasi dan kebijakan pembatalan.
                        </p>
                    </div>

                    <Button
                        variant="primary"
                        onClick={handleSubmit}
                        isLoading={processing}
                        leftIcon={<Check className="h-3.5 w-3.5" />}
                    >
                        Simpan Semua Pengaturan
                    </Button>
                </div>

                {/* Tabs */}
                <Tabs
                    tabs={[
                        {
                            id: 'profile',
                            label: 'Profil & Kontak',
                            icon: <Building2 className="h-3.5 w-3.5" />,
                        },
                        {
                            id: 'rules',
                            label: 'Aturan & Kebijakan',
                            icon: <Shield className="h-3.5 w-3.5" />,
                        },
                        {
                            id: 'social',
                            label: 'Tautan Sosial',
                            icon: <Globe className="h-3.5 w-3.5" />,
                        },
                    ]}
                    activeTab={activeTab}
                    onChange={setActiveTab}
                    variant="line"
                />

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* TAB 1: Profile & Logo */}
                    {activeTab === 'profile' && (
                        <div className="space-y-6">
                            {/* Logo Card */}
                            <div className="rounded-[12px] border border-slate-200 bg-white p-5">
                                <h3 className="mb-4 text-xs font-bold tracking-wider text-slate-900 uppercase">
                                    Logo Bisnis
                                </h3>

                                <div className="flex flex-col gap-5 sm:flex-row sm:items-center">
                                    <div className="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                                        {logoPreview ? (
                                            <img
                                                src={logoPreview}
                                                alt="Logo Preview"
                                                className="h-full w-full object-cover"
                                            />
                                        ) : (
                                            <Image className="h-8 w-8 text-slate-400" />
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <label className="cursor-pointer">
                                                <input
                                                    type="file"
                                                    accept="image/png,image/jpeg,image/webp"
                                                    onChange={handleLogoSelect}
                                                    className="sr-only"
                                                />
                                                <span className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50">
                                                    <Upload className="h-3.5 w-3.5 text-slate-500" />
                                                    Pilih Gambar
                                                </span>
                                            </label>

                                            {logoFile && (
                                                <Button
                                                    type="button"
                                                    variant="primary"
                                                    size="sm"
                                                    onClick={handleUploadLogo}
                                                    isLoading={isUploadingLogo}
                                                >
                                                    Unggah Sekarang
                                                </Button>
                                            )}

                                            {logoPreview && (
                                                <Button
                                                    type="button"
                                                    variant="danger"
                                                    size="sm"
                                                    onClick={handleRemoveLogo}
                                                    leftIcon={
                                                        <Trash2 className="h-3 w-3" />
                                                    }
                                                >
                                                    Hapus Logo
                                                </Button>
                                            )}
                                        </div>
                                        <p className="text-[11px] text-slate-400">
                                            Format yang didukung: PNG, JPG, atau
                                            WebP. Maksimal 2MB.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* Basic Details Card */}
                            <div className="space-y-4 rounded-[12px] border border-slate-200 bg-white p-5">
                                <h3 className="border-b border-slate-100 pb-3 text-xs font-bold tracking-wider text-slate-900 uppercase">
                                    Informasi Dasar
                                </h3>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <Input
                                        label="Nama Usaha / Brand"
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                        error={errors.name}
                                        required
                                    />

                                    <Input
                                        label="Slug URL Publik"
                                        value={data.slug}
                                        onChange={(e) =>
                                            setData('slug', e.target.value)
                                        }
                                        helperText={`Tautan publik: /b/${data.slug || 'slug-anda'}`}
                                        error={errors.slug}
                                        required
                                    />

                                    <Select
                                        label="Zona Waktu Operasional"
                                        value={data.timezone}
                                        onChange={(e) =>
                                            setData('timezone', e.target.value)
                                        }
                                        options={timezones}
                                        helperText="Semua booking dan slot jadwal akan dikonversi ke zona waktu ini."
                                        error={errors.timezone}
                                        required
                                    />

                                    <Input
                                        label="Email Resmi Usaha"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) =>
                                            setData('email', e.target.value)
                                        }
                                        error={errors.email}
                                        leftIcon={
                                            <Mail className="h-3.5 w-3.5" />
                                        }
                                    />
                                </div>

                                <div className="grid grid-cols-1 gap-4 pt-2 sm:grid-cols-2">
                                    <Input
                                        label="Nomor WhatsApp (Notifikasi)"
                                        value={data.whatsapp}
                                        onChange={(e) =>
                                            setData('whatsapp', e.target.value)
                                        }
                                        helperText="Format internasional, e.g. 628123456789"
                                        error={errors.whatsapp}
                                        leftIcon={
                                            <Phone className="h-3.5 w-3.5" />
                                        }
                                    />

                                    <Input
                                        label="Nomor Telepon Kantor"
                                        value={data.phone}
                                        onChange={(e) =>
                                            setData('phone', e.target.value)
                                        }
                                        error={errors.phone}
                                    />
                                </div>

                                <div className="pt-2">
                                    <Textarea
                                        label="Deskripsi Usaha"
                                        value={data.description}
                                        onChange={(e) =>
                                            setData(
                                                'description',
                                                e.target.value
                                            )
                                        }
                                        placeholder="Jelaskan spesialisasi bisnis dan keunggulan layanan Anda..."
                                        rows={3}
                                        maxLength={1000}
                                        showCount
                                        error={errors.description}
                                    />
                                </div>
                            </div>

                            {/* Address Card */}
                            <div className="space-y-4 rounded-[12px] border border-slate-200 bg-white p-5">
                                <h3 className="border-b border-slate-100 pb-3 text-xs font-bold tracking-wider text-slate-900 uppercase">
                                    Alamat & Lokasi Fisik
                                </h3>

                                <div className="space-y-4">
                                    <Input
                                        label="Alamat Lengkap"
                                        value={data.address}
                                        onChange={(e) =>
                                            setData('address', e.target.value)
                                        }
                                        placeholder="Jl. Sudirman No. 123, Blok A..."
                                        error={errors.address}
                                    />

                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                        <Input
                                            label="Kota / Kabupaten"
                                            value={data.city}
                                            onChange={(e) =>
                                                setData('city', e.target.value)
                                            }
                                            error={errors.city}
                                        />

                                        <Input
                                            label="Provinsi"
                                            value={data.province}
                                            onChange={(e) =>
                                                setData(
                                                    'province',
                                                    e.target.value
                                                )
                                            }
                                            error={errors.province}
                                        />

                                        <Input
                                            label="Kode Pos"
                                            value={data.postal_code}
                                            onChange={(e) =>
                                                setData(
                                                    'postal_code',
                                                    e.target.value
                                                )
                                            }
                                            error={errors.postal_code}
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* TAB 2: Rules & Policies */}
                    {activeTab === 'rules' && (
                        <div className="space-y-6">
                            {/* Booking Rules */}
                            <div className="space-y-4 rounded-[12px] border border-slate-200 bg-white p-5">
                                <h3 className="border-b border-slate-100 pb-3 text-xs font-bold tracking-wider text-slate-900 uppercase">
                                    Aturan Waktu Reservasi (Booking Horizon)
                                </h3>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <Input
                                        label="Pemberitahuan Minimum (Menit)"
                                        type="number"
                                        value={
                                            data.booking_rules
                                                .min_advance_minutes
                                        }
                                        onChange={(e) =>
                                            setData('booking_rules', {
                                                ...data.booking_rules,
                                                min_advance_minutes:
                                                    parseInt(e.target.value) ||
                                                    0,
                                            })
                                        }
                                        helperText="Contoh: 120 menit (minimal 2 jam sebelum waktu booking)."
                                        min={0}
                                    />

                                    <Input
                                        label="Batas Maksimum Booking (Hari)"
                                        type="number"
                                        value={
                                            data.booking_rules.max_advance_days
                                        }
                                        onChange={(e) =>
                                            setData('booking_rules', {
                                                ...data.booking_rules,
                                                max_advance_days:
                                                    parseInt(e.target.value) ||
                                                    30,
                                            })
                                        }
                                        helperText="Berapa hari ke depan pelanggan dapat melihat slot."
                                        min={1}
                                        max={365}
                                    />

                                    <Input
                                        label="Batas Reschedule Maksimal"
                                        type="number"
                                        value={
                                            data.booking_rules
                                                .max_reschedule_times
                                        }
                                        onChange={(e) =>
                                            setData('booking_rules', {
                                                ...data.booking_rules,
                                                max_reschedule_times:
                                                    parseInt(e.target.value) ||
                                                    0,
                                            })
                                        }
                                        helperText="Berapa kali pelanggan diizinkan mengubah jadwal."
                                        min={0}
                                        max={10}
                                    />
                                </div>

                                <div className="grid grid-cols-1 gap-4 pt-2 sm:grid-cols-2">
                                    <Input
                                        label="Batas Waktu Pembatalan (Jam sebelum)"
                                        type="number"
                                        value={
                                            data.booking_rules
                                                .cancellation_deadline_hours
                                        }
                                        onChange={(e) =>
                                            setData('booking_rules', {
                                                ...data.booking_rules,
                                                cancellation_deadline_hours:
                                                    parseInt(e.target.value) ||
                                                    0,
                                            })
                                        }
                                        helperText="Contoh: 24 jam sebelum slot dimulai."
                                    />

                                    <Input
                                        label="Batas Waktu Reschedule (Jam sebelum)"
                                        type="number"
                                        value={
                                            data.booking_rules
                                                .reschedule_deadline_hours
                                        }
                                        onChange={(e) =>
                                            setData('booking_rules', {
                                                ...data.booking_rules,
                                                reschedule_deadline_hours:
                                                    parseInt(e.target.value) ||
                                                    0,
                                            })
                                        }
                                        helperText="Contoh: 12 jam sebelum slot dimulai."
                                    />
                                </div>
                            </div>

                            {/* Policies Text */}
                            <div className="space-y-4 rounded-[12px] border border-slate-200 bg-white p-5">
                                <h3 className="border-b border-slate-100 pb-3 text-xs font-bold tracking-wider text-slate-900 uppercase">
                                    Teks Kebijakan & Syarat Ketentuan
                                </h3>

                                <div className="space-y-4">
                                    <Textarea
                                        label="Kebijakan Reservasi"
                                        value={data.policies.booking_policy}
                                        onChange={(e) =>
                                            setData('policies', {
                                                ...data.policies,
                                                booking_policy: e.target.value,
                                            })
                                        }
                                        placeholder="Syarat kedatangan, toleransi keterlambatan..."
                                        rows={3}
                                    />

                                    <Textarea
                                        label="Kebijakan Pembatalan (Cancellation Policy)"
                                        value={
                                            data.policies.cancellation_policy
                                        }
                                        onChange={(e) =>
                                            setData('policies', {
                                                ...data.policies,
                                                cancellation_policy:
                                                    e.target.value,
                                            })
                                        }
                                        placeholder="Ketentuan biaya hangus atau no-show..."
                                        rows={3}
                                    />

                                    <Textarea
                                        label="Kebijakan Pengembalian Dana (Refund Policy)"
                                        value={data.policies.refund_policy}
                                        onChange={(e) =>
                                            setData('policies', {
                                                ...data.policies,
                                                refund_policy: e.target.value,
                                            })
                                        }
                                        placeholder="Ketentuan proses pengembalian DP atau pembayaran..."
                                        rows={3}
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    {/* TAB 3: Social Links */}
                    {activeTab === 'social' && (
                        <div className="space-y-4 rounded-[12px] border border-slate-200 bg-white p-5">
                            <h3 className="border-b border-slate-100 pb-3 text-xs font-bold tracking-wider text-slate-900 uppercase">
                                Tautan Media Sosial & Website
                            </h3>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Input
                                    label="Instagram URL / Handle"
                                    value={data.social_links.instagram}
                                    onChange={(e) =>
                                        setData('social_links', {
                                            ...data.social_links,
                                            instagram: e.target.value,
                                        })
                                    }
                                    placeholder="https://instagram.com/namabisnis"
                                />

                                <Input
                                    label="Website Resmi"
                                    value={data.social_links.website}
                                    onChange={(e) =>
                                        setData('social_links', {
                                            ...data.social_links,
                                            website: e.target.value,
                                        })
                                    }
                                    placeholder="https://namabisnis.com"
                                />

                                <Input
                                    label="Facebook Page"
                                    value={data.social_links.facebook}
                                    onChange={(e) =>
                                        setData('social_links', {
                                            ...data.social_links,
                                            facebook: e.target.value,
                                        })
                                    }
                                    placeholder="https://facebook.com/namabisnis"
                                />

                                <Input
                                    label="TikTok"
                                    value={data.social_links.tiktok}
                                    onChange={(e) =>
                                        setData('social_links', {
                                            ...data.social_links,
                                            tiktok: e.target.value,
                                        })
                                    }
                                    placeholder="https://tiktok.com/@namabisnis"
                                />
                            </div>
                        </div>
                    )}

                    {/* Form Action Footer */}
                    <div className="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
                        <Button
                            type="submit"
                            variant="primary"
                            isLoading={processing}
                            leftIcon={<Check className="h-4 w-4" />}
                        >
                            Simpan Perubahan
                        </Button>
                    </div>
                </form>
            </div>
        </OwnerLayout>
    );
}
