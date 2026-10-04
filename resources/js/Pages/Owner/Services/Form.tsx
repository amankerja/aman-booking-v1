import { Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ChevronDown,
    ChevronUp,
    Clock,
    DollarSign,
    Layers,
    Plus,
    Save,
    Settings2,
    Trash2,
    Users,
} from 'lucide-react';
import React, { useState } from 'react';
import {
    Button,
    Input,
    Select,
    Textarea,
    useToast,
} from '../../../Components/ui';
import { OwnerLayout } from '../../../Layouts/OwnerLayout';

interface VariantFormItem {
    id?: number;
    name: string;
    price_idr: number;
    duration_minutes: number;
    order?: number;
    is_active?: boolean;
}

interface AddonFormItem {
    id?: number;
    name: string;
    price_idr: number;
    duration_minutes: number;
    order?: number;
    is_active?: boolean;
}

interface ServiceFormProps {
    mode: 'create' | 'edit';
    service?: {
        id: number;
        name: string;
        category_id: number | null;
        description: string;
        image_url: string | null;
        price_idr: number;
        duration_type: 'FIXED' | 'PER_QUANTITY' | 'PER_UNIT_SIZE' | 'VARIABLE';
        duration_minutes: number;
        duration_rule: Record<string, unknown>;
        buffer_before: number;
        buffer_after: number;
        capacity: number;
        is_active: boolean;
        is_featured: boolean;
        rules: Record<string, unknown>;
        variants: VariantFormItem[];
        addons: AddonFormItem[];
    } | null;
    categories: Array<{ id: number; name: string }>;
    canCreate: boolean;
    usage: {
        current: number;
        limit: number;
        remaining: number;
        is_unlimited: boolean;
    };
}

