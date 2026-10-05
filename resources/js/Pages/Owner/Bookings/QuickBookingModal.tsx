import {
    Calendar,
    Check,
    Clock,
    DollarSign,
    FileText,
    Loader2,
    Plus,
    Search,
    User,
    UserPlus,
} from 'lucide-react';
import React, { useEffect, useMemo, useState } from 'react';
import {
    Button,
    Input,
    Modal,
    Textarea,
    useToast,
} from '../../../Components/ui';
import { CustomFormFieldInput } from '../../Public/CustomFormFieldInput';
import {
    CustomerSummary,
    FormFieldItem,
    FormItem,
    ResourceSummary,
    ServiceSummary,
    SlotItem,
} from './types';

interface QuickBookingModalProps {
    isOpen: boolean;
    onClose: () => void;
    services: ServiceSummary[];
    resources: ResourceSummary[];
    customers: CustomerSummary[];
    forms?: FormItem[];
    onBookingCreated: () => void;
}

export const QuickBookingModal: React.FC<QuickBookingModalProps> = ({
    isOpen,
    onClose,
    services,
    resources,
    customers,
    forms = [],
    onBookingCreated,
}) => {
    const toast = useToast();

    // Mode: existing vs new customer
    const [isNewCustomer, setIsNewCustomer] = useState(false);
    const [selectedCustomerId, setSelectedCustomerId] = useState<string>('');
    const [customerSearch, setCustomerSearch] = useState('');
    const [newCustomer, setNewCustomer] = useState({
        name: '',
        phone: '',
        email: '',
    });

    // Booking selections
    const [selectedServiceId, setSelectedServiceId] = useState<string>('');
    const [selectedDate, setSelectedDate] = useState<string>(
        new Date().toISOString().split('T')[0]
    );
    const [selectedStaffId, setSelectedStaffId] = useState<string>('');
    const [selectedSlot, setSelectedSlot] = useState<SlotItem | null>(null);
    const [paymentStatus, setPaymentStatus] = useState<
        'UNPAID' | 'PAID' | 'PARTIAL'
    >('UNPAID');
    const [notes, setNotes] = useState('');

    // Custom form fields state (PRD 3.2)
    const [customFieldValues, setCustomFieldValues] = useState<
        Record<string, unknown>
    >({});
    const [customFieldFiles, setCustomFieldFiles] = useState<
        Record<string, File | null>
    >({});

    // Slots state from backend AvailabilityService
    const [slots, setSlots] = useState<SlotItem[]>([]);
    const [isLoadingSlots, setIsLoadingSlots] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Resolve active form for selected service
    const activeForm = useMemo(() => {
        if (!forms || forms.length === 0 || !selectedServiceId) return null;
        const numServiceId = Number(selectedServiceId);
        return (
            forms.find((f) => f.service_id === numServiceId) ||
            forms.find((f) => !f.service_id && f.is_default) ||
            forms.find((f) => !f.service_id) ||
            null
        );
    }, [forms, selectedServiceId]);

    // Visibility condition evaluator (PRD 3.2)
    const isFieldVisible = (field: FormFieldItem): boolean => {
        if (!field.visibility_conditions) {
            return true;
        }
        const conditions = Array.isArray(field.visibility_conditions)
            ? field.visibility_conditions
            : [field.visibility_conditions];

        if (conditions.length === 0) return true;

        return conditions.every((cond) => {
            const targetKey = cond.field || cond.field_key;
            if (!targetKey) return true;
            const actualVal = customFieldValues[targetKey];
            const targetVal = cond.value;
            const op = cond.operator || 'eq';

            switch (op) {
                case 'eq':
                case 'equals':
                    return String(actualVal ?? '') === String(targetVal ?? '');
                case 'neq':
                case 'not_equals':
                    return String(actualVal ?? '') !== String(targetVal ?? '');
                case 'contains':
                    return String(actualVal ?? '')
                        .toLowerCase()
                        .includes(String(targetVal ?? '').toLowerCase());
                case 'filled':
                case 'is_not_empty':
                    return (
                        actualVal !== undefined &&
                        actualVal !== null &&
                        actualVal !== ''
                    );
                case 'empty':
                case 'is_empty':
                    return (
                        actualVal === undefined ||
                        actualVal === null ||
                        actualVal === ''
                    );
                default:
                    return String(actualVal ?? '') === String(targetVal ?? '');
            }
        });
    };

    // Initialize first active service if none selected
    useEffect(() => {
        if (isOpen && services.length > 0 && !selectedServiceId) {
            setSelectedServiceId(String(services[0].id));
        }
    }, [isOpen, services, selectedServiceId]);

    // Reset custom fields when service changes
    useEffect(() => {
        setCustomFieldValues({});
        setCustomFieldFiles({});
    }, [selectedServiceId]);

    // Query slots via AvailabilityService whenever service, date, or staff changes (PRD 157, 158)
    useEffect(() => {
        if (!isOpen || !selectedServiceId || !selectedDate) {
            setSlots([]);
            setSelectedSlot(null);
            return;
        }

        let isCancelled = false;
        const fetchSlots = async () => {
            setIsLoadingSlots(true);
            try {
                const params = new URLSearchParams({
                    service_id: selectedServiceId,
                    date: selectedDate,
                });
                if (selectedStaffId) {
                    params.append('staff_id', selectedStaffId);
                }

                const res = await fetch(
                    `/app/bookings/slots?${params.toString()}`,
                    {
                        headers: { Accept: 'application/json' },
                    }
                );
                if (res.ok && !isCancelled) {
                    const data = await res.json();
                    setSlots(data.slots || []);
                    setSelectedSlot(null);
                }
            } catch {
                if (!isCancelled) {
                    toast.error('Gagal memuat ketersediaan slot.');
                }
            } finally {
                if (!isCancelled) {
                    setIsLoadingSlots(false);
                }
            }
        };

        fetchSlots();

        return () => {
            isCancelled = true;
        };
    }, [isOpen, selectedServiceId, selectedDate, selectedStaffId, toast]);

    const filteredCustomers = customers.filter(
        (c) =>
            c.name.toLowerCase().includes(customerSearch.toLowerCase()) ||
            c.phone_e164.includes(customerSearch) ||
            (c.email &&
                c.email.toLowerCase().includes(customerSearch.toLowerCase()))
    );

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!selectedSlot) {
            toast.error('Pilih jam slot yang tersedia.');
            return;
        }

        if (!isNewCustomer && !selectedCustomerId) {
            toast.error('Pilih customer atau isi data customer baru.');
            return;
        }

        if (
            isNewCustomer &&
            (!newCustomer.name.trim() || !newCustomer.phone.trim())
        ) {
            toast.error('Nama dan nomor telepon customer wajib diisi.');
            return;
        }

        // Validate visible required custom fields (PRD 3.2)
        if (activeForm && activeForm.fields) {
            for (const f of activeForm.fields) {
                if (
                    (f.is_active ?? true) &&
                    isFieldVisible(f) &&
                    f.is_required
                ) {
                    if (f.type === 'file') {
                        if (!customFieldFiles[f.field_key]) {
                            toast.error(
                                `Dokumen/berkas '${f.label}' wajib diunggah.`
                            );
                            return;
                        }
                    } else {
                        const val = customFieldValues[f.field_key];
                        if (
                            val === undefined ||
                            val === null ||
                            val === '' ||
                            (Array.isArray(val) && val.length === 0)
                        ) {
                            toast.error(`Kolom '${f.label}' wajib diisi.`);
                            return;
                        }
                    }
                }
            }
        }

        setIsSubmitting(true);
        try {
            const payload: Record<string, unknown> = {
                service_id: Number(selectedServiceId),
                start_at: selectedSlot.start_at,
                staff_id: selectedStaffId ? Number(selectedStaffId) : null,
                payment_status: paymentStatus,
                notes: notes.trim() || null,
            };

            if (isNewCustomer) {
                payload.customer_name = newCustomer.name.trim();
                payload.customer_phone = newCustomer.phone.trim();
                payload.customer_email = newCustomer.email.trim() || null;
            } else {
                payload.customer_id = Number(selectedCustomerId);
            }

            // Collect visible custom field values
            const visibleCustomFields: Record<string, unknown> = {};
            if (activeForm && activeForm.fields) {
                for (const field of activeForm.fields) {
                    if (
                        (field.is_active ?? true) &&
                        isFieldVisible(field) &&
                        field.type !== 'file'
                    ) {
                        if (customFieldValues[field.field_key] !== undefined) {
                            visibleCustomFields[field.field_key] =
                                customFieldValues[field.field_key];
                        }
                    }
                }
            }

            const hasFiles = Object.values(customFieldFiles).some(
                (f) => f instanceof File
            );
            const csrfToken =
                (
                    document.querySelector(
                        'meta[name="csrf-token"]'
                    ) as HTMLMetaElement
                )?.content || '';

            let res: Response;

            if (hasFiles) {
                const formData = new FormData();
                Object.entries(payload).forEach(([k, v]) => {
                    if (v !== null && v !== undefined) {
                        formData.append(k, String(v));
                    }
                });

                Object.entries(visibleCustomFields).forEach(([k, v]) => {
                    if (Array.isArray(v)) {
                        v.forEach((item, idx) => {
                            formData.append(
                                `custom_fields[${k}][${idx}]`,
                                String(item)
                            );
                        });
                    } else if (v !== null && v !== undefined) {
                        formData.append(`custom_fields[${k}]`, String(v));
                    }
                });

                Object.entries(customFieldFiles).forEach(([fieldKey, file]) => {
                    if (file instanceof File) {
                        formData.append(`custom_fields[${fieldKey}]`, file);
                    }
                });

                res = await fetch('/app/bookings', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: formData,
                });
            } else {
                if (Object.keys(visibleCustomFields).length > 0) {
                    payload.custom_fields = visibleCustomFields;
                }
                res = await fetch('/app/bookings', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(payload),
                });
            }

            const data = await res.json();
            if (res.ok) {
                toast.success(data.message || 'Booking berhasil dibuat!');
                onBookingCreated();
                onClose();
                // Reset form
                setSelectedSlot(null);
                setNotes('');
                setNewCustomer({ name: '', phone: '', email: '' });
                setSelectedCustomerId('');
                setCustomFieldValues({});
                setCustomFieldFiles({});
            } else {
                toast.error(data.message || 'Gagal membuat booking.');
            }
        } catch {
            toast.error('Terjadi kesalahan jaringan.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title={
                <div className="flex items-center gap-2 text-sm font-semibold text-slate-900">
                    <Plus className="h-4 w-4 text-blue-600" />
                    <span>Quick Booking (Buat Reservasi Cepat)</span>
                </div>
            }
            description="Buat reservasi langsung untuk walk-in atau pemesanan telepon. Ketersediaan slot divalidasi langsung oleh sistem."
            size="lg"
        >
            <form onSubmit={handleSubmit} className="space-y-4 text-xs">
                {/* 1. Customer Section */}
                <div className="space-y-2.5 rounded-[10px] border border-slate-200 bg-slate-50 p-3">
                    <div className="flex items-center justify-between">
                        <span className="flex items-center gap-1.5 text-xs font-semibold text-slate-900">
                            <User className="h-3.5 w-3.5 text-slate-500" />
                            Data Pelanggan
                        </span>
                        <button
                            type="button"
                            onClick={() => {
                                setIsNewCustomer(!isNewCustomer);
                                setSelectedCustomerId('');
                            }}
                            className="flex items-center gap-1 text-[11px] font-medium text-blue-600 hover:underline"
                        >
                            {isNewCustomer ? (
                                <>
                                    <Search className="h-3 w-3" />
                                    Pilih dari Pelanggan Tersimpan
                                </>
                            ) : (
                                <>
                                    <UserPlus className="h-3 w-3" />+ Pelanggan
                                    Baru
                                </>
                            )}
                        </button>
                    </div>

                    {!isNewCustomer ? (
                        <div className="space-y-1.5">
                            <input
                                type="text"
                                value={customerSearch}
                                onChange={(e) =>
                                    setCustomerSearch(e.target.value)
                                }
                                placeholder="Ketik nama atau nomor HP customer..."
                                className="w-full rounded-[8px] border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:outline-none"
                            />
                            <select
                                value={selectedCustomerId}
                                onChange={(e) =>
                                    setSelectedCustomerId(e.target.value)
                                }
                                className="w-full rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:outline-none"
                            >
                                <option value="">-- Pilih Pelanggan --</option>
                                {filteredCustomers.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name} ({c.phone_e164})
                                    </option>
                                ))}
                            </select>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <Input
                                label="Nama Pelanggan"
                                required
                                value={newCustomer.name}
                                onChange={(e) =>
                                    setNewCustomer({
                                        ...newCustomer,
                                        name: e.target.value,
                                    })
                                }
                                placeholder="Contoh: Andi Gunawan"
                            />
                            <Input
                                label="Nomor Telepon (WhatsApp)"
                                required
                                value={newCustomer.phone}
                                onChange={(e) =>
                                    setNewCustomer({
                                        ...newCustomer,
                                        phone: e.target.value,
                                    })
                                }
                                placeholder="08123456789"
                            />
                        </div>
                    )}
                </div>

                {/* 2. Service & Staff Section */}
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label className="mb-1 block text-xs font-medium text-slate-700">
                            Pilih Layanan{' '}
                            <span className="text-rose-500">*</span>
                        </label>
                        <select
                            value={selectedServiceId}
                            onChange={(e) =>
                                setSelectedServiceId(e.target.value)
                            }
                            required
                            className="w-full rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:outline-none"
                        >
                            {services.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.name} ({s.duration_minutes} mnt - Rp{' '}
                                    {Number(s.price_idr).toLocaleString(
                                        'id-ID'
                                    )}
                                    )
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-medium text-slate-700">
                            Staf / Terapis
                        </label>
                        <select
                            value={selectedStaffId}
                            onChange={(e) => setSelectedStaffId(e.target.value)}
                            className="w-full rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:outline-none"
                        >
                            <option value="">
                                Auto-Assign (Pilihan Terbaik Sistem)
                            </option>
                            {resources.map((r) => (
                                <option key={r.id} value={r.id}>
                                    {r.name}{' '}
                                    {r.resource_type
                                        ? `(${r.resource_type.name})`
                                        : ''}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                {/* 3. Date & Slots Section */}
                <div className="space-y-2">
                    <div className="flex items-center justify-between">
                        <label className="flex items-center gap-1.5 text-xs font-medium text-slate-700">
                            <Calendar className="h-3.5 w-3.5 text-slate-500" />
                            Tanggal Booking{' '}
                            <span className="text-rose-500">*</span>
                        </label>
                        <input
                            type="date"
                            value={selectedDate}
                            onChange={(e) => setSelectedDate(e.target.value)}
                            required
                            className="rounded-[8px] border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-900 focus:border-blue-600 focus:outline-none"
                        />
                    </div>

                    {/* Slot Picker */}
                    <div className="rounded-[10px] border border-slate-200 bg-slate-50 p-3">
                        <div className="mb-2 flex items-center justify-between">
                            <span className="flex items-center gap-1 text-[11px] font-semibold tracking-wider text-slate-700 uppercase">
                                <Clock className="h-3 w-3 text-slate-400" />
                                Pilih Jam Slot yang Tersedia
                            </span>
                            {isLoadingSlots && (
                                <span className="flex items-center gap-1 text-[11px] text-blue-600">
                                    <Loader2 className="h-3 w-3 animate-spin" />
                                    Cek ketersediaan...
                                </span>
                            )}
                        </div>

                        {isLoadingSlots ? (
                            <div className="py-6 text-center text-xs text-slate-400">
                                Menghitung ketersediaan slot secara presisi...
                            </div>
                        ) : slots.length === 0 ? (
                            <div className="py-6 text-center text-xs text-slate-400">
                                Tidak ada slot yang tersedia untuk tanggal ini
                                (libur, break, atau kuota penuh).
                            </div>
                        ) : (
                            <div className="grid max-h-48 grid-cols-3 gap-1.5 overflow-y-auto pr-1 sm:grid-cols-4 md:grid-cols-6">
                                {slots.map((slot, idx) => {
                                    const isSelected =
                                        selectedSlot?.start_at ===
                                        slot.start_at;
                                    return (
                                        <button
                                            key={idx}
                                            type="button"
                                            disabled={!slot.is_available}
                                            onClick={() =>
                                                setSelectedSlot(slot)
                                            }
                                            title={
                                                slot.reason_message || undefined
                                            }
                                            className={`rounded-[6px] px-2 py-1.5 text-center font-mono text-xs transition-all ${
                                                isSelected
                                                    ? 'bg-blue-600 font-semibold text-white ring-2 ring-blue-600 ring-offset-1'
                                                    : slot.is_available
                                                      ? 'border border-slate-200 bg-white text-slate-800 hover:border-blue-600 hover:bg-blue-50'
                                                      : 'cursor-not-allowed border border-transparent bg-slate-100 text-slate-400 line-through'
                                            }`}
                                        >
                                            {slot.start_time}
                                        </button>
                                    );
                                })}
                            </div>
                        )}

                        {selectedSlot && (
                            <div className="mt-2 flex items-center gap-1.5 rounded border border-emerald-200 bg-emerald-50 p-1.5 text-[11px] text-emerald-700">
                                <Check className="h-3.5 w-3.5 shrink-0" />
                                <span>
                                    Slot terpilih:{' '}
                                    <strong>
                                        {selectedSlot.start_time} -{' '}
                                        {selectedSlot.end_time}
                                    </strong>{' '}
                                    ({selectedSlot.duration_minutes} menit)
                                </span>
                            </div>
                        )}
                    </div>
                </div>

                {/* 4. Dynamic Custom Form Fields (PRD 3.2) */}
                {activeForm &&
                    activeForm.fields &&
                    activeForm.fields.length > 0 &&
                    activeForm.fields.some(
                        (f) => (f.is_active ?? true) && isFieldVisible(f)
                    ) && (
                        <div className="space-y-3 rounded-[10px] border border-slate-200 bg-slate-50 p-3">
                            <div className="flex items-center justify-between border-b border-slate-200 pb-2">
                                <span className="flex items-center gap-1.5 text-xs font-semibold text-slate-900">
                                    <FileText className="h-3.5 w-3.5 text-blue-600" />
                                    Formulir Khusus ({activeForm.name})
                                </span>
                                <span className="text-[11px] text-slate-500">
                                    Input kondisional aktif
                                </span>
                            </div>

                            <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                                {activeForm.fields
                                    .filter(
                                        (f) =>
                                            (f.is_active ?? true) &&
                                            isFieldVisible(f)
                                    )
                                    .map((field) => (
                                        <div
                                            key={field.id}
                                            className={
                                                ['textarea', 'address'].includes(
                                                    field.type
                                                )
                                                    ? 'sm:col-span-2'
                                                    : ''
                                            }
                                        >
                                            <CustomFormFieldInput
                                                field={field}
                                                value={
                                                    customFieldValues[
                                                        field.field_key
                                                    ]
                                                }
                                                fileValue={
                                                    customFieldFiles[
                                                        field.field_key
                                                    ]
                                                }
                                                onChange={(fieldKey, val) =>
                                                    setCustomFieldValues(
                                                        (prev) => ({
                                                            ...prev,
                                                            [fieldKey]: val,
                                                        })
                                                    )
                                                }
                                                onFileChange={(
                                                    fieldKey,
                                                    file
                                                ) =>
                                                    setCustomFieldFiles(
                                                        (prev) => ({
                                                            ...prev,
                                                            [fieldKey]: file,
                                                        })
                                                    )
                                                }
                                            />
                                        </div>
                                    ))}
                            </div>
                        </div>
                    )}

                {/* 5. Payment & Notes */}
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label className="mb-1 block flex items-center gap-1 text-xs font-medium text-slate-700">
                            <DollarSign className="h-3.5 w-3.5 text-slate-400" />
                            Status Pembayaran
                        </label>
                        <select
                            value={paymentStatus}
                            onChange={(e) =>
                                setPaymentStatus(
                                    e.target.value as
                                        'UNPAID' | 'PAID' | 'PARTIAL'
                                )
                            }
                            className="w-full rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:outline-none"
                        >
                            <option value="UNPAID">
                                Belum Dibayar (UNPAID)
                            </option>
                            <option value="PAID">Lunas (PAID)</option>
                            <option value="PARTIAL">
                                Uang Muka / DP (PARTIAL)
                            </option>
                        </select>
                    </div>

                    <div>
                        <Textarea
                            label="Catatan Internal"
                            rows={2}
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            placeholder="Catatan permintaan khusus customer, meja tertentu, dll."
                        />
                    </div>
                </div>

                {/* Footer Buttons */}
                <div className="flex justify-end gap-2 border-t border-slate-200 pt-2">
                    <Button type="button" variant="outline" onClick={onClose}>
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        variant="primary"
                        isLoading={isSubmitting}
                        disabled={!selectedSlot}
                    >
                        Simpan Booking
                    </Button>
                </div>
            </form>
        </Modal>
    );
};
