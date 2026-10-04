import { useForm } from '@inertiajs/react';
import { Check, Clock, Plus, Trash2 } from 'lucide-react';
import React from 'react';
import { Badge, Button, Input, useToast } from '../../../Components/ui';
import { OwnerLayout } from '../../../Layouts/OwnerLayout';

interface BreakTime {
    name: string;
    start_time: string;
    end_time: string;
}

interface DayHour {
    id?: number;
    day_of_week: number;
    is_open: boolean;
    open_time: string;
    close_time: string;
    breaks: BreakTime[];
}

interface OperatingHoursProps {
    hours: DayHour[];
    business: {
        id: number;
        name: string;
        timezone: string;
    };
}

const dayNames = [
    'Minggu',
    'Senin',
    'Selasa',
    'Rabu',
    'Kamis',
    'Jumat',
    'Sabtu',
];

export default function OperatingHours({
    hours,
    business,
}: OperatingHoursProps) {
    const toast = useToast();

    // Sort Sunday (0) to end or display Monday (1) to Sunday (0)
    const sortedHours = [...hours].sort((a, b) => {
        const orderA = a.day_of_week === 0 ? 7 : a.day_of_week;
        const orderB = b.day_of_week === 0 ? 7 : b.day_of_week;
        return orderA - orderB;
    });

    const { data, setData, put, processing } = useForm<{ hours: DayHour[] }>({
        hours: sortedHours,
    });

    const handleToggleOpen = (index: number) => {
        const updated = [...data.hours];
        updated[index].is_open = !updated[index].is_open;
        setData('hours', updated);
    };

    const handleTimeChange = (
        index: number,
        field: 'open_time' | 'close_time',
        val: string
    ) => {
        const updated = [...data.hours];
        updated[index][field] = val;
        setData('hours', updated);
    };

    const handleAddBreak = (dayIndex: number) => {
        const updated = [...data.hours];
        updated[dayIndex].breaks = [
            ...(updated[dayIndex].breaks || []),
            { name: 'Istirahat Siang', start_time: '12:00', end_time: '13:00' },
        ];
        setData('hours', updated);
    };

    const handleRemoveBreak = (dayIndex: number, breakIndex: number) => {
        const updated = [...data.hours];
        updated[dayIndex].breaks = updated[dayIndex].breaks.filter(
            (_, idx) => idx !== breakIndex
        );
        setData('hours', updated);
    };

    const handleBreakChange = (
        dayIndex: number,
        breakIndex: number,
        field: keyof BreakTime,
        val: string
    ) => {
        const updated = [...data.hours];
        updated[dayIndex].breaks[breakIndex][field] = val;
        setData('hours', updated);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put('/app/settings/hours', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Jadwal jam operasional berhasil diperbarui.');
            },
            onError: () => {
                toast.error('Gagal memperbarui jadwal. Pastikan jam valid.');
            },
        });
    };

    return (
        <OwnerLayout
            title="Jam Operasional Bisnis"
            breadcrumbs={[
                { label: 'Workspace', href: '/app/dashboard' },
                { label: 'Jam Operasional' },
            ]}
        >
            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-base font-bold text-slate-900">
                                Jadwal Jam Operasional & Istirahat
                            </h1>
                            <Badge variant="hauling" size="sm">
                                {business.timezone}
                            </Badge>
                        </div>
                        <p className="text-xs text-slate-500">
                            Atur hari buka, jam operasional, dan jeda istirahat
                            harian usaha Anda. Sistem booking hanya akan membuka
                            slot di dalam jam buka dan di luar waktu istirahat.
                        </p>
                    </div>

                    <Button
                        variant="primary"
                        onClick={handleSubmit}
                        isLoading={processing}
                        leftIcon={<Check className="h-3.5 w-3.5" />}
                    >
                        Simpan Jadwal
                    </Button>
                </div>

                {/* 7 Days Schedule Cards */}
                <form onSubmit={handleSubmit} className="space-y-4">
                    {data.hours.map((day, idx) => {
                        const dayName = dayNames[day.day_of_week];

                        return (
                            <div
                                key={day.day_of_week}
                                className={`rounded-[12px] border bg-white p-4 transition-all sm:p-5 ${
                                    day.is_open
                                        ? 'border-slate-200 shadow-xs'
                                        : 'border-slate-100 bg-slate-50/60 opacity-80'
                                }`}
                            >
                                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                                    {/* Left: Day Name & Toggle */}
                                    <div className="flex w-44 items-center gap-3">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                handleToggleOpen(idx)
                                            }
                                            className={`relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus-visible:outline-2 focus-visible:outline-blue-600 ${
                                                day.is_open
                                                    ? 'bg-blue-600'
                                                    : 'bg-slate-300'
                                            }`}
                                            role="switch"
                                            aria-checked={day.is_open}
                                            aria-label={`Toggle hari ${dayName}`}
                                        >
                                            <span
                                                className={`pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out ${
                                                    day.is_open
                                                        ? 'translate-x-4'
                                                        : 'translate-x-0'
                                                }`}
                                            />
                                        </button>

                                        <div>
                                            <span className="block text-xs font-bold text-slate-900">
                                                {dayName}
                                            </span>
                                            <span className="block text-[11px] text-slate-400">
                                                {day.is_open ? 'Buka' : 'Tutup'}
                                            </span>
                                        </div>
                                    </div>

                                    {/* Middle: Open & Close Time */}
                                    {day.is_open ? (
                                        <div className="flex flex-wrap items-center gap-3">
                                            <div className="flex items-center gap-1.5">
                                                <Clock className="h-3.5 w-3.5 text-slate-400" />
                                                <input
                                                    type="time"
                                                    value={day.open_time}
                                                    onChange={(e) =>
                                                        handleTimeChange(
                                                            idx,
                                                            'open_time',
                                                            e.target.value
                                                        )
                                                    }
                                                    className="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-900 focus:border-blue-600 focus:outline-none"
                                                />
                                            </div>

                                            <span className="text-xs text-slate-400">
                                                s/d
                                            </span>

                                            <div className="flex items-center gap-1.5">
                                                <input
                                                    type="time"
                                                    value={day.close_time}
                                                    onChange={(e) =>
                                                        handleTimeChange(
                                                            idx,
                                                            'close_time',
                                                            e.target.value
                                                        )
                                                    }
                                                    className="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-900 focus:border-blue-600 focus:outline-none"
                                                />
                                            </div>

                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    handleAddBreak(idx)
                                                }
                                                leftIcon={
                                                    <Plus className="h-3 w-3" />
                                                }
                                            >
                                                Tambah Istirahat
                                            </Button>
                                        </div>
                                    ) : (
                                        <div className="text-xs text-slate-400 italic">
                                            Tidak menerima reservasi di hari
                                            ini.
                                        </div>
                                    )}
                                </div>

                                {/* Bottom: Breaks Interval List */}
                                {day.is_open &&
                                    day.breaks &&
                                    day.breaks.length > 0 && (
                                        <div className="mt-4 space-y-2 border-t border-slate-100 pt-3">
                                            <p className="text-[11px] font-semibold tracking-wider text-slate-600 uppercase">
                                                Jeda Istirahat (Break Time):
                                            </p>
                                            {day.breaks.map((brk, bIdx) => (
                                                <div
                                                    key={bIdx}
                                                    className="flex flex-wrap items-center gap-2 rounded-lg border border-slate-200/60 bg-slate-50 p-2"
                                                >
                                                    <Input
                                                        placeholder="Nama jeda (e.g. Istirahat Siang)"
                                                        value={brk.name}
                                                        onChange={(e) =>
                                                            handleBreakChange(
                                                                idx,
                                                                bIdx,
                                                                'name',
                                                                e.target.value
                                                            )
                                                        }
                                                        className="max-w-xs"
                                                    />
                                                    <div className="flex items-center gap-1.5">
                                                        <input
                                                            type="time"
                                                            value={
                                                                brk.start_time
                                                            }
                                                            onChange={(e) =>
                                                                handleBreakChange(
                                                                    idx,
                                                                    bIdx,
                                                                    'start_time',
                                                                    e.target
                                                                        .value
                                                                )
                                                            }
                                                            className="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-900 focus:border-blue-600 focus:outline-none"
                                                        />
                                                        <span className="text-xs text-slate-400">
                                                            -
                                                        </span>
                                                        <input
                                                            type="time"
                                                            value={brk.end_time}
                                                            onChange={(e) =>
                                                                handleBreakChange(
                                                                    idx,
                                                                    bIdx,
                                                                    'end_time',
                                                                    e.target
                                                                        .value
                                                                )
                                                            }
                                                            className="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-900 focus:border-blue-600 focus:outline-none"
                                                        />
                                                    </div>

                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleRemoveBreak(
                                                                idx,
                                                                bIdx
                                                            )
                                                        }
                                                        className="ml-auto p-1 text-slate-400 transition-colors hover:text-rose-600"
                                                        aria-label="Hapus jeda istirahat"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                            </div>
                        );
                    })}

                    {/* Bottom Save Action */}
                    <div className="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
                        <Button
                            type="submit"
                            variant="primary"
                            isLoading={processing}
                            leftIcon={<Check className="h-4 w-4" />}
                        >
                            Simpan Jadwal Operasional
                        </Button>
                    </div>
                </form>
            </div>
        </OwnerLayout>
    );
}