export default function ServiceForm({
    mode,
    service,
    categories,
    canCreate: _canCreate,
    usage: _usage,
}: ServiceFormProps) {
    const toast = useToast();
    const [isAdvancedOpen, setIsAdvancedOpen] = useState(false);

    const { data, setData, post, put, processing, errors } = useForm({
        name: service?.name || '',
        category_id: service?.category_id || '',
        description: service?.description || '',
        price_idr: service?.price_idr ?? 100000,
        duration_type: service?.duration_type || 'FIXED',
        duration_minutes: service?.duration_minutes ?? 60,
        duration_rule: service?.duration_rule || {
            unit_name: 'ruangan',
            minutes_per_unit: 30,
            min_minutes: 30,
            max_minutes: 180,
        },
        buffer_before: service?.buffer_before ?? 0,
        buffer_after: service?.buffer_after ?? 0,
        capacity: service?.capacity ?? 1,
        is_active: service?.is_active ?? true,
        is_featured: service?.is_featured ?? false,
        rules: service?.rules || {},
        variants: service?.variants || [],
        addons: service?.addons || [],
    });

    const handleAddVariant = () => {
        setData('variants', [
            ...data.variants,
            {
                name: 'Varian Baru',
                price_idr: Number(data.price_idr) || 100000,
                duration_minutes: Number(data.duration_minutes) || 60,
                is_active: true,
            },
        ]);
    };

    const handleRemoveVariant = (index: number) => {
        setData(
            'variants',
            data.variants.filter((_, idx) => idx !== index)
        );
    };

    const handleVariantChange = (
        index: number,
        field: keyof VariantFormItem,
        val: string | number | boolean
    ) => {
        const updated = [...data.variants];
        updated[index] = { ...updated[index], [field]: val };
        setData('variants', updated);
    };

    const handleAddAddon = () => {
        setData('addons', [
            ...data.addons,
            {
                name: 'Add-on Baru',
                price_idr: 25000,
                duration_minutes: 15,
                is_active: true,
            },
        ]);
    };

    const handleRemoveAddon = (index: number) => {
        setData(
            'addons',
            data.addons.filter((_, idx) => idx !== index)
        );
    };

    const handleAddonChange = (
        index: number,
        field: keyof AddonFormItem,
        val: string | number | boolean
    ) => {
        const updated = [...data.addons];
        updated[index] = { ...updated[index], [field]: val };
        setData('addons', updated);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (mode === 'create') {
            post(route('owner.services.store'), {
                onSuccess: () => {
                    toast.success('Layanan berhasil dibuat!');
                },
                onError: (err) => {
                    if (err.plan_limit) {
                        toast.error(err.plan_limit);
                    } else {
                        toast.error(
                            'Gagal menyimpan layanan. Periksa formulir.'
                        );
                    }
                },
            });
        } else if (service) {
            put(route('owner.services.update', service.id), {
                onSuccess: () => {
                    toast.success('Layanan berhasil diperbarui!');
                },
                onError: () => {
                    toast.error('Gagal memperbarui layanan. Periksa formulir.');
                },
            });
        }
    };

    return (
        <OwnerLayout
            title={
                mode === 'create'
                    ? 'Tambah Layanan Baru'
                    : `Edit Layanan: ${service?.name}`
            }
            subtitle="Konfigurasi harga, durasi, varian, add-on, dan kapasitas layanan."
        >
            <form
                onSubmit={handleSubmit}
                className="mx-auto max-w-4xl space-y-6"
            >
                {/* Header Back & Action Buttons */}
                <div className="flex items-center justify-between">
                    <Link
                        href={route('owner.services.index')}
                        className="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 transition-colors hover:text-slate-800"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        <span>Kembali ke Katalog Layanan</span>
                    </Link>

                    <div className="flex items-center gap-2">
                        <Link href={route('owner.services.index')}>
                            <Button variant="secondary" size="sm" type="button">
                                Batal
                            </Button>
                        </Link>
                        <Button
                            variant="primary"
                            size="sm"
                            type="submit"
                            isLoading={processing}
                            leftIcon={<Save className="h-4 w-4" />}
                        >
                            {mode === 'create'
                                ? 'Simpan Layanan'
                                : 'Perbarui Layanan'}
                        </Button>
                    </div>
                </div>

                {errors.plan_limit && (
                    <div className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-xs font-medium text-rose-700">
                        {errors.plan_limit}
                    </div>
                )}

                {/* 1. INFORMASI DASAR (BASIC INFO - PRD 194) */}
                <div className="space-y-4 rounded-[14px] border border-slate-200 bg-white p-6 shadow-xs">
                    <div className="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                            <Layers className="h-4 w-4" />
                        </div>
                        <div>
                            <h3 className="text-sm font-semibold text-slate-900">
                                1. Informasi Dasar Layanan
                            </h3>
                            <p className="text-xs text-slate-500">
                                Nama layanan, kategori, harga umum, dan
                                deskripsi singkat.
                            </p>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Input
                            label="Nama Layanan"
                            placeholder="cth: Potong Rambut Pria + Cuci"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            error={errors.name}
                            required
                        />

                        <Select
                            label="Kategori Layanan"
                            value={data.category_id}
                            onChange={(e) =>
                                setData('category_id', e.target.value)
                            }
                            error={errors.category_id}
                        >
                            <option value="">Pilih Kategori (Opsional)</option>
                            {categories.map((cat) => (
                                <option key={cat.id} value={cat.id}>
                                    {cat.name}
                                </option>
                            ))}
                        </Select>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Input
                            label="Harga Dasar (Rp)"
                            type="number"
                            min="0"
                            step="1000"
                            placeholder="100000"
                            value={data.price_idr}
                            onChange={(e) =>
                                setData('price_idr', Number(e.target.value))
                            }
                            error={errors.price_idr}
                            leftIcon={<DollarSign className="h-4 w-4" />}
                            required
                        />

                        <Input
                            label="Durasi Dasar (Menit)"
                            type="number"
                            min="5"
                            max="1440"
                            step="5"
                            placeholder="60"
                            value={data.duration_minutes}
                            onChange={(e) =>
                                setData(
                                    'duration_minutes',
                                    Number(e.target.value)
                                )
                            }
                            error={errors.duration_minutes}
                            leftIcon={<Clock className="h-4 w-4" />}
                            required
                        />
                    </div>

                    <Textarea
                        label="Deskripsi Layanan"
                        placeholder="Jelaskan detail apa saja yang didapat pelanggan pada layanan ini..."
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        error={errors.description}
                        rows={3}
                    />
                </div>

                {/* 2. MODEL DURASI FLEKSIBEL (PRD 123) */}
                <div className="space-y-4 rounded-[14px] border border-slate-200 bg-white p-6 shadow-xs">
                    <div className="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                            <Clock className="h-4 w-4" />
                        </div>
                        <div>
                            <h3 className="text-sm font-semibold text-slate-900">
                                2. Model Durasi Fleksibel
                            </h3>
                            <p className="text-xs text-slate-500">
                                Pilih bagaimana durasi slot dihitung di kalender
                                sistem.
                            </p>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-4">
                        {[
                            {
                                id: 'FIXED',
                                title: 'Durasi Tetap (Fixed)',
                                desc: 'Durasi selalu sama untuk setiap booking.',
                            },
                            {
                                id: 'PER_QUANTITY',
                                title: 'Per Quantity / Unit',
                                desc: 'Durasi bertambah kelipatan jumlah unit.',
                            },
                            {
                                id: 'PER_UNIT_SIZE',
                                title: 'Per Ukuran / Varian',
                                desc: 'Durasi berbeda tiap ukuran (S/M/L) varian.',
                            },
                            {
                                id: 'VARIABLE',
                                title: 'Rentang Variabel',
                                desc: 'Durasi fleksibel di antara batas min dan max.',
                            },
                        ].map((model) => (
                            <label
                                key={model.id}
                                className={`flex cursor-pointer flex-col justify-between rounded-xl border p-3.5 transition-all ${
                                    data.duration_type === model.id
                                        ? 'border-blue-600 bg-blue-50/40 ring-1 ring-blue-600'
                                        : 'border-slate-200 bg-white hover:border-slate-300'
                                }`}
                            >
                                <div className="space-y-1">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-semibold text-slate-900">
                                            {model.title}
                                        </span>
                                        <input
                                            type="radio"
                                            name="duration_type"
                                            value={model.id}
                                            checked={
                                                data.duration_type === model.id
                                            }
                                            onChange={() =>
                                                setData(
                                                    'duration_type',
                                                    model.id as
                                                        | 'FIXED'
                                                        | 'PER_QUANTITY'
                                                        | 'PER_UNIT_SIZE'
                                                        | 'VARIABLE'
                                                )
                                            }
                                            className="h-3.5 w-3.5 text-blue-600 focus:ring-blue-600"
                                        />
                                    </div>
                                    <p className="text-[11px] text-slate-500">
                                        {model.desc}
                                    </p>
                                </div>
                            </label>
                        ))}
                    </div>

                    {/* Mode Specific Settings */}
                    {data.duration_type === 'PER_QUANTITY' && (
                        <div className="space-y-3 rounded-lg border border-blue-100 bg-blue-50/40 p-4">
                            <span className="text-xs font-semibold text-slate-800">
                                Pengaturan Durasi Kelipatan Unit
                            </span>
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <Input
                                    label="Nama Satuan Unit"
                                    placeholder="cth: ruangan, mobil, orang"
                                    value={data.duration_rule?.unit_name || ''}
                                    onChange={(e) =>
                                        setData('duration_rule', {
                                            ...data.duration_rule,
                                            unit_name: e.target.value,
                                        })
                                    }
                                />
                                <Input
                                    label="Menit Tambahan per Unit"
                                    type="number"
                                    min="5"
                                    max="360"
                                    value={
                                        data.duration_rule?.minutes_per_unit ||
                                        30
                                    }
                                    onChange={(e) =>
                                        setData('duration_rule', {
                                            ...data.duration_rule,
                                            minutes_per_unit: Number(
                                                e.target.value
                                            ),
                                        })
                                    }
                                />
                            </div>
                        </div>
                    )}

                    {data.duration_type === 'VARIABLE' && (
                        <div className="space-y-3 rounded-lg border border-blue-100 bg-blue-50/40 p-4">
                            <span className="text-xs font-semibold text-slate-800">
                                Batas Rentang Durasi Variabel
                            </span>
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <Input
                                    label="Durasi Minimum (Menit)"
                                    type="number"
                                    min="15"
                                    max="720"
                                    value={
                                        data.duration_rule?.min_minutes || 30
                                    }
                                    onChange={(e) =>
                                        setData('duration_rule', {
                                            ...data.duration_rule,
                                            min_minutes: Number(e.target.value),
                                        })
                                    }
                                />
                                <Input
                                    label="Durasi Maksimum (Menit)"
                                    type="number"
                                    min="30"
                                    max="1440"
                                    value={
                                        data.duration_rule?.max_minutes || 180
                                    }
                                    onChange={(e) =>
                                        setData('duration_rule', {
                                            ...data.duration_rule,
                                            max_minutes: Number(e.target.value),
                                        })
                                    }
                                />
                            </div>
                        </div>
                    )}
                </div>

                {/* 3. VARIAN LAYANAN (VARIANTS) */}
                <div className="space-y-4 rounded-[14px] border border-slate-200 bg-white p-6 shadow-xs">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div className="flex items-center gap-2.5">
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                                <Layers className="h-4 w-4" />
                            </div>
                            <div>
                                <h3 className="text-sm font-semibold text-slate-900">
                                    3. Varian Layanan (Opsional)
                                </h3>
                                <p className="text-xs text-slate-500">
                                    Pilihan ukuran atau paket (cth: Rambut
                                    Pendek / Panjang, Mobil Kecil / SUV).
                                </p>
                            </div>
                        </div>
                        <Button
                            variant="secondary"
                            size="sm"
                            type="button"
                            onClick={handleAddVariant}
                            leftIcon={<Plus className="h-3.5 w-3.5" />}
                        >
                            Tambah Varian
                        </Button>
                    </div>

                    {data.variants.length === 0 ? (
                        <p className="py-2 text-xs text-slate-400">
                            Belum ada varian. Layanan akan menggunakan harga dan
                            durasi dasar di atas.
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {data.variants.map((v, vIdx) => (
                                <div
                                    key={vIdx}
                                    className="flex flex-col gap-3 rounded-lg border border-slate-200 bg-slate-50/50 p-3 sm:flex-row sm:items-center"
                                >
                                    <div className="flex-1">
                                        <Input
                                            placeholder="Nama Varian (cth: Rambut Panjang)"
                                            value={v.name}
                                            onChange={(e) =>
                                                handleVariantChange(
                                                    vIdx,
                                                    'name',
                                                    e.target.value
                                                )
                                            }
                                            required
                                        />
                                    </div>
                                    <div className="w-full sm:w-44">
                                        <Input
                                            type="number"
                                            placeholder="Harga Rp"
                                            value={v.price_idr}
                                            onChange={(e) =>
                                                handleVariantChange(
                                                    vIdx,
                                                    'price_idr',
                                                    Number(e.target.value)
                                                )
                                            }
                                            leftIcon={
                                                <DollarSign className="h-3.5 w-3.5" />
                                            }
                                            required
                                        />
                                    </div>
                                    <div className="w-full sm:w-36">
                                        <Input
                                            type="number"
                                            placeholder="Durasi (Mnt)"
                                            value={v.duration_minutes}
                                            onChange={(e) =>
                                                handleVariantChange(
                                                    vIdx,
                                                    'duration_minutes',
                                                    Number(e.target.value)
                                                )
                                            }
                                            leftIcon={
                                                <Clock className="h-3.5 w-3.5" />
                                            }
                                            required
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            handleRemoveVariant(vIdx)
                                        }
                                        className="rounded p-2 text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600"
                                        title="Hapus Varian"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* 4. ADD-ON TAMBAHAN (ADDONS) */}
                <div className="space-y-4 rounded-[14px] border border-slate-200 bg-white p-6 shadow-xs">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div className="flex items-center gap-2.5">
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                                <Plus className="h-4 w-4" />
                            </div>
                            <div>
                                <h3 className="text-sm font-semibold text-slate-900">
                                    4. Layanan Tambahan (Add-on)
                                </h3>
                                <p className="text-xs text-slate-500">
                                    Opsi ekstra yang dapat ditambahkan pelanggan
                                    saat pemesanan (cth: Cuci Blow, Waxing).
                                </p>
                            </div>
                        </div>
                        <Button
                            variant="secondary"
                            size="sm"
                            type="button"
                            onClick={handleAddAddon}
                            leftIcon={<Plus className="h-3.5 w-3.5" />}
                        >
                            Tambah Add-on
                        </Button>
                    </div>

                    {data.addons.length === 0 ? (
                        <p className="py-2 text-xs text-slate-400">
                            Belum ada add-on. Anda dapat menambahkan opsi
                            pelengkap untuk meningkatkan nilai transaksi.
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {data.addons.map((a, aIdx) => (
                                <div
                                    key={aIdx}
                                    className="flex flex-col gap-3 rounded-lg border border-slate-200 bg-slate-50/50 p-3 sm:flex-row sm:items-center"
                                >
                                    <div className="flex-1">
                                        <Input
                                            placeholder="Nama Add-on (cth: Vitamin Rambut)"
                                            value={a.name}
                                            onChange={(e) =>
                                                handleAddonChange(
                                                    aIdx,
                                                    'name',
                                                    e.target.value
                                                )
                                            }
                                            required
                                        />
                                    </div>
                                    <div className="w-full sm:w-44">
                                        <Input
                                            type="number"
                                            placeholder="Harga Ekstra Rp"
                                            value={a.price_idr}
                                            onChange={(e) =>
                                                handleAddonChange(
                                                    aIdx,
                                                    'price_idr',
                                                    Number(e.target.value)
                                                )
                                            }
                                            leftIcon={
                                                <DollarSign className="h-3.5 w-3.5" />
                                            }
                                            required
                                        />
                                    </div>
                                    <div className="w-full sm:w-36">
                                        <Input
                                            type="number"
                                            placeholder="Tambahan (Mnt)"
                                            value={a.duration_minutes}
                                            onChange={(e) =>
                                                handleAddonChange(
                                                    aIdx,
                                                    'duration_minutes',
                                                    Number(e.target.value)
                                                )
                                            }
                                            leftIcon={
                                                <Clock className="h-3.5 w-3.5" />
                                            }
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => handleRemoveAddon(aIdx)}
                                        className="rounded p-2 text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600"
                                        title="Hapus Add-on"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* 5. ADVANCED SETTINGS (COLLAPSIBLE - PRD 194) */}
                <div className="overflow-hidden rounded-[14px] border border-slate-200 bg-white shadow-xs">
                    <button
                        type="button"
                        onClick={() => setIsAdvancedOpen(!isAdvancedOpen)}
                        className="flex w-full items-center justify-between p-5 text-left transition-colors hover:bg-slate-50"
                    >
                        <div className="flex items-center gap-2.5">
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                                <Settings2 className="h-4 w-4" />
                            </div>
                            <div>
                                <h3 className="text-sm font-semibold text-slate-900">
                                    5. Pengaturan Lanjutan (Advanced Settings)
                                </h3>
                                <p className="text-xs text-slate-500">
                                    Buffer waktu sebelum/sesudah, batas
                                    kapasitas pelanggan, dan aturan booking.
                                </p>
                            </div>
                        </div>
                        <div className="text-slate-400">
                            {isAdvancedOpen ? (
                                <ChevronUp className="h-5 w-5" />
                            ) : (
                                <ChevronDown className="h-5 w-5" />
                            )}
                        </div>
                    </button>

                    {isAdvancedOpen && (
                        <div className="space-y-5 border-t border-slate-100 bg-slate-50/30 p-6">
                            {/* Buffer Time (PRD 124) */}
                            <div className="space-y-3">
                                <span className="text-xs font-semibold text-slate-800">
                                    Buffer Waktu (Waktu Persiapan & Pembersihan)
                                </span>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <Input
                                        label="Buffer Persiapan Sebelum Layanan (Menit)"
                                        type="number"
                                        min="0"
                                        max="240"
                                        value={data.buffer_before}
                                        onChange={(e) =>
                                            setData(
                                                'buffer_before',
                                                Number(e.target.value)
                                            )
                                        }
                                        error={errors.buffer_before}
                                    />
                                    <Input
                                        label="Buffer Pembersihan Sesudah Layanan (Menit)"
                                        type="number"
                                        min="0"
                                        max="240"
                                        value={data.buffer_after}
                                        onChange={(e) =>
                                            setData(
                                                'buffer_after',
                                                Number(e.target.value)
                                            )
                                        }
                                        error={errors.buffer_after}
                                    />
                                </div>
                            </div>

                            {/* Kapasitas (PRD 134) */}
                            <div className="space-y-3">
                                <span className="text-xs font-semibold text-slate-800">
                                    Kapasitas Slot Pelanggan
                                </span>
                                <div className="max-w-xs">
                                    <Input
                                        label="Maksimum Pelanggan per Sesi Booking"
                                        type="number"
                                        min="1"
                                        max="500"
                                        value={data.capacity}
                                        onChange={(e) =>
                                            setData(
                                                'capacity',
                                                Number(e.target.value)
                                            )
                                        }
                                        error={errors.capacity}
                                        leftIcon={<Users className="h-4 w-4" />}
                                        helperText="Isi 1 untuk appointment privat (cth: salon), atau > 1 untuk kelas / workshop."
                                    />
                                </div>
                            </div>

                            {/* Status & Visibilitas */}
                            <div className="space-y-3 pt-2">
                                <span className="text-xs font-semibold text-slate-800">
                                    Visibilitas & Status
                                </span>
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                    <label className="flex cursor-pointer items-center gap-2.5 rounded-lg border border-slate-200 bg-white p-3">
                                        <input
                                            type="checkbox"
                                            checked={data.is_active}
                                            onChange={(e) =>
                                                setData(
                                                    'is_active',
                                                    e.target.checked
                                                )
                                            }
                                            className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-600"
                                        />
                                        <span className="text-xs font-medium text-slate-800">
                                            Layanan Aktif (Bisa Dipesan)
                                        </span>
                                    </label>

                                    <label className="flex cursor-pointer items-center gap-2.5 rounded-lg border border-slate-200 bg-white p-3">
                                        <input
                                            type="checkbox"
                                            checked={data.is_featured}
                                            onChange={(e) =>
                                                setData(
                                                    'is_featured',
                                                    e.target.checked
                                                )
                                            }
                                            className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-600"
                                        />
                                        <span className="text-xs font-medium text-slate-800">
                                            Tandai sebagai Layanan Unggulan
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                {/* Submit Action footer */}
                <div className="flex items-center justify-end gap-2 pt-2">
                    <Link href={route('owner.services.index')}>
                        <Button variant="secondary" size="sm" type="button">
                            Batal
                        </Button>
                    </Link>
                    <Button
                        variant="primary"
                        size="sm"
                        type="submit"
                        isLoading={processing}
                        leftIcon={<Save className="h-4 w-4" />}
                    >
                        {mode === 'create'
                            ? 'Simpan Layanan'
                            : 'Perbarui Layanan'}
                    </Button>
                </div>
            </form>
        </OwnerLayout>
    );
}
