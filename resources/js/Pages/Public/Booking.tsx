import { Head } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    ArrowRight,
    Check,
    Clock,
    Loader2,
    Lock,
    RefreshCw,
    ShieldCheck,
    Sparkles,
} from 'lucide-react';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Button } from '../../Components/ui/Button';
import { Input } from '../../Components/ui/Input';
import { Textarea } from '../../Components/ui/Textarea';
import { PublicLayout } from '../../Layouts/PublicLayout';
import { CustomFormFieldInput } from './CustomFormFieldInput';

export interface ServiceVariant {
    id: number;
    name: string;
    duration_minutes: number;
    price_idr: number;
}

export interface ServiceAddon {
    id: number;
    name: string;
    duration_minutes: number;
    price_idr: number;
}

export interface ServiceItem {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    price_idr: number;
    duration_minutes: number;
    capacity: number;
    category?: { id: number; name: string } | null;
    variants?: ServiceVariant[];
    addons?: ServiceAddon[];
}

export interface StaffItem {
    id: number;
    name: string;
    code?: string | null;
}

export interface TimeSlot {
    start_time: string;
    end_time: string;
    start_at: string;
    end_at: string;
    available: boolean;
    reason?: string | null;
    message?: string | null;
    available_capacity?: number;
    available_staff?: Array<{ id: number; name: string }>;
}

export interface FormFieldCondition {
    field: string;
    operator: 'eq' | 'neq' | 'in' | 'not_in' | 'filled' | 'empty' | string;
    value: unknown;
}

export interface FormFieldItem {
    id: number;
    field_key: string;
    type: string;
    label: string;
    placeholder?: string | null;
    help_text?: string | null;
    is_required: boolean;
    default_value?: string | null;
    options?: Array<{ label: string; value: string }> | null;
    validation_rules?: Record<string, unknown> | null;
    visibility_conditions?: FormFieldCondition[] | FormFieldCondition | null;
    sort_order: number;
    is_active: boolean;
}

export interface BookingFormItem {
    id: number;
    service_id?: number | null;
    name: string;
    description?: string | null;
    is_default: boolean;
    is_active: boolean;
    fields: FormFieldItem[];
}

export interface BookingProps {
    business: {
        name: string;
        slug: string;
        timezone: string;
        description?: string | null;
        address?: string | null;
        whatsapp?: string | null;
        phone?: string | null;
        logo_url?: string | null;
        booking_rules?: {
            min_advance_hours?: number;
            max_advance_days?: number;
            slot_interval_minutes?: number;
        } | null;
    };
    services: ServiceItem[];
    staff: StaffItem[];
    forms?: BookingFormItem[];
    preselectedServiceId?: number | null;
    quickMode?: boolean;
}

type BookingStep = 'service' | 'staff' | 'datetime' | 'customer' | 'review';

