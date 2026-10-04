import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CalendarOff, Plus, Save, Trash2, X } from 'lucide-react';
import React, { useState } from 'react';
import { Button, Input, useToast } from '../../../Components/ui';
import { OwnerLayout } from '../../../Layouts/OwnerLayout';

interface ResourceTypeItem {
    id: number;
    code: string;
    name: string;
    icon: string;
    is_staff: boolean;
    is_space: boolean;
    is_equipment: boolean;
}

interface ResourceGroupItem {
    id: number;
    name: string;
    description: string | null;
}

interface BreakItem {
    start: string;
    end: string;
    title?: string;
}

interface DaySchedule {
    day_of_week: number;
    is_available: boolean;
    start_time: string;
    end_time: string;
    breaks: BreakItem[];
}

interface TimeBlockItem {
    id: number;
    start_at: string;
    end_at: string;
    reason: string;
    is_all_resources: boolean;
}

interface ResourceDetail {
    id: number;
    uuid: string;
    name: string;
    code: string | null;
    resource_type_id: number;
    group_id: number | null;
    capacity: number;
    visibility: 'PUBLIC' | 'INTERNAL';
    state: 'AVAILABLE' | 'BLOCKED' | 'MAINTENANCE' | 'INACTIVE';
    skills: string[];
    metadata: Record<string, unknown> | null;
    is_archived: boolean;
    archived_at: string | null;
    schedules: DaySchedule[];
    time_blocks: TimeBlockItem[];
}

