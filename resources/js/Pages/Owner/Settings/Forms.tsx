import { router, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Edit3,
    Eye,
    FileText,
    Layers,
    Plus,
    Sparkles,
    Trash2,
    X,
} from 'lucide-react';
import React, { useMemo, useState } from 'react';
import {
    Badge,
    Button,
    Input,
    Modal,
    Textarea,
    useToast,
} from '../../../Components/ui';
import { OwnerLayout } from '../../../Layouts/OwnerLayout';
import { CustomFormFieldInput } from '../../Public/CustomFormFieldInput';

export interface FormOption {
    label: string;
    value: string;
}

export interface FormVisibilityCondition {
    field: string;
    operator: string;
    value: unknown;
}

export interface FormFieldItem {
    id: number;
    tenant_id: number;
    form_id: number;
    field_key: string;
    type: string;
    label: string;
    placeholder?: string | null;
    help_text?: string | null;
    is_required: boolean;
    default_value?: string | null;
    options?: FormOption[] | null;
    validation_rules?: Record<string, unknown> | null;
    visibility_conditions?: FormVisibilityCondition[] | FormVisibilityCondition | null;
    sort_order: number;
    is_active: boolean;
}

export interface BookingFormItem {
    id: number;
    tenant_id: number;
    service_id?: number | null;
    name: string;
    slug: string;
    description?: string | null;
    is_default: boolean;
    is_active: boolean;
    service?: { id: number; name: string } | null;
    fields: FormFieldItem[];
}

export interface BusinessPresetItem {
    key: string;
    label: string;
    description: string;
}

export interface ServiceOption {
    id: number;
    name: string;
}

export interface FormsPageProps {
    forms: BookingFormItem[];
    services: ServiceOption[];
    presets: BusinessPresetItem[];
}

const FIELD_TYPES = [
    { value: 'text', label: 'Teks Pendek (Text)' },
    { value: 'textarea', label: 'Teks Panjang (Textarea)' },
    { value: 'number', label: 'Angka (Number)' },
    { value: 'currency', label: 'Nominal Rupiah (Currency)' },
    { value: 'phone', label: 'Nomor Telepon / WA (Phone)' },
    { value: 'email', label: 'Email' },
    { value: 'date', label: 'Tanggal (Date)' },
    { value: 'time', label: 'Waktu / Jam (Time)' },
    { value: 'select', label: 'Pilihan Tunggal Dropdown (Select)' },
    { value: 'radio', label: 'Pilihan Radio (Radio Button)' },
    { value: 'checkbox', label: 'Centang Tunggal (Checkbox)' },
    { value: 'multiselect', label: 'Pilihan Ganda (Multi-select)' },
    { value: 'file', label: 'Unggah Berkas / Foto (File)' },
    { value: 'address', label: 'Alamat Pengiriman (Address)' },
];