export default function Booking({
    business,
    services = [],
    staff = [],
    forms = [],
    preselectedServiceId = null,
    quickMode = false,
}: BookingProps) {
    // 1. Wizard Step State
    const hasStaffChoice = staff && staff.length > 0;
    const [step, setStep] = useState<BookingStep>(() => {
        if (
            quickMode &&
            preselectedServiceId &&
            services.some((s) => s.id === preselectedServiceId)
        ) {
            return 'datetime';
        }
        if (
            preselectedServiceId &&
            services.some((s) => s.id === preselectedServiceId)
        ) {
            return hasStaffChoice ? 'staff' : 'datetime';
        }
        return 'service';
    });

    // 2. Selection State
    const [selectedServiceId, setSelectedServiceId] = useState<number | null>(
        () =>
            preselectedServiceId ||
            (services.length === 1 ? services[0].id : null)
    );
    const [selectedVariantId, setSelectedVariantId] = useState<number | null>(
        null
    );
    const [selectedAddonIds, setSelectedAddonIds] = useState<number[]>([]);
    const [selectedStaffId, setSelectedStaffId] = useState<number | null>(null); // null = "Siapa saja (Otomatis)"

    // 3. Date & Slot State
    const todayStr = useMemo(() => {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }, []);

    const [selectedDate, setSelectedDate] = useState<string>(todayStr);
    const [selectedSlot, setSelectedSlot] = useState<TimeSlot | null>(null);
    const [slots, setSlots] = useState<TimeSlot[]>([]);
    const [isLoadingSlots, setIsLoadingSlots] = useState<boolean>(false);
    const [slotsError, setSlotsError] = useState<string | null>(null);

    // 4. Customer Form State (persisted temporarily in memory / sessionStorage)
    const [customerName, setCustomerName] = useState<string>(() => {
        return sessionStorage.getItem('aman_customer_name') || '';
    });
    const [customerPhone, setCustomerPhone] = useState<string>(() => {
        return sessionStorage.getItem('aman_customer_phone') || '';
    });
    const [customerEmail, setCustomerEmail] = useState<string>(() => {
        return sessionStorage.getItem('aman_customer_email') || '';
    });
    const [customerNotes, setCustomerNotes] = useState<string>(() => {
        return sessionStorage.getItem('aman_customer_notes') || '';
    });
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});

    // 4b. Dynamic Custom Fields State (PRD 26, 27)
    const [customFieldValues, setCustomFieldValues] = useState<
        Record<string, unknown>
    >({});
    const [customFieldFiles, setCustomFieldFiles] = useState<
        Record<string, File | null>
    >({});

    // Active form resolution (service-specific first, then default, then any active)
    const activeForm = useMemo(() => {
        if (!forms || forms.length === 0) return null;
        if (selectedServiceId) {
            const serviceForm = forms.find(
                (f) => f.is_active && f.service_id === selectedServiceId
            );
            if (serviceForm) return serviceForm;
        }
        const defaultForm = forms.find((f) => f.is_active && f.is_default);
        if (defaultForm) return defaultForm;
        return forms.find((f) => f.is_active) || null;
    }, [forms, selectedServiceId]);

    // Reactive visibility condition evaluator (aligned with server FormService::evaluateVisibility)
    const isFieldVisible = useCallback(
        (field: FormFieldItem, values: Record<string, unknown>): boolean => {
            const conditions = field.visibility_conditions;
            if (!conditions) return true;
            const ruleList = Array.isArray(conditions)
                ? conditions
                : [conditions];
            if (ruleList.length === 0) return true;

            for (const rule of ruleList) {
                const targetKey = rule.field;
                const op = rule.operator || 'eq';
                const expected = rule.value;
                const actual = values[targetKey];

                let pass = false;
                switch (op) {
                    case 'eq':
                        pass = String(actual ?? '') === String(expected ?? '');
                        break;
                    case 'neq':
                        pass = String(actual ?? '') !== String(expected ?? '');
                        break;
                    case 'in':
                        pass = Array.isArray(expected)
                            ? expected
                                  .map(String)
                                  .includes(String(actual ?? ''))
                            : false;
                        break;
                    case 'not_in':
                        pass = Array.isArray(expected)
                            ? !expected
                                  .map(String)
                                  .includes(String(actual ?? ''))
                            : true;
                        break;
                    case 'filled':
                        pass =
                            actual !== undefined &&
                            actual !== null &&
                            actual !== '';
                        break;
                    case 'empty':
                        pass =
                            actual === undefined ||
                            actual === null ||
                            actual === '';
                        break;
                    default:
                        pass = String(actual ?? '') === String(expected ?? '');
                        break;
                }

                if (!pass) return false;
            }
            return true;
        },
        []
    );

    // 5. Submission & Idempotency Key
    const [idempotencyKey] = useState<string>(() => {
        if (typeof crypto !== 'undefined' && crypto.randomUUID) {
            return crypto.randomUUID();
        }
        return `uuid-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
    });
    const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
    const [submissionError, setSubmissionError] = useState<string | null>(null);

    // Save customer input to sessionStorage
    useEffect(() => {
        sessionStorage.setItem('aman_customer_name', customerName);
    }, [customerName]);
    useEffect(() => {
        sessionStorage.setItem('aman_customer_phone', customerPhone);
    }, [customerPhone]);
    useEffect(() => {
        sessionStorage.setItem('aman_customer_email', customerEmail);
    }, [customerEmail]);
    useEffect(() => {
        sessionStorage.setItem('aman_customer_notes', customerNotes);
    }, [customerNotes]);

    // Derived active service
    const activeService = useMemo(() => {
        return services.find((s) => s.id === selectedServiceId) || null;
    }, [services, selectedServiceId]);

    // Active variant & addons
    const activeVariant = useMemo(() => {
        if (!activeService?.variants || !selectedVariantId) return null;
        return (
            activeService.variants.find((v) => v.id === selectedVariantId) ||
            null
        );
    }, [activeService, selectedVariantId]);

    const activeAddons = useMemo(() => {
        if (!activeService?.addons) return [];
        return activeService.addons.filter((a) =>
            selectedAddonIds.includes(a.id)
        );
    }, [activeService, selectedAddonIds]);

    // Active staff
    const activeStaff = useMemo(() => {
        if (!selectedStaffId) return null;
        return staff.find((s) => s.id === selectedStaffId) || null;
    }, [staff, selectedStaffId]);

    // Total Duration & Price calculations
    const totalPrice = useMemo(() => {
        let price = activeVariant
            ? activeVariant.price_idr
            : activeService?.price_idr || 0;
        for (const addon of activeAddons) {
            price += addon.price_idr;
        }
        return price;
    }, [activeService, activeVariant, activeAddons]);

    const totalDuration = useMemo(() => {
        let duration = activeVariant
            ? activeVariant.duration_minutes
            : activeService?.duration_minutes || 0;
        for (const addon of activeAddons) {
            duration += addon.duration_minutes;
        }
        return duration;
    }, [activeService, activeVariant, activeAddons]);

    // Timezone code label
    const timezoneLabel = useMemo(() => {
        const tz = business.timezone || 'Asia/Jakarta';
        if (
            tz.includes('Jayapura') ||
            tz.includes('WIT') ||
            tz === 'Asia/Jayapura'
        )
            return 'WIT (UTC+9)';
        if (
            tz.includes('Makassar') ||
            tz.includes('WITA') ||
            tz === 'Asia/Makassar'
        )
            return 'WITA (UTC+8)';
        return 'WIB (UTC+7)';
    }, [business.timezone]);

    // 14-day date strip
    const dateStrip = useMemo(() => {
        const days = [];
        const base = new Date();
        for (let i = 0; i < 14; i++) {
            const d = new Date(base);
            d.setDate(base.getDate() + i);
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            const dateStr = `${yyyy}-${mm}-${dd}`;
            const dayName = d.toLocaleDateString('id-ID', { weekday: 'short' });
            const dayNum = d.getDate();
            const monthName = d.toLocaleDateString('id-ID', { month: 'short' });
            days.push({
                dateStr,
                dayName,
                dayNum,
                monthName,
                isToday: i === 0,
            });
        }
        return days;
    }, []);

    // Fetch availability slots whenever selectedService, variant, addons, staff, or date change
    const fetchSlots = useCallback(async () => {
        if (!selectedServiceId || !selectedDate) return;
        setIsLoadingSlots(true);
        setSlotsError(null);
        setSelectedSlot(null);

        try {
            const params = new URLSearchParams({
                service_id: String(selectedServiceId),
                date: selectedDate,
            });
            if (selectedVariantId)
                params.append('variant_id', String(selectedVariantId));
            if (selectedStaffId)
                params.append('staff_id', String(selectedStaffId));
            selectedAddonIds.forEach((id) =>
                params.append('addon_ids[]', String(id))
            );

            const res = await fetch(
                `/${business.slug}/availability?${params.toString()}`,
                {
                    headers: {
                        Accept: 'application/json',
                    },
                }
            );

            if (!res.ok) {
                const errData = await res.json().catch(() => null);
                throw new Error(
                    errData?.message || 'Gagal memuat jadwal ketersediaan.'
                );
            }

            const data = await res.json();
            setSlots(data.slots || []);
        } catch (err: unknown) {
            const msg =
                err instanceof Error
                    ? err.message
                    : 'Terjadi gangguan saat memuat slot waktu.';
            setSlotsError(msg);
            setSlots([]);
        } finally {
            setIsLoadingSlots(false);
        }
    }, [
        business.slug,
        selectedServiceId,
        selectedDate,
        selectedVariantId,
        selectedStaffId,
        selectedAddonIds,
    ]);

    useEffect(() => {
        if (step === 'datetime' && selectedServiceId && selectedDate) {
            void fetchSlots();
        }
    }, [step, selectedServiceId, selectedDate, fetchSlots]);

    // Group slots into Pagi, Siang, Malam
    const groupedSlots = useMemo(() => {
        const morning: TimeSlot[] = [];
        const afternoon: TimeSlot[] = [];
        const evening: TimeSlot[] = [];

        slots.forEach((s) => {
            const hour = parseInt(s.start_time.split(':')[0], 10);
            if (hour < 12) {
                morning.push(s);
            } else if (hour < 17) {
                afternoon.push(s);
            } else {
                evening.push(s);
            }
        });

        return { morning, afternoon, evening };
    }, [slots]);

    // Toggle addon selection
    const handleToggleAddon = (addonId: number) => {
        setSelectedAddonIds((prev) =>
            prev.includes(addonId)
                ? prev.filter((id) => id !== addonId)
                : [...prev, addonId]
        );
    };

    // Validation for customer form + dynamic custom fields (PRD 3.2: hidden required fields are NOT validated)
    const validateCustomerStep = (): boolean => {
        const errors: Record<string, string> = {};
        if (!customerName.trim()) {
            errors.customer_name = 'Nama lengkap wajib diisi.';
        }
        const cleanedPhone = customerPhone.replace(/\D/g, '');
        if (!customerPhone.trim()) {
            errors.customer_phone = 'Nomor WhatsApp wajib diisi.';
        } else if (cleanedPhone.length < 9 || cleanedPhone.length > 15) {
            errors.customer_phone =
                'Nomor WhatsApp harus antara 9-15 digit angka.';
        }
        if (customerEmail.trim()) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(customerEmail.trim())) {
                errors.customer_email = 'Format email tidak valid.';
            }
        }

        // Validate visible custom fields
        if (activeForm?.fields) {
            for (const field of activeForm.fields) {
                if (!field.is_active) continue;
                const visible = isFieldVisible(field, customFieldValues);
                // PRD 3.2: Field wajib tersembunyi tidak divalidasi
                if (!visible) continue;

                if (field.is_required) {
                    if (field.type === 'file') {
                        if (!customFieldFiles[field.field_key]) {
                            errors[`custom_${field.field_key}`] =
                                `${field.label} wajib diunggah.`;
                        }
                    } else {
                        const val = customFieldValues[field.field_key];
                        if (
                            val === undefined ||
                            val === null ||
                            val === '' ||
                            (Array.isArray(val) && val.length === 0)
                        ) {
                            errors[`custom_${field.field_key}`] =
                                `${field.label} wajib diisi.`;
                        }
                    }
                }
            }
        }

        setFormErrors(errors);
        return Object.keys(errors).length === 0;
    };

    // Handle Form Submit
    const handleSubmitBooking = async () => {
        if (!activeService || !selectedSlot) return;
        setIsSubmitting(true);
        setSubmissionError(null);

        const formData = new FormData();
        formData.append('service_id', String(activeService.id));
        if (selectedVariantId) {
            formData.append('variant_id', String(selectedVariantId));
        }
        selectedAddonIds.forEach((id) =>
            formData.append('addon_ids[]', String(id))
        );
        if (selectedStaffId) {
            formData.append('staff_id', String(selectedStaffId));
        }
        formData.append('start_at', selectedSlot.start_at);
        formData.append('customer_name', customerName.trim());
        formData.append('customer_phone', customerPhone.trim());
        if (customerEmail.trim()) {
            formData.append('customer_email', customerEmail.trim());
        }
        if (customerNotes.trim()) {
            formData.append('customer_notes', customerNotes.trim());
        }
        formData.append('idempotency_key', idempotencyKey);

        // Append custom field text/json values
        Object.entries(customFieldValues).forEach(([k, v]) => {
            if (Array.isArray(v)) {
                v.forEach((val) =>
                    formData.append(`custom_fields[${k}][]`, String(val))
                );
            } else if (v !== null && v !== undefined && v !== '') {
                formData.append(`custom_fields[${k}]`, String(v));
            }
        });

        // Append uploaded files
        Object.entries(customFieldFiles).forEach(([k, file]) => {
            if (file instanceof File) {
                formData.append(`custom_fields[${k}]`, file);
            }
        });

        try {
            const res = await fetch(`/${business.slug}/booking`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Idempotency-Key': idempotencyKey,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            const data = await res.json().catch(() => null);

            if (!res.ok) {
                // Friendly error message handling (e.g. SLOT_TAKEN)
                if (data?.error_code === 'SLOT_TAKEN') {
                    setSubmissionError(
                        'Slot waktu tersebut baru saja dipesan oleh customer lain. Silakan pilih waktu lain di bawah.'
                    );
                    setStep('datetime');
                    fetchSlots();
                } else {
                    setSubmissionError(
                        data?.message ||
                            'Terjadi kesalahan saat memproses reservasi Anda. Silakan coba lagi.'
                    );
                }
                return;
            }

            if (data?.redirect_url) {
                window.location.href = data.redirect_url;
            } else if (data?.code) {
                window.location.href = `/${business.slug}/booking/success/${data.code}`;
            }
        } catch (err: unknown) {
            const msg =
                err instanceof Error
                    ? err.message
                    : 'Koneksi gagal. Silakan periksa jaringan internet Anda.';
            setSubmissionError(msg);
        } finally {
            setIsSubmitting(false);
        }
    };

    // Format currency IDR
    const formatRp = (num: number) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(num);
    };

    return (
        <PublicLayout
            title={`Booking Online — ${business.name}`}
            businessName={business.name}
            businessPhone={business.whatsapp || business.phone || undefined}
        >
            <Head>
                <title>{`Reservasi Online - ${business.name}`}</title>
                <meta
                    name="description"
                    content={`Reservasi jadwal layanan di ${business.name}. Pilih waktu sendiri, terkonfirmasi instan tanpa antre.`}
                />
            </Head>

            <div className="mx-auto max-w-2xl">
                {/* Step Indicator Header */}
                <div className="mb-6 rounded-[14px] border border-slate-200 bg-white p-4 shadow-xs">
                    <div className="flex items-center justify-between text-xs font-semibold text-slate-500">
                        <span className="flex items-center gap-1.5 text-blue-600">
                            <span className="flex h-5 w-5 items-center justify-center rounded-full bg-blue-600 text-[10px] text-white">
                                {step === 'service'
                                    ? '1'
                                    : step === 'staff'
                                      ? '2'
                                      : step === 'datetime'
                                        ? '3'
                                        : step === 'customer'
                                          ? '4'
                                          : '5'}
                            </span>
                            <span className="font-bold text-slate-900 capitalize">
                                {step === 'service' && 'Pilih Layanan'}
                                {step === 'staff' && 'Pilih Terapis/Staf'}
                                {step === 'datetime' && 'Pilih Tanggal & Waktu'}
                                {step === 'customer' && 'Data Pemesan'}
                                {step === 'review' && 'Konfirmasi Reservasi'}
                            </span>
                        </span>

                        <span className="text-[11px] font-medium text-slate-400">
                            Zona Waktu:{' '}
                            <strong className="text-slate-700">
                                {timezoneLabel}
                            </strong>
                        </span>
                    </div>

                    {/* Progress Bar */}
                    <div className="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                        <div
                            className="h-full bg-blue-600 transition-all duration-300"
                            style={{
                                width:
                                    step === 'service'
                                        ? '20%'
                                        : step === 'staff'
                                          ? '40%'
                                          : step === 'datetime'
                                            ? '60%'
                                            : step === 'customer'
                                              ? '80%'
                                              : '100%',
                            }}
                        />
                    </div>
                </div>

                {/* Submission Error Banner */}
                {submissionError && (
                    <div className="mb-6 flex items-start gap-3 rounded-[12px] border border-red-200 bg-red-50 p-4 text-xs text-red-800">
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0 text-red-600" />
                        <div className="flex-1">
                            <strong className="block font-semibold">
                                Reservasi Belum Berhasil
                            </strong>
                            <span>{submissionError}</span>
                        </div>
                    </div>
                )}

                {/* ================= STEP 1: LAYANAN ================= */}
                {step === 'service' && (
                    <div className="space-y-4">
                        <div className="rounded-[14px] border border-slate-200 bg-white p-5 shadow-xs sm:p-6">
                            <h2 className="text-base font-bold text-slate-900 sm:text-lg">
                                Pilih Layanan yang Anda Inginkan
                            </h2>
                            <p className="mt-1 text-xs text-slate-500">
                                Pilih satu layanan utama untuk memulai reservasi
                                Anda.
                            </p>

                            <div className="mt-5 space-y-3">
                                {services.map((srv) => {
                                    const isSelected =
                                        selectedServiceId === srv.id;
                                    return (
                                        <div
                                            key={srv.id}
                                            onClick={() => {
                                                setSelectedServiceId(srv.id);
                                                setSelectedVariantId(
                                                    srv.variants?.[0]?.id ||
                                                        null
                                                );
                                                setSelectedAddonIds([]);
                                            }}
                                            className={`cursor-pointer rounded-[12px] border p-4 transition-all ${
                                                isSelected
                                                    ? 'border-blue-600 bg-blue-50/40 ring-1 ring-blue-600'
                                                    : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/60'
                                            }`}
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-sm font-bold text-slate-900">
                                                            {srv.name}
                                                        </span>
                                                        {srv.category && (
                                                            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">
                                                                {
                                                                    srv.category
                                                                        .name
                                                                }
                                                            </span>
                                                        )}
                                                    </div>
                                                    {srv.description && (
                                                        <p className="mt-1 line-clamp-2 text-xs text-slate-500">
                                                            {srv.description}
                                                        </p>
                                                    )}
                                                    <div className="mt-2.5 flex items-center gap-3 text-xs font-medium text-slate-600">
                                                        <span className="flex items-center gap-1">
                                                            <Clock className="h-3.5 w-3.5 text-slate-400" />
                                                            {
                                                                srv.duration_minutes
                                                            }{' '}
                                                            Menit
                                                        </span>
                                                        <span className="font-bold text-blue-600">
                                                            {formatRp(
                                                                srv.price_idr
                                                            )}
                                                        </span>
                                                    </div>
                                                </div>

                                                <div
                                                    className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-full border transition-all ${
                                                        isSelected
                                                            ? 'border-blue-600 bg-blue-600 text-white'
                                                            : 'border-slate-300 bg-white'
                                                    }`}
                                                >
                                                    {isSelected && (
                                                        <Check className="h-3 w-3 stroke-[3]" />
                                                    )}
                                                </div>
                                            </div>

                                            {/* Nested Variants if selected */}
                                            {isSelected &&
                                                srv.variants &&
                                                srv.variants.length > 0 && (
                                                    <div className="mt-4 border-t border-blue-100 pt-3.5">
                                                        <span className="block text-[11px] font-semibold text-slate-700">
                                                            Pilih Tipe / Varian:
                                                        </span>
                                                        <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                            {srv.variants.map(
                                                                (v) => {
                                                                    const isVarSelected =
                                                                        selectedVariantId ===
                                                                        v.id;
                                                                    return (
                                                                        <button
                                                                            key={
                                                                                v.id
                                                                            }
                                                                            type="button"
                                                                            onClick={(
                                                                                e
                                                                            ) => {
                                                                                e.stopPropagation();
                                                                                setSelectedVariantId(
                                                                                    v.id
                                                                                );
                                                                            }}
                                                                            className={`flex items-center justify-between rounded-lg border px-3 py-2 text-left text-xs transition-all ${
                                                                                isVarSelected
                                                                                    ? 'border-blue-600 bg-white font-semibold text-blue-900 ring-1 ring-blue-600'
                                                                                    : 'border-slate-200 bg-white/80 text-slate-700 hover:border-slate-300'
                                                                            }`}
                                                                        >
                                                                            <span>
                                                                                {
                                                                                    v.name
                                                                                }
                                                                            </span>
                                                                            <span className="font-bold text-blue-600">
                                                                                {formatRp(
                                                                                    v.price_idr
                                                                                )}
                                                                            </span>
                                                                        </button>
                                                                    );
                                                                }
                                                            )}
                                                        </div>
                                                    </div>
                                                )}

                                            {/* Nested Add-ons if selected */}
                                            {isSelected &&
                                                srv.addons &&
                                                srv.addons.length > 0 && (
                                                    <div className="mt-4 border-t border-blue-100 pt-3.5">
                                                        <span className="block text-[11px] font-semibold text-slate-700">
                                                            Tambahan Opsional
                                                            (Add-on):
                                                        </span>
                                                        <div className="mt-2 space-y-2">
                                                            {srv.addons.map(
                                                                (addon) => {
                                                                    const isChecked =
                                                                        selectedAddonIds.includes(
                                                                            addon.id
                                                                        );
                                                                    return (
                                                                        <label
                                                                            key={
                                                                                addon.id
                                                                            }
                                                                            onClick={(
                                                                                e
                                                                            ) =>
                                                                                e.stopPropagation()
                                                                            }
                                                                            className={`flex cursor-pointer items-center justify-between rounded-lg border px-3 py-2 text-xs transition-all ${
                                                                                isChecked
                                                                                    ? 'border-blue-500 bg-white font-medium text-slate-900'
                                                                                    : 'border-slate-200 bg-white/70 text-slate-600 hover:bg-white'
                                                                            }`}
                                                                        >
                                                                            <div className="flex items-center gap-2">
                                                                                <input
                                                                                    type="checkbox"
                                                                                    checked={
                                                                                        isChecked
                                                                                    }
                                                                                    onChange={() =>
                                                                                        handleToggleAddon(
                                                                                            addon.id
                                                                                        )
                                                                                    }
                                                                                    className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                                                                />
                                                                                <span>
                                                                                    {
                                                                                        addon.name
                                                                                    }{' '}
                                                                                    (+
                                                                                    {
                                                                                        addon.duration_minutes
                                                                                    }
                                                                                    m)
                                                                                </span>
                                                                            </div>
                                                                            <span className="font-semibold text-slate-700">
                                                                                +
                                                                                {formatRp(
                                                                                    addon.price_idr
                                                                                )}
                                                                            </span>
                                                                        </label>
                                                                    );
                                                                }
                                                            )}
                                                        </div>
                                                    </div>
                                                )}
                                        </div>
                                    );
                                })}
                            </div>

                            {/* Continue Button */}
                            <div className="mt-6 flex justify-end">
                                <Button
                                    variant="primary"
                                    size="lg"
                                    disabled={!selectedServiceId}
                                    onClick={() =>
                                        setStep(
                                            hasStaffChoice
                                                ? 'staff'
                                                : 'datetime'
                                        )
                                    }
                                    className="w-full min-w-[160px] sm:w-auto"
                                >
                                    Lanjutkan
                                    <ArrowRight className="ml-1.5 h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                )}

                {/* ================= STEP 2: STAFF (OPTIONAL) ================= */}
                {step === 'staff' && (
                    <div className="rounded-[14px] border border-slate-200 bg-white p-5 shadow-xs sm:p-6">
                        <div className="flex items-center justify-between">
                            <h2 className="text-base font-bold text-slate-900 sm:text-lg">
                                Pilih Terapis atau Staf
                            </h2>
                            <button
                                type="button"
                                onClick={() => setStep('service')}
                                className="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-900"
                            >
                                <ArrowLeft className="h-3.5 w-3.5" />
                                Kembali
                            </button>
                        </div>
                        <p className="mt-1 text-xs text-slate-500">
                            Pilih petugas pilihan Anda atau serahkan pada kami
                            untuk penjadwalan otomatis.
                        </p>

                        <div className="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            {/* Option 1: Anyone Available */}
                            <div
                                onClick={() => setSelectedStaffId(null)}
                                className={`cursor-pointer rounded-[12px] border p-4 transition-all ${
                                    selectedStaffId === null
                                        ? 'border-blue-600 bg-blue-50/40 ring-1 ring-blue-600'
                                        : 'border-slate-200 bg-white hover:border-slate-300'
                                }`}
                            >
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-600">
                                        <Sparkles className="h-5 w-5" />
                                    </div>
                                    <div className="flex-1">
                                        <span className="block text-xs font-bold text-slate-900">
                                            Siapa Saja yang Tersedia
                                        </span>
                                        <span className="block text-[11px] text-slate-500">
                                            Jadwal tercepat & terluang
                                        </span>
                                    </div>
                                    {selectedStaffId === null && (
                                        <Check className="h-4 w-4 stroke-[3] text-blue-600" />
                                    )}
                                </div>
                            </div>

                            {/* Option 2..N: Specific Staff */}
                            {staff.map((st) => {
                                const isSelected = selectedStaffId === st.id;
                                return (
                                    <div
                                        key={st.id}
                                        onClick={() =>
                                            setSelectedStaffId(st.id)
                                        }
                                        className={`cursor-pointer rounded-[12px] border p-4 transition-all ${
                                            isSelected
                                                ? 'border-blue-600 bg-blue-50/40 ring-1 ring-blue-600'
                                                : 'border-slate-200 bg-white hover:border-slate-300'
                                        }`}
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-700 uppercase">
                                                {st.name.slice(0, 2)}
                                            </div>
                                            <div className="flex-1">
                                                <span className="block text-xs font-bold text-slate-900">
                                                    {st.name}
                                                </span>
                                                <span className="block text-[11px] text-slate-500">
                                                    {st.code
                                                        ? `Staff ID: ${st.code}`
                                                        : 'Staf Profesional'}
                                                </span>
                                            </div>
                                            {isSelected && (
                                                <Check className="h-4 w-4 stroke-[3] text-blue-600" />
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        {/* Navigation */}
                        <div className="mt-6 flex items-center justify-between border-t border-slate-100 pt-4">
                            <Button
                                variant="secondary"
                                size="md"
                                onClick={() => setStep('service')}
                            >
                                <ArrowLeft className="mr-1.5 h-4 w-4" />
                                Ubah Layanan
                            </Button>
                            <Button
                                variant="primary"
                                size="md"
                                onClick={() => setStep('datetime')}
                            >
                                Pilih Jadwal
                                <ArrowRight className="ml-1.5 h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                )}

                {/* ================= STEP 3: TANGGAL & WAKTU ================= */}
                {step === 'datetime' && (
                    <div className="space-y-4">
                        <div className="rounded-[14px] border border-slate-200 bg-white p-5 shadow-xs sm:p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h2 className="text-base font-bold text-slate-900 sm:text-lg">
                                        Pilih Tanggal & Waktu Kedatangan
                                    </h2>
                                    <p className="mt-0.5 text-xs text-slate-500">
                                        Durasi layanan:{' '}
                                        <strong>{totalDuration} menit</strong> |
                                        Zona waktu:{' '}
                                        <strong className="text-blue-700">
                                            {timezoneLabel}
                                        </strong>
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() =>
                                        setStep(
                                            hasStaffChoice ? 'staff' : 'service'
                                        )
                                    }
                                    className="hidden items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-900 sm:inline-flex"
                                >
                                    <ArrowLeft className="h-3.5 w-3.5" />
                                    Kembali
                                </button>
                            </div>

                            {/* Horizontal 14-Day Strip */}
                            <div className="mt-5">
                                <span className="mb-2 block text-xs font-semibold text-slate-700">
                                    Pilih Hari:
                                </span>
                                <div className="flex scrollbar-thin gap-2 overflow-x-auto pb-2">
                                    {dateStrip.map((item) => {
                                        const isSelected =
                                            selectedDate === item.dateStr;
                                        return (
                                            <button
                                                key={item.dateStr}
                                                type="button"
                                                onClick={() =>
                                                    setSelectedDate(
                                                        item.dateStr
                                                    )
                                                }
                                                className={`flex min-w-[62px] flex-col items-center justify-center rounded-[10px] border px-2 py-2.5 text-center transition-all ${
                                                    isSelected
                                                        ? 'border-blue-600 bg-blue-600 font-bold text-white shadow-xs'
                                                        : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'
                                                }`}
                                            >
                                                <span
                                                    className={`text-[10px] font-semibold uppercase ${
                                                        isSelected
                                                            ? 'text-blue-100'
                                                            : 'text-slate-400'
                                                    }`}
                                                >
                                                    {item.isToday
                                                        ? 'Hari Ini'
                                                        : item.dayName}
                                                </span>
                                                <span className="mt-0.5 text-base leading-tight font-black">
                                                    {item.dayNum}
                                                </span>
                                                <span
                                                    className={`text-[10px] ${
                                                        isSelected
                                                            ? 'text-blue-100'
                                                            : 'text-slate-400'
                                                    }`}
                                                >
                                                    {item.monthName}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            {/* Slot Picker Grid */}
                            <div className="mt-6 border-t border-slate-100 pt-5">
                                <div className="mb-3 flex items-center justify-between">
                                    <span className="text-xs font-bold text-slate-900">
                                        Slot Waktu Tersedia ({selectedDate}):
                                    </span>
                                    <button
                                        type="button"
                                        onClick={fetchSlots}
                                        disabled={isLoadingSlots}
                                        className="inline-flex items-center gap-1 text-[11px] font-semibold text-blue-600 hover:text-blue-700 disabled:opacity-50"
                                    >
                                        <RefreshCw
                                            className={`h-3 w-3 ${isLoadingSlots ? 'animate-spin' : ''}`}
                                        />
                                        Segarkan Slot
                                    </button>
                                </div>

                                {isLoadingSlots ? (
                                    <div className="flex flex-col items-center justify-center py-12 text-slate-400">
                                        <Loader2 className="h-6 w-6 animate-spin text-blue-600" />
                                        <span className="mt-2 text-xs">
                                            Memeriksa ketersediaan jadwal...
                                        </span>
                                    </div>
                                ) : slotsError ? (
                                    <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-700">
                                        <p className="font-semibold">
                                            {slotsError}
                                        </p>
                                        <button
                                            type="button"
                                            onClick={fetchSlots}
                                            className="mt-2 text-xs font-bold text-red-800 underline"
                                        >
                                            Coba Lagi
                                        </button>
                                    </div>
                                ) : slots.length === 0 ? (
                                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-6 text-center text-xs text-slate-500">
                                        <p className="font-semibold text-slate-800">
                                            Tidak Ada Slot Jadwal Tersedia
                                        </p>
                                        <p className="mt-1">
                                            Semua jadwal pada tanggal ini sudah
                                            penuh atau sedang tutup. Silakan
                                            pilih tanggal lain di atas.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="space-y-4">
                                        {/* Morning */}
                                        {groupedSlots.morning.length > 0 && (
                                            <div>
                                                <span className="mb-2 block text-[11px] font-semibold tracking-wider text-slate-400 uppercase">
                                                    Pagi (09:00 - 12:00)
                                                </span>
                                                <div className="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
                                                    {groupedSlots.morning.map(
                                                        (slot, idx) => (
                                                            <TimeSlotButton
                                                                key={idx}
                                                                slot={slot}
                                                                isSelected={
                                                                    selectedSlot?.start_at ===
                                                                    slot.start_at
                                                                }
                                                                onSelect={() =>
                                                                    setSelectedSlot(
                                                                        slot
                                                                    )
                                                                }
                                                            />
                                                        )
                                                    )}
                                                </div>
                                            </div>
                                        )}

                                        {/* Afternoon */}
                                        {groupedSlots.afternoon.length > 0 && (
                                            <div>
                                                <span className="mb-2 block text-[11px] font-semibold tracking-wider text-slate-400 uppercase">
                                                    Siang / Sore (12:00 - 17:00)
                                                </span>
                                                <div className="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
                                                    {groupedSlots.afternoon.map(
                                                        (slot, idx) => (
                                                            <TimeSlotButton
                                                                key={idx}
                                                                slot={slot}
                                                                isSelected={
                                                                    selectedSlot?.start_at ===
                                                                    slot.start_at
                                                                }
                                                                onSelect={() =>
                                                                    setSelectedSlot(
                                                                        slot
                                                                    )
                                                                }
                                                            />
                                                        )
                                                    )}
                                                </div>
                                            </div>
                                        )}

                                        {/* Evening */}
                                        {groupedSlots.evening.length > 0 && (
                                            <div>
                                                <span className="mb-2 block text-[11px] font-semibold tracking-wider text-slate-400 uppercase">
                                                    Malam (17:00+)
                                                </span>
                                                <div className="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
                                                    {groupedSlots.evening.map(
                                                        (slot, idx) => (
                                                            <TimeSlotButton
                                                                key={idx}
                                                                slot={slot}
                                                                isSelected={
                                                                    selectedSlot?.start_at ===
                                                                    slot.start_at
                                                                }
                                                                onSelect={() =>
                                                                    setSelectedSlot(
                                                                        slot
                                                                    )
                                                                }
                                                            />
                                                        )
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>

                            {/* Navigation */}
                            <div className="mt-8 flex items-center justify-between border-t border-slate-100 pt-4">
                                <Button
                                    variant="secondary"
                                    size="md"
                                    onClick={() =>
                                        setStep(
                                            hasStaffChoice ? 'staff' : 'service'
                                        )
                                    }
                                >
                                    <ArrowLeft className="mr-1.5 h-4 w-4" />
                                    Kembali
                                </Button>
                                <Button
                                    variant="primary"
                                    size="md"
                                    disabled={!selectedSlot}
                                    onClick={() => setStep('customer')}
                                >
                                    Isi Data Diri
                                    <ArrowRight className="ml-1.5 h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                )}

                {/* ================= STEP 4: DATA PEMESAN ================= */}
                {step === 'customer' && (
                    <div className="rounded-[14px] border border-slate-200 bg-white p-5 shadow-xs sm:p-6">
                        <div className="flex items-center justify-between">
                            <h2 className="text-base font-bold text-slate-900 sm:text-lg">
                                Lengkapi Data Pemesan
                            </h2>
                            <button
                                type="button"
                                onClick={() => setStep('datetime')}
                                className="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-900"
                            >
                                <ArrowLeft className="h-3.5 w-3.5" />
                                Kembali
                            </button>
                        </div>
                        <p className="mt-1 text-xs text-slate-500">
                            Konfirmasi tiket dan detail reservasi akan
                            dikirimkan langsung ke nomor WhatsApp Anda.
                        </p>

                        <div className="mt-5 space-y-4">
                            {/* Nama Lengkap */}
                            <div>
                                <label className="mb-1 block text-xs font-semibold text-slate-700">
                                    Nama Lengkap{' '}
                                    <span className="text-red-500">*</span>
                                </label>
                                <Input
                                    type="text"
                                    value={customerName}
                                    onChange={(e) =>
                                        setCustomerName(e.target.value)
                                    }
                                    placeholder="Contoh: Budi Pratama"
                                    error={formErrors.customer_name}
                                    className="text-sm"
                                    autoComplete="name"
                                />
                                {formErrors.customer_name && (
                                    <span className="mt-1 block text-[11px] text-red-600">
                                        {formErrors.customer_name}
                                    </span>
                                )}
                            </div>

                            {/* Nomor WhatsApp */}
                            <div>
                                <label className="mb-1 block text-xs font-semibold text-slate-700">
                                    Nomor WhatsApp Aktif{' '}
                                    <span className="text-red-500">*</span>
                                </label>
                                <div className="relative">
                                    <Input
                                        type="tel"
                                        inputMode="tel"
                                        value={customerPhone}
                                        onChange={(e) =>
                                            setCustomerPhone(e.target.value)
                                        }
                                        placeholder="081234567890"
                                        error={formErrors.customer_phone}
                                        className="text-sm"
                                        autoComplete="tel"
                                    />
                                </div>
                                <span className="mt-1 block text-[11px] text-slate-400">
                                    Gunakan format 08xx atau 628xx. Tiket
                                    booking akan dikirim ke nomor ini.
                                </span>
                                {formErrors.customer_phone && (
                                    <span className="mt-1 block text-[11px] text-red-600">
                                        {formErrors.customer_phone}
                                    </span>
                                )}
                            </div>

                            {/* Email (Opsional) */}
                            <div>
                                <label className="mb-1 block text-xs font-semibold text-slate-700">
                                    Email{' '}
                                    <span className="font-normal text-slate-400">
                                        (Opsional)
                                    </span>
                                </label>
                                <Input
                                    type="email"
                                    inputMode="email"
                                    value={customerEmail}
                                    onChange={(e) =>
                                        setCustomerEmail(e.target.value)
                                    }
                                    placeholder="budi@example.com"
                                    error={formErrors.customer_email}
                                    className="text-sm"
                                    autoComplete="email"
                                />
                                {formErrors.customer_email && (
                                    <span className="mt-1 block text-[11px] text-red-600">
                                        {formErrors.customer_email}
                                    </span>
                                )}
                            </div>

                            {/* Catatan Khusus */}
                            <div>
                                <label className="mb-1 block text-xs font-semibold text-slate-700">
                                    Catatan / Permintaan Khusus{' '}
                                    <span className="font-normal text-slate-400">
                                        (Opsional)
                                    </span>
                                </label>
                                <Textarea
                                    value={customerNotes}
                                    onChange={(e) =>
                                        setCustomerNotes(e.target.value)
                                    }
                                    rows={3}
                                    placeholder="Misal: preferensi ruangan non-smoking, terapis wanita, dll."
                                    className="text-sm"
                                />
                            </div>

                            {/* Dynamic Custom Fields (PRD 26, 27) */}
                            {activeForm?.fields &&
                                activeForm.fields.length > 0 && (
                                    <div className="space-y-4 border-t border-slate-100 pt-4">
                                        <div className="flex items-center justify-between">
                                            <h3 className="text-xs font-bold tracking-wider text-slate-500 uppercase">
                                                {activeForm.name ||
                                                    'Informasi Tambahan'}
                                            </h3>
                                            <span className="text-[10px] text-slate-400">
                                                Formulir Khusus Layanan
                                            </span>
                                        </div>

                                        {activeForm.fields
                                            .filter(
                                                (f) =>
                                                    f.is_active &&
                                                    isFieldVisible(
                                                        f,
                                                        customFieldValues
                                                    )
                                            )
                                            .map((field) => (
                                                <CustomFormFieldInput
                                                    key={field.id}
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
                                                    error={
                                                        formErrors[
                                                            `custom_${field.field_key}`
                                                        ]
                                                    }
                                                    onChange={(key, val) => {
                                                        setCustomFieldValues(
                                                            (prev) => ({
                                                                ...prev,
                                                                [key]: val,
                                                            })
                                                        );
                                                        if (
                                                            formErrors[
                                                                `custom_${key}`
                                                            ]
                                                        ) {
                                                            setFormErrors(
                                                                (prev) => {
                                                                    const copy =
                                                                        {
                                                                            ...prev,
                                                                        };
                                                                    delete copy[
                                                                        `custom_${key}`
                                                                    ];
                                                                    return copy;
                                                                }
                                                            );
                                                        }
                                                    }}
                                                    onFileChange={(
                                                        key,
                                                        file
                                                    ) => {
                                                        setCustomFieldFiles(
                                                            (prev) => ({
                                                                ...prev,
                                                                [key]: file,
                                                            })
                                                        );
                                                        if (
                                                            formErrors[
                                                                `custom_${key}`
                                                            ]
                                                        ) {
                                                            setFormErrors(
                                                                (prev) => {
                                                                    const copy =
                                                                        {
                                                                            ...prev,
                                                                        };
                                                                    delete copy[
                                                                        `custom_${key}`
                                                                    ];
                                                                    return copy;
                                                                }
                                                            );
                                                        }
                                                    }}
                                                />
                                            ))}
                                    </div>
                                )}
                        </div>

                        {/* Navigation */}
                        <div className="mt-8 flex items-center justify-between border-t border-slate-100 pt-4">
                            <Button
                                variant="secondary"
                                size="md"
                                onClick={() => setStep('datetime')}
                            >
                                <ArrowLeft className="mr-1.5 h-4 w-4" />
                                Ubah Jadwal
                            </Button>
                            <Button
                                variant="primary"
                                size="md"
                                onClick={() => {
                                    if (validateCustomerStep()) {
                                        setStep('review');
                                    }
                                }}
                            >
                                Tinjau Reservasi
                                <ArrowRight className="ml-1.5 h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                )}

                {/* ================= STEP 5: REVIEW & KONFIRMASI ================= */}
                {step === 'review' && (
                    <div className="space-y-4">
                        <div className="rounded-[14px] border border-slate-200 bg-white p-5 shadow-xs sm:p-6">
                            <h2 className="text-base font-bold text-slate-900 sm:text-lg">
                                Ringkasan & Konfirmasi Reservasi
                            </h2>
                            <p className="mt-1 text-xs text-slate-500">
                                Periksa kembali data pemesanan Anda sebelum
                                konfirmasi dikirim.
                            </p>

                            {/* Summary Card */}
                            <div className="mt-5 space-y-3.5 rounded-xl border border-slate-100 bg-slate-50 p-4 text-xs">
                                <div className="flex items-start justify-between">
                                    <span className="text-slate-500">
                                        Layanan:
                                    </span>
                                    <div className="text-right">
                                        <strong className="block font-bold text-slate-900">
                                            {activeService?.name}
                                        </strong>
                                        {activeVariant && (
                                            <span className="block text-[11px] text-slate-500">
                                                Varian: {activeVariant.name}
                                            </span>
                                        )}
                                        {activeAddons.map((ad) => (
                                            <span
                                                key={ad.id}
                                                className="block text-[11px] text-slate-500"
                                            >
                                                + {ad.name}
                                            </span>
                                        ))}
                                    </div>
                                </div>

                                <div className="flex items-center justify-between border-t border-slate-200/60 pt-2.5">
                                    <span className="text-slate-500">
                                        Petugas / Staf:
                                    </span>
                                    <span className="font-semibold text-slate-900">
                                        {activeStaff
                                            ? activeStaff.name
                                            : 'Siapa Saja (Otomatis)'}
                                    </span>
                                </div>

                                <div className="flex items-center justify-between border-t border-slate-200/60 pt-2.5">
                                    <span className="text-slate-500">
                                        Waktu Kedatangan:
                                    </span>
                                    <div className="text-right">
                                        <strong className="block font-bold text-slate-900">
                                            {selectedDate},{' '}
                                            {selectedSlot?.start_time} -{' '}
                                            {selectedSlot?.end_time}
                                        </strong>
                                        <span className="text-[11px] font-medium text-blue-600">
                                            {timezoneLabel} (Durasi:{' '}
                                            {totalDuration} mnt)
                                        </span>
                                    </div>
                                </div>

                                <div className="flex items-center justify-between border-t border-slate-200/60 pt-2.5">
                                    <span className="text-slate-500">
                                        Nama Pemesan:
                                    </span>
                                    <span className="font-semibold text-slate-900">
                                        {customerName}
                                    </span>
                                </div>

                                <div className="flex items-center justify-between border-t border-slate-200/60 pt-2.5">
                                    <span className="text-slate-500">
                                        Nomor WhatsApp:
                                    </span>
                                    <span className="font-semibold text-slate-900">
                                        {customerPhone}
                                    </span>
                                </div>

                                {customerEmail && (
                                    <div className="flex items-center justify-between border-t border-slate-200/60 pt-2.5">
                                        <span className="text-slate-500">
                                            Email:
                                        </span>
                                        <span className="text-slate-700">
                                            {customerEmail}
                                        </span>
                                    </div>
                                )}

                                {customerNotes && (
                                    <div className="flex items-start justify-between border-t border-slate-200/60 pt-2.5">
                                        <span className="text-slate-500">
                                            Catatan:
                                        </span>
                                        <span className="max-w-[240px] text-right italic text-slate-700">
                                            &ldquo;{customerNotes}&rdquo;
                                        </span>
                                    </div>
                                )}

                                {/* Custom Form Fields Review */}
                                {activeForm?.fields &&
                                    activeForm.fields
                                        .filter(
                                            (f) =>
                                                f.is_active &&
                                                isFieldVisible(
                                                    f,
                                                    customFieldValues
                                                )
                                        )
                                        .map((f) => {
                                            const val =
                                                customFieldValues[f.field_key];
                                            const file =
                                                customFieldFiles[f.field_key];
                                            if (
                                                (val === undefined ||
                                                    val === null ||
                                                    val === '') &&
                                                !file
                                            ) {
                                                return null;
                                            }
                                            const displayVal = file
                                                ? file.name
                                                : Array.isArray(val)
                                                  ? val.join(', ')
                                                  : typeof val === 'boolean'
                                                    ? val
                                                        ? 'Ya'
                                                        : 'Tidak'
                                                    : String(val);
                                            return (
                                                <div
                                                    key={f.id}
                                                    className="flex items-start justify-between border-t border-slate-200/60 pt-2.5"
                                                >
                                                    <span className="text-slate-500">
                                                        {f.label}:
                                                    </span>
                                                    <span className="max-w-[240px] text-right font-medium text-slate-800">
                                                        {displayVal}
                                                    </span>
                                                </div>
                                            );
                                        })}

                                <div className="flex items-center justify-between border-t border-slate-200 pt-3 text-sm">
                                    <span className="font-bold text-slate-900">
                                        Total Biaya:
                                    </span>
                                    <span className="text-base font-black text-blue-600">
                                        {formatRp(totalPrice)}
                                    </span>
                                </div>
                            </div>

                            {/* Trust Badge */}
                            <div className="mt-4 flex items-center gap-2 rounded-lg bg-emerald-50 px-3.5 py-2 text-xs text-emerald-800">
                                <ShieldCheck className="h-4 w-4 shrink-0 text-emerald-600" />
                                <span>
                                    Konfirmasi instan. Tidak ada biaya pemesanan
                                    awal. Pembayaran di lokasi.
                                </span>
                            </div>

                            {/* Action Buttons */}
                            <div className="mt-6 flex flex-col gap-2.5 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                                <Button
                                    variant="secondary"
                                    size="md"
                                    disabled={isSubmitting}
                                    onClick={() => setStep('customer')}
                                    className="order-2 sm:order-1"
                                >
                                    <ArrowLeft className="mr-1.5 h-4 w-4" />
                                    Ubah Data
                                </Button>

                                <Button
                                    variant="primary"
                                    size="lg"
                                    disabled={isSubmitting}
                                    onClick={handleSubmitBooking}
                                    className="order-1 w-full min-w-[200px] sm:order-2 sm:w-auto"
                                >
                                    {isSubmitting ? (
                                        <>
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            Memproses Booking...
                                        </>
                                    ) : (
                                        <>
                                            <Lock className="mr-1.5 h-4 w-4" />
                                            Konfirmasi Booking Sekarang
                                        </>
                                    )}
                                </Button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </PublicLayout>
    );
}

// TimeSlotButton subcomponent
function TimeSlotButton({
    slot,
    isSelected,
    onSelect,
}: {
    slot: TimeSlot;
    isSelected: boolean;
    onSelect: () => void;
}) {
    if (!slot.available) {
        return (
            <button
                type="button"
                disabled
                title={slot.message || 'Slot waktu tidak tersedia'}
                className="flex cursor-not-allowed flex-col items-center justify-center rounded-lg border border-slate-200 bg-slate-100/70 px-1 py-2 text-xs text-slate-400 line-through opacity-60"
            >
                <span className="font-semibold">{slot.start_time}</span>
            </button>
        );
    }

    return (
        <button
            type="button"
            onClick={onSelect}
            className={`flex flex-col items-center justify-center rounded-lg border px-1 py-2 text-xs transition-all ${
                isSelected
                    ? 'border-blue-600 bg-blue-600 font-bold text-white shadow-xs'
                    : 'border-slate-200 bg-white text-slate-800 hover:border-blue-400 hover:bg-blue-50/30'
            }`}
        >
            <span className="font-bold">{slot.start_time}</span>
        </button>
    );
}