interface FormProps {
    resource: ResourceDetail | null;
    resource_types: ResourceTypeItem[];
    resource_groups: ResourceGroupItem[];
    default_schedules?: DaySchedule[];
    quota?: {
        current: number;
        limit: number;
        remaining: number;
        is_unlimited: boolean;
    };
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

const SKILL_PRESETS = [
    'Massage',
    'Reflexology',
    'Hot Stone',
    'Facial',
    'Creambath',
    'Haircut',
    'Coloring',
    'Manicure',
    'Pedicure',
    'Konsultasi',
];

export default function ResourceForm({
    resource,
    resource_types,
    resource_groups,
    default_schedules,
}: FormProps) {
    const isEdit = !!resource;
    const toast = useToast();

    // Basic Fields
    const [name, setName] = useState(resource?.name || '');
    const [code, setCode] = useState(resource?.code || '');
    const [typeId, setTypeId] = useState<number>(
        resource?.resource_type_id ||
            (resource_types.length > 0 ? resource_types[0].id : 1)
    );
    const [groupId, setGroupId] = useState<number | ''>(
        resource?.group_id ?? ''
    );
    const [capacity, setCapacity] = useState<number>(resource?.capacity ?? 1);
    const [visibility, setVisibility] = useState<'PUBLIC' | 'INTERNAL'>(
        resource?.visibility || 'PUBLIC'
    );
    const [state, setState] = useState<
        'AVAILABLE' | 'BLOCKED' | 'MAINTENANCE' | 'INACTIVE'
    >(resource?.state || 'AVAILABLE');

    // Skills
    const [skills, setSkills] = useState<string[]>(resource?.skills || []);
    const [newSkillInput, setNewSkillInput] = useState('');

    // Schedules
    const initialSchedules: DaySchedule[] = resource?.schedules ||
        default_schedules || [
            {
                day_of_week: 0,
                is_available: false,
                start_time: '09:00',
                end_time: '17:00',
                breaks: [],
            },
            {
                day_of_week: 1,
                is_available: true,
                start_time: '09:00',
                end_time: '17:00',
                breaks: [],
            },
            {
                day_of_week: 2,
                is_available: true,
                start_time: '09:00',
                end_time: '17:00',
                breaks: [],
            },
            {
                day_of_week: 3,
                is_available: true,
                start_time: '09:00',
                end_time: '17:00',
                breaks: [],
            },
            {
                day_of_week: 4,
                is_available: true,
                start_time: '09:00',
                end_time: '17:00',
                breaks: [],
            },
            {
                day_of_week: 5,
                is_available: true,
                start_time: '09:00',
                end_time: '17:00',
                breaks: [],
            },
            {
                day_of_week: 6,
                is_available: true,
                start_time: '09:00',
                end_time: '17:00',
                breaks: [],
            },
        ];
    const [schedules, setSchedules] = useState<DaySchedule[]>(initialSchedules);

    const [isSubmitting, setIsSubmitting] = useState(false);

    // Skill handlers
    const addSkill = (skillName: string) => {
        const trimmed = skillName.trim();
        if (!trimmed) return;
        if (skills.some((s) => s.toLowerCase() === trimmed.toLowerCase())) {
            toast.error(`Skill '${trimmed}' sudah ada.`);
            return;
        }
        setSkills([...skills, trimmed]);
        setNewSkillInput('');
    };

    const removeSkill = (index: number) => {
        setSkills(skills.filter((_, i) => i !== index));
    };

    // Schedule handlers
    const updateSchedule = (
        dayIndex: number,
        updates: Partial<DaySchedule>
    ) => {
        setSchedules((prev) =>
            prev.map((sch) =>
                sch.day_of_week === dayIndex ? { ...sch, ...updates } : sch
            )
        );
    };

    const addBreak = (dayIndex: number) => {
        setSchedules((prev) =>
            prev.map((sch) => {
                if (sch.day_of_week === dayIndex) {
                    const currentBreaks = sch.breaks || [];
                    return {
                        ...sch,
                        breaks: [
                            ...currentBreaks,
                            {
                                start: '12:00',
                                end: '13:00',
                                title: 'Istirahat',
                            },
                        ],
                    };
                }
                return sch;
            })
        );
    };

    const updateBreak = (
        dayIndex: number,
        breakIndex: number,
        field: keyof BreakItem,
        val: string
    ) => {
        setSchedules((prev) =>
            prev.map((sch) => {
                if (sch.day_of_week === dayIndex) {
                    const nextBreaks = [...(sch.breaks || [])];
                    nextBreaks[breakIndex] = {
                        ...nextBreaks[breakIndex],
                        [field]: val,
                    };
                    return { ...sch, breaks: nextBreaks };
                }
                return sch;
            })
        );
    };

    const removeBreak = (dayIndex: number, breakIndex: number) => {
        setSchedules((prev) =>
            prev.map((sch) => {
                if (sch.day_of_week === dayIndex) {
                    return {
                        ...sch,
                        breaks: (sch.breaks || []).filter(
                            (_, i) => i !== breakIndex
                        ),
                    };
                }
                return sch;
            })
        );
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!name.trim()) {
            toast.error('Nama resource wajib diisi.');
            return;
        }

        setIsSubmitting(true);

        const payload = {
            name: name.trim(),
            code: code.trim() || null,
            resource_type_id: typeId,
            group_id: groupId ? Number(groupId) : null,
            capacity: Number(capacity) || 1,
            visibility,
            state,
            skills,
            schedules: schedules.map((s) => ({
                day_of_week: s.day_of_week,
                is_available: s.is_available,
                start_time: s.start_time,
                end_time: s.end_time,
                breaks: s.breaks || [],
            })),
        };

        if (isEdit) {
            router.put(`/app/resources/${resource.id}`, payload, {
                onSuccess: () => {
                    toast.success('Resource berhasil diperbarui.');
                },
                onError: (errors) => {
                    const firstMsg = Object.values(errors)[0] as string;
                    toast.error(firstMsg || 'Gagal menyimpan resource.');
                },
                onFinish: () => setIsSubmitting(false),
            });
        } else {
            router.post('/app/resources', payload, {
                onSuccess: () => {
                    toast.success('Resource berhasil ditambahkan.');
                },
                onError: (errors) => {
                    const firstMsg = Object.values(errors)[0] as string;
                    toast.error(firstMsg || 'Gagal menambahkan resource.');
                },
                onFinish: () => setIsSubmitting(false),
            });
        }
    };

