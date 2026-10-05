import { router } from '@inertiajs/react';
import {
    Activity,
    AlertCircle,
    ArrowDownRight,
    ArrowUpRight,
    BarChart3,
    Calendar,
    CheckCircle2,
    Clock,
    DollarSign,
    Download,
    Filter,
    Layers,
    PieChart,
    RefreshCw,
    TrendingUp,
    UserCheck,
    Users,
    XCircle,
} from 'lucide-react';
import React, { useState } from 'react';
import { Badge, Button } from '../../../Components/ui';
import { OwnerLayout } from '../../../Layouts/OwnerLayout';

interface SummaryMetrics {
    total_bookings: number;
    completed_bookings: number;
    confirmed_bookings: number;
    pending_bookings: number;
    checked_in_bookings: number;
    in_progress_bookings: number;
    cancelled_bookings: number;
    no_show_bookings: number;
    expired_bookings: number;
    total_revenue: number;
    new_customers_count: number;
    repeat_customers_count: number;
    average_utilization: number;
    date_from: string;
    date_to: string;
    preset: string;
}

interface ResourceUtilizationItem {
    id: number;
    name: string;
    code: string | null;
    role: string;
    booked_minutes: number;
    booked_hours: number;
    available_minutes: number;
    available_hours: number;
    utilization_rate: number;
}

interface ServiceMetricItem {
    id: number;
    name: string;
    category_name: string;
    total_bookings: number;
    completed_bookings: number;
    cancelled_bookings: number;
    cancellation_rate: number;
    total_revenue: number;
    total_duration_minutes: number;
    total_duration_hours: number;
}

interface DailyTrendItem {
    date: string;
    day_name: string;
    formatted_date: string;
    total_bookings: number;
    completed_bookings: number;
    cancelled_bookings: number;
    revenue: number;
}

interface FilterOptions {
    services: Array<{ id: number; name: string }>;
    resources: Array<{ id: number; name: string }>;
}

interface ReportsIndexProps {
    summary: SummaryMetrics;
    resources: ResourceUtilizationItem[];
    services: ServiceMetricItem[];
    daily_trends: DailyTrendItem[];
    filters: {
        preset: string;
        date_from: string;
        date_to: string;
        service_id: number | null;
        resource_id: number | null;
    };
    filter_options: FilterOptions;
    can_export: boolean;
}