export default function FormsPage({
    forms,
    services,
    presets,
}: FormsPageProps) {
    const toast = useToast();

    // Active selected form tab
    const [selectedFormId, setSelectedFormId] = useState<number>(
        forms[0]?.id || 0
    );
    const activeForm = useMemo(() => {
        return forms.find((f) => f.id === selectedFormId) || forms[0] || null;
    }, [forms, selectedFormId]);

    // Modals state
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [editingForm, setEditingForm] = useState<BookingFormItem | null>(null);

    const [isFieldModalOpen, setIsFieldModalOpen] = useState(false);
    const [editingField, setEditingField] = useState<FormFieldItem | null>(null);

    const [isPresetModalOpen, setIsPresetModalOpen] = useState(false);
    const [selectedPresetKey, setSelectedPresetKey] = useState<string>('rental');

    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<{
        type: 'form' | 'field';
        id: number;
        name: string;
        formId?: number;
    } | null>(null);

    // Interactive Preview State
    const [previewValues, setPreviewValues] = useState<Record<string, unknown>>({});
    const [previewFiles, setPreviewFiles] = useState<Record<string, File | null>>({});

    // Form Modal Form State
    const formForm = useForm({
        name: '',
        description: '',
        service_id: '' as string | number,
        is_default: false,
        is_active: true,
    });

    // Field Modal Form State
    const fieldForm = useForm({
        field_key: '',
        type: 'text',
        label: '',
        placeholder: '',
        help_text: '',
        is_required: false,
        default_value: '',
        options: [] as FormOption[],
        has_condition: false,
        condition_field: '',
        condition_operator: 'eq',
        condition_value: '',
        is_active: true,
    });

    // Helper: evaluate visibility in preview
    const isFieldVisibleInPreview = (
        field: FormFieldItem,
        values: Record<string, unknown>
    ): boolean => {
        const conditions = field.visibility_conditions;
        if (!conditions) return true;
        const ruleList = Array.isArray(conditions) ? conditions : [conditions];
        if (ruleList.length === 0) return true;

        for (const rule of ruleList) {
            const actual = values[rule.field];
            const expected = rule.value;
            let pass = false;
            switch (rule.operator || 'eq') {
                case 'eq':
                    pass = String(actual ?? '') === String(expected ?? '');
                    break;
                case 'neq':
                    pass = String(actual ?? '') !== String(expected ?? '');
                    break;
                case 'filled':
                    pass = actual !== undefined && actual !== null && actual !== '';
                    break;
                case 'empty':
                    pass = actual === undefined || actual === null || actual === '';
                    break;
                default:
                    pass = String(actual ?? '') === String(expected ?? '');
                    break;
            }
            if (!pass) return false;
        }
        return true;
    };

    // Open Form Create/Edit
    const handleOpenFormModal = (item?: BookingFormItem) => {
        if (item) {
            setEditingForm(item);
            formForm.setData({
                name: item.name,
                description: item.description || '',
                service_id: item.service_id || '',
                is_default: item.is_default,
                is_active: item.is_active,
            });
        } else {
            setEditingForm(null);
            formForm.setData({
                name: '',
                description: '',
                service_id: '',
                is_default: false,
                is_active: true,
            });
        }
        setIsFormModalOpen(true);
    };

    // Submit Form Create/Edit
    const handleSaveForm = (e: React.FormEvent) => {
        e.preventDefault();
        const payload = {
            name: formForm.data.name,
            description: formForm.data.description || null,
            service_id: formForm.data.service_id
                ? Number(formForm.data.service_id)
                : null,
            is_default: Boolean(formForm.data.is_default),
            is_active: Boolean(formForm.data.is_active),
        };

        if (editingForm) {
            router.put(`/app/settings/forms/${editingForm.id}`, payload, {
                onSuccess: () => {
                    setIsFormModalOpen(false);
                    toast.success('Formulir berhasil diperbarui.');
                },
                onError: (errs) => {
                    toast.error(
                        Object.values(errs)[0] as string ||
                            'Gagal memperbarui formulir.'
                    );
                },
            });
        } else {
            router.post('/app/settings/forms', payload, {
                onSuccess: () => {
                    setIsFormModalOpen(false);
                    toast.success('Formulir baru berhasil dibuat.');
                },
                onError: (errs) => {
                    toast.error(
                        Object.values(errs)[0] as string ||
                            'Gagal membuat formulir.'
                    );
                },
            });
        }
    };

    // Open Field Create/Edit
    const handleOpenFieldModal = (item?: FormFieldItem) => {
        if (item) {
            setEditingField(item);
            const condition = Array.isArray(item.visibility_conditions)
                ? item.visibility_conditions[0]
                : item.visibility_conditions;
            fieldForm.setData({
                field_key: item.field_key,
                type: item.type,
                label: item.label,
                placeholder: item.placeholder || '',
                help_text: item.help_text || '',
                is_required: item.is_required,
                default_value: item.default_value || '',
                options: item.options ? [...item.options] : [],
                has_condition: Boolean(condition),
                condition_field: condition?.field || '',
                condition_operator: condition?.operator || 'eq',
                condition_value: String(condition?.value ?? ''),
                is_active: item.is_active,
            });
        } else {
            setEditingField(null);
            fieldForm.setData({
                field_key: '',
                type: 'text',
                label: '',
                placeholder: '',
                help_text: '',
                is_required: false,
                default_value: '',
                options: [
                    { label: 'Opsi 1', value: 'opsi_1' },
                    { label: 'Opsi 2', value: 'opsi_2' },
                ],
                has_condition: false,
                condition_field: '',
                condition_operator: 'eq',
                condition_value: '',
                is_active: true,
            });
        }
        setIsFieldModalOpen(true);
    };

    // Auto-generate key from label
    const handleLabelChange = (val: string) => {
        fieldForm.setData('label', val);
        if (!editingField) {
            const slugKey = val
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '')
                .slice(0, 32);
            fieldForm.setData('field_key', slugKey);
        }
    };

    // Save Field
    const handleSaveField = (e: React.FormEvent) => {
        e.preventDefault();
        if (!activeForm) return;

        let visibility_conditions: FormVisibilityCondition | null = null;
        if (fieldForm.data.has_condition && fieldForm.data.condition_field) {
            visibility_conditions = {
                field: fieldForm.data.condition_field,
                operator: fieldForm.data.condition_operator,
                value: fieldForm.data.condition_value,
            };
        }

        const payload = {
            field_key: fieldForm.data.field_key,
            type: fieldForm.data.type,
            label: fieldForm.data.label,
            placeholder: fieldForm.data.placeholder || null,
            help_text: fieldForm.data.help_text || null,
            is_required: Boolean(fieldForm.data.is_required),
            default_value: fieldForm.data.default_value || null,
            options: ['select', 'radio', 'multiselect'].includes(fieldForm.data.type)
                ? fieldForm.data.options
                : null,
            visibility_conditions,
            is_active: Boolean(fieldForm.data.is_active),
        };

        if (editingField) {
            router.put(
                `/app/settings/forms/${activeForm.id}/fields/${editingField.id}`,
                payload,
                {
                    onSuccess: () => {
                        setIsFieldModalOpen(false);
                        toast.success('Kolom formulir berhasil diperbarui.');
                    },
                    onError: (errs) => {
                        toast.error(
                            Object.values(errs)[0] as string ||
                                'Gagal menyimpan kolom formulir.'
                        );
                    },
                }
            );
        } else {
            router.post(
                `/app/settings/forms/${activeForm.id}/fields`,
                payload,
                {
                    onSuccess: () => {
                        setIsFieldModalOpen(false);
                        toast.success('Kolom formulir berhasil ditambahkan.');
                    },
                    onError: (errs) => {
                        toast.error(
                            Object.values(errs)[0] as string ||
                                'Gagal menambahkan kolom formulir.'
                        );
                    },
                }
            );
        }
    };

    // Delete confirmed
    const handleConfirmDelete = () => {
        if (!deleteTarget) return;

        if (deleteTarget.type === 'form') {
            router.delete(`/app/settings/forms/${deleteTarget.id}`, {
                onSuccess: () => {
                    setIsDeleteModalOpen(false);
                    toast.success('Formulir berhasil dihapus.');
                },
                onError: () => toast.error('Gagal menghapus formulir.'),
            });
        } else if (deleteTarget.type === 'field' && deleteTarget.formId) {
            router.delete(
                `/app/settings/forms/${deleteTarget.formId}/fields/${deleteTarget.id}`,
                {
                    onSuccess: () => {
                        setIsDeleteModalOpen(false);
                        toast.success('Kolom formulir berhasil dihapus.');
                    },
                    onError: () => toast.error('Gagal menghapus kolom.'),
                }
            );
        }
    };

    // Reorder fields
    const handleMoveField = (index: number, direction: 'up' | 'down') => {
        if (!activeForm) return;
        const newFields = [...activeForm.fields];
        const targetIndex = direction === 'up' ? index - 1 : index + 1;
        if (targetIndex < 0 || targetIndex >= newFields.length) return;

        const temp = newFields[index];
        newFields[index] = newFields[targetIndex];
        newFields[targetIndex] = temp;

        const reordered = newFields.map((f, idx) => ({
            id: f.id,
            sort_order: idx + 1,
        }));

        router.post(
            `/app/settings/forms/${activeForm.id}/reorder`,
            { fields: reordered },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Urutan kolom diperbarui.'),
            }
        );
    };

    // Install Preset Template
    const handleInstallPreset = () => {
        router.post(
            '/app/settings/forms/install-preset',
            { preset: selectedPresetKey },
            {
                onSuccess: () => {
                    setIsPresetModalOpen(false);
                    toast.success('Template formulir berhasil dipasang.');
                },
                onError: () => toast.error('Gagal memasang template formulir.'),
            }
        );
    };

    return (
        <OwnerLayout
            title="Formulir Pemesanan"
            subtitle="Pengaturan Form Builder & Logika Kondisional (PRD 26, 27, 179)"
        >
            <div className="space-y-6">
                {/* Header Actions */}
                <div className="flex flex-col justify-between gap-4 rounded-[14px] border border-slate-200 bg-white p-5 sm:flex-row sm:items-center">
                    <div>
                        <div className="flex items-center gap-2">
                            <h2 className="text-base font-bold text-slate-900">
                                Dynamic Form Builder & Conditional Rules
                            </h2>
                            <Badge variant="blue">PRD 26, 27</Badge>
                        </div>
                        <p className="mt-1 text-xs text-slate-500">
                            Bangun kolom formulir fleksibel, dokumen KTP/SIM,
                            serta logika otomatis tampil/sembunyi saat pemesanan.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setIsPresetModalOpen(true)}
                            className="gap-1.5 text-xs text-slate-700"
                        >
                            <Sparkles className="h-3.5 w-3.5 text-blue-600" />
                            Gunakan Template Bisnis
                        </Button>
                        <Button
                            variant="primary"
                            size="sm"
                            onClick={() => handleOpenFormModal()}
                            className="gap-1.5 text-xs"
                        >
                            <Plus className="h-3.5 w-3.5" />
                            Buat Formulir Baru
                        </Button>
                    </div>
                </div>

                {/* Form Selector Tabs */}
                <div className="flex scrollbar-thin items-center gap-2 overflow-x-auto border-b border-slate-200 pb-2">
                    {forms.map((f) => {
                        const isSelected = f.id === activeForm?.id;
                        return (
                            <button
                                key={f.id}
                                type="button"
                                onClick={() => setSelectedFormId(f.id)}
                                className={`flex items-center gap-2 rounded-[8px] border px-3.5 py-2 text-xs font-semibold whitespace-nowrap transition-all ${
                                    isSelected
                                        ? 'border-blue-600 bg-blue-50/60 text-blue-700 shadow-xs'
                                        : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50'
                                }`}
                            >
                                <FileText
                                    className={`h-3.5 w-3.5 ${
                                        isSelected
                                            ? 'text-blue-600'
                                            : 'text-slate-400'
                                    }`}
                                />
                                <span>{f.name}</span>
                                {f.is_default && (
                                    <span className="rounded-full bg-blue-100 px-1.5 py-0.5 text-[9px] font-bold text-blue-800">
                                        Default
                                    </span>
                                )}
                                {f.service && (
                                    <span className="rounded-full bg-slate-100 px-1.5 py-0.5 text-[9px] font-medium text-slate-600">
                                        {f.service.name}
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </div>

                {activeForm && (
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                        {/* Left / Main: Fields Manager */}
                        <div className="space-y-4 lg:col-span-7">
                            <div className="rounded-[14px] border border-slate-200 bg-white p-5">
                                {/* Form Info Header */}
                                <div className="flex flex-col justify-between gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-center">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <h3 className="text-base font-bold text-slate-900">
                                                {activeForm.name}
                                            </h3>
                                            {activeForm.is_default && (
                                                <Badge variant="blue">
                                                    Default Tenant
                                                </Badge>
                                            )}
                                            {!activeForm.is_active && (
                                                <Badge variant="gray">
                                                    Nonaktif
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="mt-0.5 text-xs text-slate-500">
                                            {activeForm.description ||
                                                'Tidak ada keterangan deskripsi formulir.'}
                                        </p>
                                        <div className="mt-2 text-[11px] text-slate-400">
                                            Target Layanan:{' '}
                                            <strong className="text-slate-700">
                                                {activeForm.service
                                                    ? activeForm.service.name
                                                    : 'Semua Layanan (Default)'}
                                            </strong>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-1.5">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                handleOpenFormModal(activeForm)
                                            }
                                            className="h-8 text-xs text-slate-700"
                                        >
                                            <Edit3 className="mr-1 h-3 w-3" />
                                            Edit Form
                                        </Button>
                                        {forms.length > 1 && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => {
                                                    setDeleteTarget({
                                                        type: 'form',
                                                        id: activeForm.id,
                                                        name: activeForm.name,
                                                    });
                                                    setIsDeleteModalOpen(true);
                                                }}
                                                className="h-8 text-xs text-rose-600 hover:bg-rose-50"
                                            >
                                                <Trash2 className="h-3 w-3" />
                                            </Button>
                                        )}
                                    </div>
                                </div>

                                {/* Fields Header */}
                                <div className="mt-4 flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Layers className="h-4 w-4 text-slate-500" />
                                        <h4 className="text-xs font-bold tracking-wider text-slate-700 uppercase">
                                            Daftar Kolom ({activeForm.fields.length})
                                        </h4>
                                    </div>
                                    <Button
                                        variant="primary"
                                        size="sm"
                                        onClick={() => handleOpenFieldModal()}
                                        className="gap-1 text-xs"
                                    >
                                        <Plus className="h-3 w-3" />
                                        Tambah Kolom
                                    </Button>
                                </div>

                                {/* Fields List Table */}
                                <div className="mt-3 space-y-2">
                                    {activeForm.fields.length === 0 ? (
                                        <div className="rounded-[10px] border border-dashed border-slate-200 bg-slate-50/50 p-6 text-center text-xs text-slate-500">
                                            <p className="font-semibold text-slate-700">
                                                Belum Ada Kolom Kustom
                                            </p>
                                            <p className="mt-1">
                                                Formulir ini belum memiliki kolom
                                                kustom. Klik tombol "Tambah
                                                Kolom" di atas.
                                            </p>
                                        </div>
                                    ) : (
                                        activeForm.fields.map(
                                            (field, index) => {
                                                const condition = Array.isArray(
                                                    field.visibility_conditions
                                                )
                                                    ? field
                                                          .visibility_conditions[0]
                                                    : field.visibility_conditions;

                                                return (
                                                    <div
                                                        key={field.id}
                                                        className="flex items-center justify-between rounded-[10px] border border-slate-200 bg-white p-3 transition-colors hover:border-slate-300"
                                                    >
                                                        <div className="flex items-center gap-3">
                                                            {/* Sort arrows */}
                                                            <div className="flex flex-col gap-0.5">
                                                                <button
                                                                    type="button"
                                                                    disabled={
                                                                        index === 0
                                                                    }
                                                                    onClick={() =>
                                                                        handleMoveField(
                                                                            index,
                                                                            'up'
                                                                        )
                                                                    }
                                                                    className="rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30"
                                                                >
                                                                    <ArrowUp className="h-3 w-3" />
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    disabled={
                                                                        index ===
                                                                        activeForm
                                                                            .fields
                                                                            .length -
                                                                            1
                                                                    }
                                                                    onClick={() =>
                                                                        handleMoveField(
                                                                            index,
                                                                            'down'
                                                                        )
                                                                    }
                                                                    className="rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30"
                                                                >
                                                                    <ArrowDown className="h-3 w-3" />
                                                                </button>
                                                            </div>

                                                            {/* Field Info */}
                                                            <div>
                                                                <div className="flex items-center gap-2">
                                                                    <span className="text-xs font-bold text-slate-900">
                                                                        {field.label}
                                                                    </span>
                                                                    {field.is_required && (
                                                                        <span className="rounded bg-rose-100 px-1.5 py-0.2 text-[9px] font-bold text-rose-800">
                                                                            Wajib
                                                                        </span>
                                                                    )}
                                                                    <span className="font-mono text-[10px] text-slate-400">
                                                                        ({field.field_key})
                                                                    </span>
                                                                </div>

                                                                <div className="mt-1 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500">
                                                                    <span className="rounded bg-slate-100 px-1.5 py-0.5 font-medium text-slate-700">
                                                                        Tipe: {field.type}
                                                                    </span>
                                                                    {condition && (
                                                                        <span className="rounded border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800">
                                                                            Jika {condition.field} {condition.operator} {condition.value}
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </div>

                                                        {/* Actions */}
                                                        <div className="flex items-center gap-1">
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() =>
                                                                    handleOpenFieldModal(
                                                                        field
                                                                    )
                                                                }
                                                                className="h-7 px-2 text-xs"
                                                            >
                                                                <Edit3 className="h-3 w-3 text-slate-600" />
                                                            </Button>
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() => {
                                                                    setDeleteTarget({
                                                                        type: 'field',
                                                                        id: field.id,
                                                                        name: field.label,
                                                                        formId: activeForm.id,
                                                                    });
                                                                    setIsDeleteModalOpen(true);
                                                                }}
                                                                className="h-7 px-2 text-xs text-rose-600 hover:bg-rose-50"
                                                            >
                                                                <Trash2 className="h-3 w-3" />
                                                            </Button>
                                                        </div>
                                                    </div>
                                                );
                                            }
                                        )
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Right: Live Interactive Preview */}
                        <div className="space-y-4 lg:col-span-5">
                            <div className="sticky top-6 rounded-[14px] border border-slate-200 bg-white p-5 shadow-xs">
                                <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                                    <div className="flex items-center gap-2">
                                        <Eye className="h-4 w-4 text-blue-600" />
                                        <h3 className="text-xs font-bold tracking-wider text-slate-900 uppercase">
                                            Live Preview Form
                                        </h3>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setPreviewValues({});
                                            setPreviewFiles({});
                                        }}
                                        className="text-[11px] font-semibold text-blue-600 hover:underline"
                                    >
                                        Reset Preview
                                    </button>
                                </div>
                                <p className="mt-2 text-[11px] text-slate-400">
                                    Simulasi langsung seperti yang dilihat oleh
                                    customer di halaman booking. Coba ubah
                                    jawaban untuk menguji kemunculan kolom
                                    bersyarat.
                                </p>

                                <div className="mt-4 space-y-4 rounded-[10px] border border-slate-100 bg-slate-50/50 p-4">
                                    {activeForm.fields.length === 0 ? (
                                        <div className="py-6 text-center text-xs text-slate-400">
                                            Tidak ada kolom untuk dipratinjau.
                                        </div>
                                    ) : (
                                        activeForm.fields
                                            .filter(
                                                (f) =>
                                                    f.is_active &&
                                                    isFieldVisibleInPreview(
                                                        f,
                                                        previewValues
                                                    )
                                            )
                                            .map((field) => (
                                                <CustomFormFieldInput
                                                    key={field.id}
                                                    field={field}
                                                    value={
                                                        previewValues[
                                                            field.field_key
                                                        ]
                                                    }
                                                    fileValue={
                                                        previewFiles[
                                                            field.field_key
                                                        ]
                                                    }
                                                    onChange={(key, val) =>
                                                        setPreviewValues(
                                                            (prev) => ({
                                                                ...prev,
                                                                [key]: val,
                                                            })
                                                        )
                                                    }
                                                    onFileChange={(key, file) =>
                                                        setPreviewFiles(
                                                            (prev) => ({
                                                                ...prev,
                                                                [key]: file,
                                                            })
                                                        )
                                                    }
                                                />
                                            ))
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* Modal: Form Create / Edit */}
            <Modal
                isOpen={isFormModalOpen}
                onClose={() => setIsFormModalOpen(false)}
                title={editingForm ? 'Edit Formulir' : 'Buat Formulir Baru'}
                description="Konfigurasikan judul formulir dan pengikatan ke layanan spesifik."
                size="md"
            >
                <form onSubmit={handleSaveForm} className="space-y-4 text-xs">
                    <div>
                        <label className="mb-1 block font-semibold text-slate-700">
                            Nama Formulir <span className="text-red-500">*</span>
                        </label>
                        <Input
                            type="text"
                            required
                            value={formForm.data.name}
                            onChange={(e) =>
                                formForm.setData('name', e.target.value)
                            }
                            placeholder="Contoh: Formulir Rental Mobil Lepas Kunci"
                        />
                    </div>

                    <div>
                        <label className="mb-1 block font-semibold text-slate-700">
                            Target Layanan (Opsional)
                        </label>
                        <select
                            value={formForm.data.service_id}
                            onChange={(e) =>
                                formForm.setData('service_id', e.target.value)
                            }
                            className="w-full rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:outline-hidden"
                        >
                            <option value="">
                                -- Berlaku untuk Semua Layanan (Default) --
                            </option>
                            {services.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.name}
                                </option>
                            ))}
                        </select>
                        <span className="mt-1 block text-[10px] text-slate-400">
                            Bila dipilih, formulir ini hanya muncul saat customer
                            memilih layanan tersebut.
                        </span>
                    </div>

                    <div>
                        <label className="mb-1 block font-semibold text-slate-700">
                            Deskripsi Formulir
                        </label>
                        <Textarea
                            rows={2}
                            value={formForm.data.description}
                            onChange={(e) =>
                                formForm.setData('description', e.target.value)
                            }
                            placeholder="Penjelasan singkat instruksi pengisian..."
                        />
                    </div>

                    <div className="space-y-2 border-t border-slate-100 pt-3">
                        <label className="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                checked={formForm.data.is_default}
                                onChange={(e) =>
                                    formForm.setData(
                                        'is_default',
                                        e.target.checked
                                    )
                                }
                                className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span className="font-semibold text-slate-700">
                                Jadikan Formulir Default Tenant
                            </span>
                        </label>

                        <label className="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                checked={formForm.data.is_active}
                                onChange={(e) =>
                                    formForm.setData(
                                        'is_active',
                                        e.target.checked
                                    )
                                }
                                className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span className="font-semibold text-slate-700">
                                Aktifkan Formulir Ini
                            </span>
                        </label>
                    </div>

                    <div className="flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setIsFormModalOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant="primary"
                            isLoading={formForm.processing}
                        >
                            Simpan Formulir
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Modal: Field Create / Edit */}
            <Modal
                isOpen={isFieldModalOpen}
                onClose={() => setIsFieldModalOpen(false)}
                title={editingField ? 'Edit Kolom Formulir' : 'Tambah Kolom Baru'}
                description="Tentukan jenis input, validasi, dan logika kondisional kemunculannya."
                size="lg"
            >
                <form onSubmit={handleSaveField} className="space-y-4 text-xs">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block font-semibold text-slate-700">
                                Label Kolom <span className="text-red-500">*</span>
                            </label>
                            <Input
                                type="text"
                                required
                                value={fieldForm.data.label}
                                onChange={(e) => handleLabelChange(e.target.value)}
                                placeholder="Contoh: Nomor Plat Kendaraan"
                            />
                        </div>

                        <div>
                            <label className="mb-1 block font-semibold text-slate-700">
                                Field Key (ID Unik Database){' '}
                                <span className="text-red-500">*</span>
                            </label>
                            <Input
                                type="text"
                                required
                                disabled={Boolean(editingField)}
                                value={fieldForm.data.field_key}
                                onChange={(e) =>
                                    fieldForm.setData(
                                        'field_key',
                                        e.target.value
                                    )
                                }
                                placeholder="plate_number"
                                className="font-mono text-xs"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block font-semibold text-slate-700">
                                Tipe Input <span className="text-red-500">*</span>
                            </label>
                            <select
                                value={fieldForm.data.type}
                                onChange={(e) =>
                                    fieldForm.setData('type', e.target.value)
                                }
                                className="w-full rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:outline-hidden"
                            >
                                {FIELD_TYPES.map((t) => (
                                    <option key={t.value} value={t.value}>
                                        {t.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="mb-1 block font-semibold text-slate-700">
                                Placeholder
                            </label>
                            <Input
                                type="text"
                                value={fieldForm.data.placeholder}
                                onChange={(e) =>
                                    fieldForm.setData(
                                        'placeholder',
                                        e.target.value
                                    )
                                }
                                placeholder="Contoh: Masukkan nomor plat..."
                            />
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block font-semibold text-slate-700">
                            Bantuan / Help Text
                        </label>
                        <Input
                            type="text"
                            value={fieldForm.data.help_text}
                            onChange={(e) =>
                                fieldForm.setData('help_text', e.target.value)
                            }
                            placeholder="Contoh: Format berkas JPG, PNG, atau PDF (Maks. 5 MB)"
                        />
                    </div>

                    {/* Options Builder for select, radio, multiselect */}
                    {['select', 'radio', 'multiselect'].includes(
                        fieldForm.data.type
                    ) && (
                        <div className="rounded-[10px] border border-slate-200 bg-slate-50/50 p-3">
                            <div className="mb-2 flex items-center justify-between">
                                <span className="font-semibold text-slate-800">
                                    Daftar Opsi Pilihan
                                </span>
                                <button
                                    type="button"
                                    onClick={() =>
                                        fieldForm.setData('options', [
                                            ...fieldForm.data.options,
                                            {
                                                label: `Opsi ${fieldForm.data.options.length + 1}`,
                                                value: `opsi_${fieldForm.data.options.length + 1}`,
                                            },
                                        ])
                                    }
                                    className="text-[11px] font-bold text-blue-600 hover:underline"
                                >
                                    + Tambah Opsi
                                </button>
                            </div>
                            <div className="space-y-2">
                                {fieldForm.data.options.map((opt, i) => (
                                    <div
                                        key={i}
                                        className="flex items-center gap-2"
                                    >
                                        <Input
                                            type="text"
                                            value={opt.label}
                                            onChange={(e) => {
                                                const updated = [
                                                    ...fieldForm.data.options,
                                                ];
                                                updated[i].label = e.target.value;
                                                fieldForm.setData(
                                                    'options',
                                                    updated
                                                );
                                            }}
                                            placeholder="Label Opsi"
                                            className="h-8 text-xs"
                                        />
                                        <Input
                                            type="text"
                                            value={opt.value}
                                            onChange={(e) => {
                                                const updated = [
                                                    ...fieldForm.data.options,
                                                ];
                                                updated[i].value = e.target.value;
                                                fieldForm.setData(
                                                    'options',
                                                    updated
                                                );
                                            }}
                                            placeholder="Value"
                                            className="h-8 font-mono text-xs"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => {
                                                const updated =
                                                    fieldForm.data.options.filter(
                                                        (_, idx) => idx !== i
                                                    );
                                                fieldForm.setData(
                                                    'options',
                                                    updated
                                                );
                                            }}
                                            className="text-slate-400 hover:text-rose-600"
                                        >
                                            <X className="h-4 w-4" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Conditional Rules Section (PRD 26, 27) */}
                    <div className="rounded-[10px] border border-slate-200 bg-slate-50/50 p-3">
                        <label className="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                checked={fieldForm.data.has_condition}
                                onChange={(e) =>
                                    fieldForm.setData(
                                        'has_condition',
                                        e.target.checked
                                    )
                                }
                                className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span className="font-semibold text-slate-800">
                                Gunakan Aturan Kondisional (Show / Hide)
                            </span>
                        </label>
                        <p className="mt-1 text-[11px] text-slate-500">
                            Kolom ini hanya akan muncul jika jawaban dari kolom
                            lain memenuhi syarat yang ditentukan.
                        </p>

                        {fieldForm.data.has_condition && (
                            <div className="mt-3 grid grid-cols-1 gap-2 border-t border-slate-200/60 pt-3 sm:grid-cols-3">
                                <div>
                                    <label className="mb-1 block text-[11px] font-semibold text-slate-600">
                                        Bergantung Pada Kolom:
                                    </label>
                                    <select
                                        value={fieldForm.data.condition_field}
                                        onChange={(e) =>
                                            fieldForm.setData(
                                                'condition_field',
                                                e.target.value
                                            )
                                        }
                                        className="w-full rounded-[6px] border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-900"
                                    >
                                        <option value="">-- Pilih Kolom --</option>
                                        {activeForm?.fields
                                            .filter(
                                                (f) =>
                                                    f.id !== editingField?.id
                                            )
                                            .map((f) => (
                                                <option
                                                    key={f.id}
                                                    value={f.field_key}
                                                >
                                                    {f.label} ({f.field_key})
                                                </option>
                                            ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="mb-1 block text-[11px] font-semibold text-slate-600">
                                        Operator:
                                    </label>
                                    <select
                                        value={fieldForm.data.condition_operator}
                                        onChange={(e) =>
                                            fieldForm.setData(
                                                'condition_operator',
                                                e.target.value
                                            )
                                        }
                                        className="w-full rounded-[6px] border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-900"
                                    >
                                        <option value="eq">Sama Dengan (=)</option>
                                        <option value="neq">Tidak Sama Dengan (!=)</option>
                                        <option value="filled">Diisi / Tidak Kosong</option>
                                        <option value="empty">Kosong</option>
                                    </select>
                                </div>

                                <div>
                                    <label className="mb-1 block text-[11px] font-semibold text-slate-600">
                                        Nilai yang Diharapkan:
                                    </label>
                                    <Input
                                        type="text"
                                        value={fieldForm.data.condition_value}
                                        onChange={(e) =>
                                            fieldForm.setData(
                                                'condition_value',
                                                e.target.value
                                            )
                                        }
                                        placeholder="Misal: hotel / yes"
                                        className="h-8 text-xs"
                                    />
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="flex items-center gap-4 border-t border-slate-100 pt-3">
                        <label className="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                checked={fieldForm.data.is_required}
                                onChange={(e) =>
                                    fieldForm.setData(
                                        'is_required',
                                        e.target.checked
                                    )
                                }
                                className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span className="font-semibold text-slate-700">
                                Wajib Diisi (Required)
                            </span>
                        </label>

                        <label className="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                checked={fieldForm.data.is_active}
                                onChange={(e) =>
                                    fieldForm.setData(
                                        'is_active',
                                        e.target.checked
                                    )
                                }
                                className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span className="font-semibold text-slate-700">
                                Aktif
                            </span>
                        </label>
                    </div>

                    <div className="flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setIsFieldModalOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant="primary"
                            isLoading={fieldForm.processing}
                        >
                            Simpan Kolom
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Modal: Install Business Preset */}
            <Modal
                isOpen={isPresetModalOpen}
                onClose={() => setIsPresetModalOpen(false)}
                title="Pasang Template Bisnis Siap Pakai"
                description="Pilih template industri Anda untuk menambahkan paket formulir standar lengkap dengan kolom kondisional."
                size="md"
            >
                <div className="space-y-3 text-xs">
                    <div className="space-y-2">
                        {presets.map((preset) => (
                            <label
                                key={preset.key}
                                className={`flex cursor-pointer items-start gap-3 rounded-[10px] border p-3 transition-all ${
                                    selectedPresetKey === preset.key
                                        ? 'border-blue-600 bg-blue-50/50 shadow-xs'
                                        : 'border-slate-200 bg-white hover:border-slate-300'
                                }`}
                            >
                                <input
                                    type="radio"
                                    name="preset"
                                    value={preset.key}
                                    checked={selectedPresetKey === preset.key}
                                    onChange={() =>
                                        setSelectedPresetKey(preset.key)
                                    }
                                    className="mt-0.5 h-4 w-4 text-blue-600 focus:ring-blue-500"
                                />
                                <div>
                                    <span className="block font-bold text-slate-900">
                                        {preset.label}
                                    </span>
                                    <span className="mt-0.5 block text-slate-500">
                                        {preset.description}
                                    </span>
                                </div>
                            </label>
                        ))}
                    </div>

                    <div className="flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setIsPresetModalOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            variant="primary"
                            onClick={handleInstallPreset}
                        >
                            Terapkan Template
                        </Button>
                    </div>
                </div>
            </Modal>

            {/* Modal: Confirm Delete */}
            <Modal
                isOpen={isDeleteModalOpen}
                onClose={() => setIsDeleteModalOpen(false)}
                title={`Hapus ${deleteTarget?.type === 'form' ? 'Formulir' : 'Kolom'}`}
                description={`Apakah Anda yakin ingin menghapus "${deleteTarget?.name}"? Tindakan ini tidak dapat dibatalkan.`}
                size="sm"
            >
                <div className="flex items-center justify-end gap-2 text-xs">
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={() => setIsDeleteModalOpen(false)}
                    >
                        Batal
                    </Button>
                    <Button
                        type="button"
                        variant="danger"
                        onClick={handleConfirmDelete}
                    >
                        Hapus Sekarang
                    </Button>
                </div>
            </Modal>
        </OwnerLayout>
    );
}