    return (
        <OwnerLayout
            title={isEdit ? `Edit: ${resource.name}` : 'Tambah Resource'}
            breadcrumbs={[
                { label: 'Dashboard', href: '/app/dashboard' },
                { label: 'Resource & Tim', href: '/app/resources' },
                { label: isEdit ? 'Edit Resource' : 'Tambah Baru' },
            ]}
        >
            <Head
                title={`${isEdit ? 'Edit Resource' : 'Tambah Resource'} - AMAN BOOKING`}
            />

            <form
                onSubmit={handleSubmit}
                className="mx-auto max-w-4xl space-y-6 pb-12"
            >
                {/* Header Action Bar */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Link href="/app/resources">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft className="h-4 w-4 text-slate-600" />
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-lg font-semibold text-slate-900">
                                {isEdit
                                    ? `Edit Resource: ${resource.name}`
                                    : 'Tambah Resource Baru'}
                            </h1>
                            <p className="text-xs text-slate-500">
                                Atur profil, keahlian khusus, dan jadwal shift
                                kerja mingguan.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Link href="/app/resources">
                            <Button variant="outline" size="sm" type="button">
                                Batal
                            </Button>
                        </Link>
                        <Button
                            variant="primary"
                            size="sm"
                            type="submit"
                            isLoading={isSubmitting}
                            leftIcon={<Save className="h-4 w-4" />}
                        >
                            {isEdit ? 'Simpan Perubahan' : 'Buat Resource'}
                        </Button>
                    </div>
                </div>

                {/* Section 1: Informasi Dasar */}
                <div className="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="border-b border-slate-100 pb-2 text-sm font-semibold text-slate-900">
                        1. Informasi Dasar
                    </h2>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Nama Resource / Tim / Ruang{' '}
                                <span className="text-rose-500">*</span>
                            </label>
                            <Input
                                placeholder="Contoh: Anita (Therapist), Ruang Spa 1, Lapangan Futsal A"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                required
                                className="mt-1 text-xs"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Kode / Unit ID (Opsional)
                            </label>
                            <Input
                                placeholder="Contoh: STF-01, RM-01, CRT-A"
                                value={code}
                                onChange={(e) => setCode(e.target.value)}
                                className="mt-1 font-mono text-xs"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Tipe Resource{' '}
                                <span className="text-rose-500">*</span>
                            </label>
                            <select
                                value={typeId}
                                onChange={(e) =>
                                    setTypeId(Number(e.target.value))
                                }
                                className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-blue-500 focus:outline-none"
                                required
                            >
                                {resource_types.map((type) => (
                                    <option key={type.id} value={type.id}>
                                        {type.name} ({type.code})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Grup / Pool (Opsional)
                            </label>
                            <select
                                value={groupId}
                                onChange={(e) =>
                                    setGroupId(
                                        e.target.value
                                            ? Number(e.target.value)
                                            : ''
                                    )
                                }
                                className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-blue-500 focus:outline-none"
                            >
                                <option value="">
                                    Tanpa Grup (Stand-alone)
                                </option>
                                {resource_groups.map((group) => (
                                    <option key={group.id} value={group.id}>
                                        {group.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Kapasitas Simultan{' '}
                                <span className="text-rose-500">*</span>
                            </label>
                            <Input
                                type="number"
                                min={1}
                                max={1000}
                                value={capacity}
                                onChange={(e) =>
                                    setCapacity(
                                        Math.max(1, Number(e.target.value))
                                    )
                                }
                                required
                                className="mt-1 text-xs"
                            />
                            <p className="mt-1 text-[11px] text-slate-400">
                                Jumlah customer maksimal yang dapat dilayani
                                sekaligus pada slot waktu yang sama.
                            </p>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Visibilitas Penugasan{' '}
                                <span className="text-rose-500">*</span>
                            </label>
                            <select
                                value={visibility}
                                onChange={(e) =>
                                    setVisibility(
                                        e.target.value as 'PUBLIC' | 'INTERNAL'
                                    )
                                }
                                className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-blue-500 focus:outline-none"
                            >
                                <option value="PUBLIC">
                                    PUBLIC — Customer dapat memilih resource ini
                                    langsung
                                </option>
                                <option value="INTERNAL">
                                    INTERNAL — Hanya untuk penugasan sistem /
                                    admin
                                </option>
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700">
                                Kondisi / Status Operasional{' '}
                                <span className="text-rose-500">*</span>
                            </label>
                            <select
                                value={state}
                                onChange={(e) =>
                                    setState(
                                        e.target.value as
                                            | 'AVAILABLE'
                                            | 'BLOCKED'
                                            | 'MAINTENANCE'
                                            | 'INACTIVE'
                                    )
                                }
                                className="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 focus:border-blue-500 focus:outline-none"
                            >
                                <option value="AVAILABLE">
                                    Tersedia (Siap melayani booking)
                                </option>
                                <option value="MAINTENANCE">
                                    Maintenance (Dalam perbaikan / servis)
                                </option>
                                <option value="BLOCKED">
                                    Terblokir Sementara
                                </option>
                                <option value="INACTIVE">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                </div>

                {/* Section 2: Keahlian & Spesialisasi (Skill Compatibility) */}
                <div className="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                        <div>
                            <h2 className="text-sm font-semibold text-slate-900">
                                2. Keahlian & Spesialisasi (Skill Rules PRD 160)
                            </h2>
                            <p className="text-xs text-slate-500">
                                Layanan yang mensyaratkan keahlian tertentu
                                hanya akan menugaskan resource yang memiliki
                                skill ini.
                            </p>
                        </div>
                    </div>

                    <div className="space-y-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <Input
                                placeholder="Ketik keahlian (contoh: Hot Stone, Reflexology)..."
                                value={newSkillInput}
                                onChange={(e) =>
                                    setNewSkillInput(e.target.value)
                                }
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        addSkill(newSkillInput);
                                    }
                                }}
                                className="w-64 text-xs"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => addSkill(newSkillInput)}
                                leftIcon={<Plus className="h-3.5 w-3.5" />}
                            >
                                Tambah Skill
                            </Button>
                        </div>

                        {/* Preset Suggestions */}
                        <div className="flex flex-wrap items-center gap-1.5 pt-1">
                            <span className="text-[11px] text-slate-400">
                                Rekomendasi cepat:
                            </span>
                            {SKILL_PRESETS.map((preset) => (
                                <button
                                    key={preset}
                                    type="button"
                                    onClick={() => addSkill(preset)}
                                    className="rounded border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] text-slate-600 transition-colors hover:bg-blue-50 hover:text-blue-700"
                                >
                                    + {preset}
                                </button>
                            ))}
                        </div>

                        {/* Selected Skills */}
                        <div className="flex flex-wrap gap-2 pt-2">
                            {skills.length === 0 ? (
                                <span className="text-xs text-slate-400 italic">
                                    Belum ada skill yang ditambahkan. Resource
                                    ini dapat menangani semua layanan umum.
                                </span>
                            ) : (
                                skills.map((skill, index) => (
                                    <span
                                        key={index}
                                        className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700"
                                    >
                                        {skill}
                                        <button
                                            type="button"
                                            onClick={() => removeSkill(index)}
                                            className="text-blue-500 hover:text-blue-800"
                                        >
                                            <X className="h-3 w-3" />
                                        </button>
                                    </span>
                                ))
                            )}
                        </div>
                    </div>
                </div>

                {/* Section 3: Jadwal Kerja Mingguan & Jam Istirahat */}
                <div className="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="border-b border-slate-100 pb-2">
                        <h2 className="text-sm font-semibold text-slate-900">
                            3. Jadwal Kerja Mingguan & Istirahat (Resource
                            Schedules)
                        </h2>
                        <p className="text-xs text-slate-500">
                            Tentukan jam kerja operasional dan periode istirahat
                            harian untuk resource ini.
                        </p>
                    </div>

                    <div className="divide-y divide-slate-100">
                        {schedules.map((daySch) => {
                            const dayName = DAY_NAMES[daySch.day_of_week];

                            return (
                                <div
                                    key={daySch.day_of_week}
                                    className={`py-3 transition-colors ${
                                        !daySch.is_available
                                            ? 'bg-slate-50/50 opacity-75'
                                            : ''
                                    }`}
                                >
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="flex items-center gap-3">
                                            <input
                                                type="checkbox"
                                                id={`day_${daySch.day_of_week}`}
                                                checked={daySch.is_available}
                                                onChange={(e) =>
                                                    updateSchedule(
                                                        daySch.day_of_week,
                                                        {
                                                            is_available:
                                                                e.target
                                                                    .checked,
                                                        }
                                                    )
                                                }
                                                className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                            />
                                            <label
                                                htmlFor={`day_${daySch.day_of_week}`}
                                                className="w-24 cursor-pointer text-xs font-semibold text-slate-800"
                                            >
                                                {dayName}
                                            </label>

                                            <span
                                                className={`rounded-full px-2 py-0.5 text-[11px] font-medium ${
                                                    daySch.is_available
                                                        ? 'bg-emerald-50 text-emerald-700'
                                                        : 'bg-slate-100 text-slate-500'
                                                }`}
                                            >
                                                {daySch.is_available
                                                    ? 'Buka'
                                                    : 'Libur'}
                                            </span>
                                        </div>

                                        {daySch.is_available && (
                                            <div className="flex flex-wrap items-center gap-2">
                                                <div className="flex items-center gap-1.5 text-xs text-slate-600">
                                                    <span>Jam:</span>
                                                    <Input
                                                        type="time"
                                                        value={
                                                            daySch.start_time
                                                        }
                                                        onChange={(e) =>
                                                            updateSchedule(
                                                                daySch.day_of_week,
                                                                {
                                                                    start_time:
                                                                        e.target
                                                                            .value,
                                                                }
                                                            )
                                                        }
                                                        className="h-8 w-24 text-xs"
                                                    />
                                                    <span>-</span>
                                                    <Input
                                                        type="time"
                                                        value={daySch.end_time}
                                                        onChange={(e) =>
                                                            updateSchedule(
                                                                daySch.day_of_week,
                                                                {
                                                                    end_time:
                                                                        e.target
                                                                            .value,
                                                                }
                                                            )
                                                        }
                                                        className="h-8 w-24 text-xs"
                                                    />
                                                </div>

                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        addBreak(
                                                            daySch.day_of_week
                                                        )
                                                    }
                                                    className="text-xs text-blue-600 hover:text-blue-700"
                                                >
                                                    + Istirahat
                                                </Button>
                                            </div>
                                        )}
                                    </div>

                                    {/* Breaks List */}
                                    {daySch.is_available &&
                                        daySch.breaks &&
                                        daySch.breaks.length > 0 && (
                                            <div className="mt-2.5 ml-8 space-y-1.5 border-l-2 border-amber-300 pl-3">
                                                {daySch.breaks.map(
                                                    (brk, bIdx) => (
                                                        <div
                                                            key={bIdx}
                                                            className="flex items-center gap-2 text-xs text-slate-600"
                                                        >
                                                            <span className="text-[11px] font-medium text-amber-700">
                                                                Istirahat:
                                                            </span>
                                                            <Input
                                                                type="time"
                                                                value={
                                                                    brk.start
                                                                }
                                                                onChange={(e) =>
                                                                    updateBreak(
                                                                        daySch.day_of_week,
                                                                        bIdx,
                                                                        'start',
                                                                        e.target
                                                                            .value
                                                                    )
                                                                }
                                                                className="h-7 w-20 text-[11px]"
                                                            />
                                                            <span>-</span>
                                                            <Input
                                                                type="time"
                                                                value={brk.end}
                                                                onChange={(e) =>
                                                                    updateBreak(
                                                                        daySch.day_of_week,
                                                                        bIdx,
                                                                        'end',
                                                                        e.target
                                                                            .value
                                                                    )
                                                                }
                                                                className="h-7 w-20 text-[11px]"
                                                            />
                                                            <Input
                                                                placeholder="Keterangan (mis. Istirahat Siang)"
                                                                value={
                                                                    brk.title ||
                                                                    ''
                                                                }
                                                                onChange={(e) =>
                                                                    updateBreak(
                                                                        daySch.day_of_week,
                                                                        bIdx,
                                                                        'title',
                                                                        e.target
                                                                            .value
                                                                    )
                                                                }
                                                                className="h-7 w-40 text-[11px]"
                                                            />
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    removeBreak(
                                                                        daySch.day_of_week,
                                                                        bIdx
                                                                    )
                                                                }
                                                                className="text-slate-400 hover:text-rose-600"
                                                            >
                                                                <Trash2 className="h-3.5 w-3.5" />
                                                            </button>
                                                        </div>
                                                    )
                                                )}
                                            </div>
                                        )}
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Section 4: Riwayat Time Block / Cuti (Edit mode only) */}
                {isEdit &&
                    resource?.time_blocks &&
                    resource.time_blocks.length > 0 && (
                        <div className="space-y-3 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                                <h2 className="text-sm font-semibold text-slate-900">
                                    4. Block Waktu & Cuti Terjadwal
                                </h2>
                                <Link href="/app/time-blocks">
                                    <span className="text-xs text-blue-600 hover:underline">
                                        Kelola Semua Time Block &rarr;
                                    </span>
                                </Link>
                            </div>

                            <div className="space-y-2">
                                {resource.time_blocks.map((tb) => (
                                    <div
                                        key={tb.id}
                                        className="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 p-2.5 text-xs text-slate-700"
                                    >
                                        <div className="flex items-center gap-2">
                                            <CalendarOff className="h-4 w-4 text-amber-600" />
                                            <span className="font-medium text-slate-900">
                                                {tb.reason}
                                            </span>
                                        </div>
                                        <span className="font-mono text-[11px] text-slate-500">
                                            {new Date(
                                                tb.start_at
                                            ).toLocaleDateString('id-ID')}{' '}
                                            -{' '}
                                            {new Date(
                                                tb.end_at
                                            ).toLocaleDateString('id-ID')}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                {/* Bottom Submit Actions */}
                <div className="flex justify-end gap-2 pt-2">
                    <Link href="/app/resources">
                        <Button variant="outline" size="sm" type="button">
                            Batal
                        </Button>
                    </Link>
                    <Button
                        variant="primary"
                        size="sm"
                        type="submit"
                        isLoading={isSubmitting}
                        leftIcon={<Save className="h-4 w-4" />}
                    >
                        {isEdit ? 'Simpan Perubahan' : 'Buat Resource'}
                    </Button>
                </div>
            </form>
        </OwnerLayout>
    );
}