export default function ReportsIndex({
    summary,
    resources,
    services,
    daily_trends,
    filters,
    filter_options,
    can_export,
}: ReportsIndexProps) {
    const [selectedPreset, setSelectedPreset] = useState<string>(filters.preset || '30d');
    const [dateFrom, setDateFrom] = useState<string>(filters.date_from || '');
    const [dateTo, setDateTo] = useState<string>(filters.date_to || '');
    const [serviceId, setServiceId] = useState<string>(filters.service_id ? String(filters.service_id) : '');
    const [resourceId, setResourceId] = useState<string>(filters.resource_id ? String(filters.resource_id) : '');
    const [activeTab, setActiveTab] = useState<'resources' | 'services' | 'trends' | 'breakdown'>('resources');

    const formatIdr = (val: number): string => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(val);
    };

    const formatDuration = (minutes: number): string => {
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        if (hours === 0) return `${mins} mnt`;
        if (mins === 0) return `${hours} jam`;
        return `${hours} jam ${mins} mnt`;
    };

    const applyFilter = (customParams?: Record<string, any>) => {
        const params: Record<string, any> = {
            preset: selectedPreset,
            date_from: dateFrom,
            date_to: dateTo,
            service_id: serviceId || undefined,
            resource_id: resourceId || undefined,
            ...customParams,
        };

        // Remove empty values
        Object.keys(params).forEach((key) => {
            if (params[key] === undefined || params[key] === '') {
                delete params[key];
            }
        });

        router.get('/app/reports', params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handlePresetChange = (preset: string) => {
        setSelectedPreset(preset);
        applyFilter({ preset, date_from: undefined, date_to: undefined });
    };

    const handleReset = () => {
        setSelectedPreset('30d');
        setServiceId('');
        setResourceId('');
        router.get('/app/reports', { preset: '30d' });
    };

    const handleExport = () => {
        if (!can_export) return;

        const params = new URLSearchParams();
        if (selectedPreset) params.set('preset', selectedPreset);
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo) params.set('date_to', dateTo);
        if (serviceId) params.set('service_id', serviceId);
        if (resourceId) params.set('resource_id', resourceId);

        window.location.href = `/app/reports/export?${params.toString()}`;
    };

    // Calculate max bookings for visual daily trend bars
    const maxDailyBookings = Math.max(1, ...daily_trends.map((d) => d.total_bookings));

    return (
        <OwnerLayout
            title="Laporan & Analitik"
            breadcrumbs={[
                { label: 'Dashboard', href: '/app/dashboard' },
                { label: 'Laporan', href: '/app/reports' },
            ]}
            actions={
                <Button
                    variant="outline"
                    onClick={handleExport}
                    disabled={!can_export}
                    className="flex items-center gap-2 border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:text-slate-900"
                    title={can_export ? 'Unduh data laporan dalam format CSV' : 'Membutuhkan izin report.export'}
                >
                    <Download className="h-4 w-4 text-slate-500" />
                    <span>Ekspor CSV</span>
                </Button>
            }
        >
            <div className="space-y-6 pb-12">
                {/* Header Information */}
                <div>
                    <h1 className="text-xl font-bold tracking-tight text-slate-900">
                        Laporan & Analitik Operasional
                    </h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Ringkasan performa pemesanan, pendapatan, utilisasi staf/resource, dan pelanggan pada periode terpilih.
                    </p>
                </div>

                {/* Filter Bar */}
                <div className="rounded-xl border border-slate-200 bg-white p-4">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        {/* Preset Buttons */}
                        <div className="flex flex-wrap items-center gap-1.5">
                            <span className="mr-1.5 flex items-center text-xs font-semibold uppercase tracking-wider text-slate-400">
                                <Calendar className="mr-1 h-3.5 w-3.5" /> Periode:
                            </span>
                            <button
                                type="button"
                                onClick={() => handlePresetChange('7d')}
                                className={`rounded-lg px-3 py-1.5 text-xs font-medium transition-colors ${
                                    selectedPreset === '7d'
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                }`}
                            >
                                7 Hari Terakhir
                            </button>
                            <button
                                type="button"
                                onClick={() => handlePresetChange('30d')}
                                className={`rounded-lg px-3 py-1.5 text-xs font-medium transition-colors ${
                                    selectedPreset === '30d'
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                }`}
                            >
                                30 Hari Terakhir
                            </button>
                            <button
                                type="button"
                                onClick={() => handlePresetChange('this_month')}
                                className={`rounded-lg px-3 py-1.5 text-xs font-medium transition-colors ${
                                    selectedPreset === 'this_month'
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                }`}
                            >
                                Bulan Ini
                            </button>
                            <button
                                type="button"
                                onClick={() => setSelectedPreset('custom')}
                                className={`rounded-lg px-3 py-1.5 text-xs font-medium transition-colors ${
                                    selectedPreset === 'custom'
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                }`}
                            >
                                Kustom
                            </button>
                        </div>

                        {/* Date & Dropdown Inputs */}
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="flex items-center gap-1.5">
                                <input
                                    type="date"
                                    value={dateFrom}
                                    onChange={(e) => {
                                        setDateFrom(e.target.value);
                                        setSelectedPreset('custom');
                                    }}
                                    className="h-8 rounded-lg border border-slate-200 bg-slate-50 px-2 text-xs text-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none"
                                />
                                <span className="text-xs text-slate-400">s/d</span>
                                <input
                                    type="date"
                                    value={dateTo}
                                    onChange={(e) => {
                                        setDateTo(e.target.value);
                                        setSelectedPreset('custom');
                                    }}
                                    className="h-8 rounded-lg border border-slate-200 bg-slate-50 px-2 text-xs text-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none"
                                />
                            </div>

                            {/* Service Filter */}
                            <select
                                value={serviceId}
                                onChange={(e) => setServiceId(e.target.value)}
                                className="h-8 rounded-lg border border-slate-200 bg-slate-50 px-2 text-xs text-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none"
                            >
                                <option value="">Semua Layanan</option>
                                {filter_options.services.map((svc) => (
                                    <option key={svc.id} value={svc.id}>
                                        {svc.name}
                                    </option>
                                ))}
                            </select>

                            {/* Resource Filter */}
                            <select
                                value={resourceId}
                                onChange={(e) => setResourceId(e.target.value)}
                                className="h-8 rounded-lg border border-slate-200 bg-slate-50 px-2 text-xs text-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none"
                            >
                                <option value="">Semua Resource / Staf</option>
                                {filter_options.resources.map((res) => (
                                    <option key={res.id} value={res.id}>
                                        {res.name}
                                    </option>
                                ))}
                            </select>

                            {/* Actions */}
                            <Button
                                size="sm"
                                onClick={() => applyFilter()}
                                className="h-8 bg-blue-600 px-3 text-xs text-white hover:bg-blue-700"
                            >
                                Terapkan
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={handleReset}
                                className="h-8 border-slate-200 px-2.5 text-xs text-slate-600 hover:bg-slate-100"
                                title="Reset filter"
                            >
                                <RefreshCw className="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>
                </div>

                {/* 5 KPI Summary Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    {/* 1. Total Booking */}
                    <div className="rounded-xl border border-slate-200 bg-white p-4">
                        <div className="flex items-center justify-between text-slate-500">
                            <span className="text-xs font-medium">Total Booking</span>
                            <Calendar className="h-4 w-4 text-slate-400" />
                        </div>
                        <div className="mt-2 text-2xl font-bold tracking-tight text-slate-900">
                            {summary.total_bookings}
                        </div>
                        <div className="mt-2 flex flex-wrap gap-1 text-[11px] text-slate-500">
                            <span className="rounded bg-emerald-50 px-1.5 py-0.5 font-medium text-emerald-700">
                                {summary.completed_bookings} Selesai
                            </span>
                            <span className="rounded bg-sky-50 px-1.5 py-0.5 font-medium text-sky-700">
                                {summary.confirmed_bookings} Konfirmasi
                            </span>
                        </div>
                    </div>

                    {/* 2. Total Pendapatan */}
                    <div className="rounded-xl border border-slate-200 bg-white p-4">
                        <div className="flex items-center justify-between text-slate-500">
                            <span className="text-xs font-medium">Total Pendapatan</span>
                            <DollarSign className="h-4 w-4 text-slate-400" />
                        </div>
                        <div className="mt-2 text-2xl font-bold tracking-tight text-slate-900">
                            {formatIdr(summary.total_revenue)}
                        </div>
                        <div className="mt-2 text-[11px] text-slate-500">
                            Dari booking lunas & selesai
                        </div>
                    </div>

                    {/* 3. Pelanggan Baru vs Repeat */}
                    <div className="rounded-xl border border-slate-200 bg-white p-4">
                        <div className="flex items-center justify-between text-slate-500">
                            <span className="text-xs font-medium">Pelanggan Baru vs Repeat</span>
                            <UserCheck className="h-4 w-4 text-slate-400" />
                        </div>
                        <div className="mt-2 flex items-baseline gap-2">
                            <span className="text-2xl font-bold tracking-tight text-slate-900">
                                {summary.new_customers_count}
                            </span>
                            <span className="text-xs text-slate-500">
                                Baru / <strong className="text-slate-800">{summary.repeat_customers_count}</strong> Repeat
                            </span>
                        </div>
                        <div className="mt-2.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 flex">
                            {summary.new_customers_count + summary.repeat_customers_count > 0 ? (
                                <>
                                    <div
                                        className="h-full bg-blue-500"
                                        style={{
                                            width: `${
                                                (summary.new_customers_count /
                                                    (summary.new_customers_count + summary.repeat_customers_count)) *
                                                100
                                            }%`,
                                        }}
                                        title={`Baru: ${summary.new_customers_count}`}
                                    />
                                    <div
                                        className="h-full bg-emerald-500"
                                        style={{
                                            width: `${
                                                (summary.repeat_customers_count /
                                                    (summary.new_customers_count + summary.repeat_customers_count)) *
                                                100
                                            }%`,
                                        }}
                                        title={`Repeat: ${summary.repeat_customers_count}`}
                                    />
                                </>
                            ) : (
                                <div className="h-full w-full bg-slate-200" />
                            )}
                        </div>
                    </div>

                    {/* 4. Rata-rata Utilisasi Resource */}
                    <div className="rounded-xl border border-slate-200 bg-white p-4">
                        <div className="flex items-center justify-between text-slate-500">
                            <span className="text-xs font-medium">Rata-rata Utilisasi</span>
                            <Activity className="h-4 w-4 text-slate-400" />
                        </div>
                        <div className="mt-2 text-2xl font-bold tracking-tight text-slate-900">
                            {summary.average_utilization}%
                        </div>
                        <div className="mt-2.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                            <div
                                className="h-full bg-blue-600 transition-all duration-300"
                                style={{ width: `${Math.min(100, summary.average_utilization)}%` }}
                            />
                        </div>
                    </div>

                    {/* 5. Pembatalan & No-Show */}
                    <div className="rounded-xl border border-slate-200 bg-white p-4">
                        <div className="flex items-center justify-between text-slate-500">
                            <span className="text-xs font-medium">Batal & No-Show</span>
                            <XCircle className="h-4 w-4 text-slate-400" />
                        </div>
                        <div className="mt-2 flex items-baseline gap-2">
                            <span className="text-2xl font-bold tracking-tight text-red-600">
                                {summary.cancelled_bookings + summary.no_show_bookings}
                            </span>
                            <span className="text-xs text-slate-500">
                                ({summary.cancelled_bookings} batal, {summary.no_show_bookings} no-show)
                            </span>
                        </div>
                        <div className="mt-2 text-[11px] text-slate-500">
                            Rasio batal:{' '}
                            <strong className="text-slate-700">
                                {summary.total_bookings > 0
                                    ? Math.round((summary.cancelled_bookings / summary.total_bookings) * 100)
                                    : 0}
                                %
                            </strong>
                        </div>
                    </div>
                </div>

                {/* Main Content Tabs */}
                <div className="rounded-xl border border-slate-200 bg-white">
                    {/* Tab Navigation */}
                    <div className="flex border-b border-slate-200 px-4">
                        <button
                            type="button"
                            onClick={() => setActiveTab('resources')}
                            className={`flex items-center gap-2 border-b-2 py-3.5 text-xs font-semibold transition-colors ${
                                activeTab === 'resources'
                                    ? 'border-blue-600 text-blue-600'
                                    : 'border-transparent text-slate-500 hover:text-slate-800'
                            }`}
                        >
                            <Users className="h-4 w-4" />
                            <span>Utilisasi Resource & Staf (PRD 162)</span>
                            <span className="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] text-slate-600">
                                {resources.length}
                            </span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('services')}
                            className={`ml-6 flex items-center gap-2 border-b-2 py-3.5 text-xs font-semibold transition-colors ${
                                activeTab === 'services'
                                    ? 'border-blue-600 text-blue-600'
                                    : 'border-transparent text-slate-500 hover:text-slate-800'
                            }`}
                        >
                            <Layers className="h-4 w-4" />
                            <span>Performa Layanan (PRD 163)</span>
                            <span className="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] text-slate-600">
                                {services.length}
                            </span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('trends')}
                            className={`ml-6 flex items-center gap-2 border-b-2 py-3.5 text-xs font-semibold transition-colors ${
                                activeTab === 'trends'
                                    ? 'border-blue-600 text-blue-600'
                                    : 'border-transparent text-slate-500 hover:text-slate-800'
                            }`}
                        >
                            <TrendingUp className="h-4 w-4" />
                            <span>Tren Harian</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('breakdown')}
                            className={`ml-6 flex items-center gap-2 border-b-2 py-3.5 text-xs font-semibold transition-colors ${
                                activeTab === 'breakdown'
                                    ? 'border-blue-600 text-blue-600'
                                    : 'border-transparent text-slate-500 hover:text-slate-800'
                            }`}
                        >
                            <PieChart className="h-4 w-4" />
                            <span>Status Pemesanan</span>
                        </button>
                    </div>

                    {/* Tab 1: Resource Utilization */}
                    {activeTab === 'resources' && (
                        <div className="p-4">
                            <div className="mb-4 flex items-center justify-between">
                                <p className="text-xs text-slate-500">
                                    Rumus PRD 162: <code className="rounded bg-slate-100 px-1 py-0.5 font-mono text-[11px] text-slate-700">(Waktu Terpakai / Waktu Tersedia) * 100%</code>. Waktu tersedia dihitung berdasarkan jadwal kerja individu atau jam operasional bisnis, dikurangi istirahat dan cuti/blok waktu.
                                </p>
                            </div>

                            {resources.length === 0 ? (
                                <div className="py-12 text-center">
                                    <Users className="mx-auto h-8 w-8 text-slate-300" />
                                    <p className="mt-2 text-sm text-slate-500">Belum ada resource aktif pada tenant ini.</p>
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs">
                                        <thead>
                                            <tr className="border-b border-slate-200 bg-slate-50 text-slate-500 font-medium">
                                                <th className="py-2.5 px-3">Nama Resource</th>
                                                <th className="py-2.5 px-3">Peran / Tipe</th>
                                                <th className="py-2.5 px-3">Waktu Terpakai</th>
                                                <th className="py-2.5 px-3">Waktu Tersedia</th>
                                                <th className="py-2.5 px-3">Tingkat Utilisasi</th>
                                                <th className="py-2.5 px-3 w-48">Indikator</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {resources.map((res) => (
                                                <tr key={res.id} className="hover:bg-slate-50">
                                                    <td className="py-3 px-3 font-medium text-slate-900">
                                                        {res.name}
                                                        {res.code && (
                                                            <span className="ml-1.5 font-mono text-[10px] text-slate-400">
                                                                #{res.code}
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="py-3 px-3 text-slate-600">{res.role}</td>
                                                    <td className="py-3 px-3 text-slate-800">
                                                        <span className="font-semibold">{res.booked_hours} jam</span>{' '}
                                                        <span className="text-slate-400">({res.booked_minutes} mnt)</span>
                                                    </td>
                                                    <td className="py-3 px-3 text-slate-600">
                                                        <span>{res.available_hours} jam</span>{' '}
                                                        <span className="text-slate-400">({res.available_minutes} mnt)</span>
                                                    </td>
                                                    <td className="py-3 px-3">
                                                        <span
                                                            className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${
                                                                res.utilization_rate >= 75
                                                                    ? 'bg-emerald-50 text-emerald-700'
                                                                    : res.utilization_rate >= 40
                                                                    ? 'bg-blue-50 text-blue-700'
                                                                    : res.utilization_rate > 0
                                                                    ? 'bg-amber-50 text-amber-700'
                                                                    : 'bg-slate-100 text-slate-500'
                                                            }`}
                                                        >
                                                            {res.utilization_rate}%
                                                        </span>
                                                    </td>
                                                    <td className="py-3 px-3">
                                                        <div className="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                                            <div
                                                                className={`h-full transition-all duration-300 ${
                                                                    res.utilization_rate >= 75
                                                                        ? 'bg-emerald-500'
                                                                        : res.utilization_rate >= 40
                                                                        ? 'bg-blue-600'
                                                                        : 'bg-amber-500'
                                                                }`}
                                                                style={{ width: `${Math.min(100, res.utilization_rate)}%` }}
                                                            />
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Tab 2: Service Metrics */}
                    {activeTab === 'services' && (
                        <div className="p-4">
                            <div className="mb-4">
                                <p className="text-xs text-slate-500">
                                    Metrik performa per layanan (PRD 163): Total pemesanan, tingkat pembatalan, durasi operasional, dan kontribusi pendapatan.
                                </p>
                            </div>

                            {services.length === 0 ? (
                                <div className="py-12 text-center">
                                    <Layers className="mx-auto h-8 w-8 text-slate-300" />
                                    <p className="mt-2 text-sm text-slate-500">Belum ada data layanan untuk ditampilkan.</p>
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs">
                                        <thead>
                                            <tr className="border-b border-slate-200 bg-slate-50 text-slate-500 font-medium">
                                                <th className="py-2.5 px-3">Nama Layanan</th>
                                                <th className="py-2.5 px-3">Kategori</th>
                                                <th className="py-2.5 px-3 text-center">Total Booking</th>
                                                <th className="py-2.5 px-3 text-center">Selesai</th>
                                                <th className="py-2.5 px-3 text-center">Batal</th>
                                                <th className="py-2.5 px-3 text-center">Rasio Batal</th>
                                                <th className="py-2.5 px-3">Durasi Total</th>
                                                <th className="py-2.5 px-3 text-right">Total Pendapatan</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {services.map((svc) => (
                                                <tr key={svc.id} className="hover:bg-slate-50">
                                                    <td className="py-3 px-3 font-medium text-slate-900">{svc.name}</td>
                                                    <td className="py-3 px-3 text-slate-500">{svc.category_name}</td>
                                                    <td className="py-3 px-3 text-center font-semibold text-slate-800">
                                                        {svc.total_bookings}
                                                    </td>
                                                    <td className="py-3 px-3 text-center text-emerald-600 font-medium">
                                                        {svc.completed_bookings}
                                                    </td>
                                                    <td className="py-3 px-3 text-center text-red-600 font-medium">
                                                        {svc.cancelled_bookings}
                                                    </td>
                                                    <td className="py-3 px-3 text-center">
                                                        <span
                                                            className={`inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium ${
                                                                svc.cancellation_rate > 20
                                                                    ? 'bg-red-50 text-red-700'
                                                                    : 'bg-slate-100 text-slate-600'
                                                            }`}
                                                        >
                                                            {svc.cancellation_rate}%
                                                        </span>
                                                    </td>
                                                    <td className="py-3 px-3 text-slate-600">
                                                        {svc.total_duration_hours} jam
                                                    </td>
                                                    <td className="py-3 px-3 text-right font-semibold text-slate-900">
                                                        {formatIdr(svc.total_revenue)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Tab 3: Daily Trends */}
                    {activeTab === 'trends' && (
                        <div className="p-4">
                            <div className="mb-4">
                                <p className="text-xs text-slate-500">
                                    Aktivitas harian pemesanan dan tren pendapatan pada rentang tanggal terpilih.
                                </p>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead>
                                        <tr className="border-b border-slate-200 bg-slate-50 text-slate-500 font-medium">
                                            <th className="py-2.5 px-3">Tanggal</th>
                                            <th className="py-2.5 px-3">Hari</th>
                                            <th className="py-2.5 px-3 text-center">Total Booking</th>
                                            <th className="py-2.5 px-3 text-center">Selesai</th>
                                            <th className="py-2.5 px-3 text-center">Batal</th>
                                            <th className="py-2.5 px-3 w-48">Distribusi Visual</th>
                                            <th className="py-2.5 px-3 text-right">Pendapatan</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {daily_trends.map((item) => (
                                            <tr key={item.date} className="hover:bg-slate-50">
                                                <td className="py-2.5 px-3 font-mono text-slate-800">{item.date}</td>
                                                <td className="py-2.5 px-3 text-slate-600 font-medium">{item.day_name}</td>
                                                <td className="py-2.5 px-3 text-center font-semibold text-slate-900">
                                                    {item.total_bookings}
                                                </td>
                                                <td className="py-2.5 px-3 text-center text-emerald-600">
                                                    {item.completed_bookings}
                                                </td>
                                                <td className="py-2.5 px-3 text-center text-red-600">
                                                    {item.cancelled_bookings}
                                                </td>
                                                <td className="py-2.5 px-3">
                                                    <div className="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                                        <div
                                                            className="h-full bg-blue-600"
                                                            style={{
                                                                width: `${
                                                                    item.total_bookings > 0
                                                                        ? (item.total_bookings / maxDailyBookings) * 100
                                                                        : 0
                                                                }%`,
                                                            }}
                                                        />
                                                    </div>
                                                </td>
                                                <td className="py-2.5 px-3 text-right font-medium text-slate-900">
                                                    {formatIdr(item.revenue)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {/* Tab 4: Status Breakdown */}
                    {activeTab === 'breakdown' && (
                        <div className="p-6">
                            <h3 className="text-sm font-semibold text-slate-900">
                                Rincian Pemesanan Berdasarkan Kategori Status
                            </h3>
                            <p className="mt-1 text-xs text-slate-500">
                                Distribusi seluruh status pemesanan dalam periode yang dipilih.
                            </p>

                            <div className="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <div className="rounded-xl border border-slate-100 bg-slate-50/50 p-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-medium text-slate-600">COMPLETED</span>
                                        <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                                    </div>
                                    <div className="mt-2 text-2xl font-bold text-slate-900">
                                        {summary.completed_bookings}
                                    </div>
                                    <div className="mt-1 text-[11px] text-slate-500">Pemesanan telah selesai</div>
                                </div>

                                <div className="rounded-xl border border-slate-100 bg-slate-50/50 p-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-medium text-slate-600">CONFIRMED</span>
                                        <Clock className="h-4 w-4 text-sky-600" />
                                    </div>
                                    <div className="mt-2 text-2xl font-bold text-slate-900">
                                        {summary.confirmed_bookings}
                                    </div>
                                    <div className="mt-1 text-[11px] text-slate-500">Terkonfirmasi & slot terjaga</div>
                                </div>

                                <div className="rounded-xl border border-slate-100 bg-slate-50/50 p-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-medium text-slate-600">PENDING</span>
                                        <Clock className="h-4 w-4 text-amber-600" />
                                    </div>
                                    <div className="mt-2 text-2xl font-bold text-slate-900">
                                        {summary.pending_bookings}
                                    </div>
                                    <div className="mt-1 text-[11px] text-slate-500">Menunggu verifikasi / bayar</div>
                                </div>

                                <div className="rounded-xl border border-slate-100 bg-slate-50/50 p-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-medium text-slate-600">CHECKED_IN</span>
                                        <UserCheck className="h-4 w-4 text-blue-600" />
                                    </div>
                                    <div className="mt-2 text-2xl font-bold text-slate-900">
                                        {summary.checked_in_bookings}
                                    </div>
                                    <div className="mt-1 text-[11px] text-slate-500">Pelanggan telah hadir</div>
                                </div>

                                <div className="rounded-xl border border-slate-100 bg-slate-50/50 p-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-medium text-slate-600">IN_PROGRESS</span>
                                        <Activity className="h-4 w-4 text-indigo-600" />
                                    </div>
                                    <div className="mt-2 text-2xl font-bold text-slate-900">
                                        {summary.in_progress_bookings}
                                    </div>
                                    <div className="mt-1 text-[11px] text-slate-500">Layanan sedang berlangsung</div>
                                </div>

                                <div className="rounded-xl border border-slate-100 bg-slate-50/50 p-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-medium text-slate-600">CANCELLED</span>
                                        <XCircle className="h-4 w-4 text-red-600" />
                                    </div>
                                    <div className="mt-2 text-2xl font-bold text-red-600">
                                        {summary.cancelled_bookings}
                                    </div>
                                    <div className="mt-1 text-[11px] text-slate-500">Dibatalkan sebelum jadwal</div>
                                </div>

                                <div className="rounded-xl border border-slate-100 bg-slate-50/50 p-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-medium text-slate-600">NO_SHOW</span>
                                        <AlertCircle className="h-4 w-4 text-orange-600" />
                                    </div>
                                    <div className="mt-2 text-2xl font-bold text-orange-600">
                                        {summary.no_show_bookings}
                                    </div>
                                    <div className="mt-1 text-[11px] text-slate-500">Tidak hadir tanpa kabar</div>
                                </div>

                                <div className="rounded-xl border border-slate-100 bg-slate-50/50 p-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-medium text-slate-600">EXPIRED</span>
                                        <Clock className="h-4 w-4 text-slate-400" />
                                    </div>
                                    <div className="mt-2 text-2xl font-bold text-slate-500">
                                        {summary.expired_bookings}
                                    </div>
                                    <div className="mt-1 text-[11px] text-slate-500">Batas waktu hold habis</div>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </OwnerLayout>
    );
}
