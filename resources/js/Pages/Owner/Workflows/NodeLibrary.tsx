import {
    AlertCircle,
    Bell,
    CheckCircle2,
    Clock,
    DollarSign,
    FileText,
    GitBranch,
    HelpCircle,
    Plus,
    RefreshCw,
    UserCheck,
    Zap,
} from 'lucide-react';
import React from 'react';
import { NodeCategory, WorkflowNodeConfig } from './types';

export interface NodeTemplateItem {
    id: string;
    category: NodeCategory;
    type: string;
    label: string;
    description: string;
    icon: React.ReactNode;
    defaultConfig: WorkflowNodeConfig;
}

interface NodeLibraryProps {
    onAddNode: (template: NodeTemplateItem) => void;
}

export const NODE_TEMPLATES: Record<NodeCategory, NodeTemplateItem[]> = {
    trigger: [
        {
            id: 'trigger_booking_created',
            category: 'trigger',
            type: 'booking_created',
            label: 'Booking Dibuat',
            description: 'Aktif saat customer atau owner membuat reservasi baru.',
            icon: <Zap className="h-4 w-4 text-blue-600" />,
            defaultConfig: {},
        },
        {
            id: 'trigger_status_changed',
            category: 'trigger',
            type: 'status_changed',
            label: 'Status Berubah',
            description: 'Aktif saat status booking berpindah.',
            icon: <RefreshCw className="h-4 w-4 text-blue-600" />,
            defaultConfig: { target_status: 'CONFIRMED' },
        },
        {
            id: 'trigger_payment_received',
            category: 'trigger',
            type: 'payment_received',
            label: 'Pembayaran Diterima',
            description: 'Aktif saat invoice booking berhasil dibayar.',
            icon: <CheckCircle2 className="h-4 w-4 text-blue-600" />,
            defaultConfig: {},
        },
        {
            id: 'trigger_customer_checked_in',
            category: 'trigger',
            type: 'customer_checked_in',
            label: 'Customer Check-in',
            description: 'Aktif saat customer tiba di lokasi.',
            icon: <UserCheck className="h-4 w-4 text-blue-600" />,
            defaultConfig: {},
        },
        {
            id: 'trigger_booking_cancelled',
            category: 'trigger',
            type: 'booking_cancelled',
            label: 'Booking Dibatalkan',
            description: 'Aktif saat reservasi dibatalkan.',
            icon: <AlertCircle className="h-4 w-4 text-blue-600" />,
            defaultConfig: {},
        },
    ],
    condition: [
        {
            id: 'cond_payment_status',
            category: 'condition',
            type: 'payment_status',
            label: 'Cek Status Pembayaran',
            description: 'Bercabang jika status bayar Lunas (PAID) atau Belum (UNPAID).',
            icon: <HelpCircle className="h-4 w-4 text-amber-600" />,
            defaultConfig: { payment_status: 'PAID' },
        },
        {
            id: 'cond_booking_status',
            category: 'condition',
            type: 'booking_status',
            label: 'Cek Status Booking',
            description: 'Periksa status reservasi saat ini.',
            icon: <GitBranch className="h-4 w-4 text-amber-600" />,
            defaultConfig: { status: 'CONFIRMED' },
        },
        {
            id: 'cond_total_amount',
            category: 'condition',
            type: 'total_amount',
            label: 'Cek Total Nominal',
            description: 'Bercabang berdasarkan batas minimum nilai transaksi.',
            icon: <DollarSign className="h-4 w-4 text-amber-600" />,
            defaultConfig: { amount: 100000 },
        },
    ],
    action: [
        {
            id: 'act_change_status',
            category: 'action',
            type: 'change_status',
            label: 'Ubah Status Booking',
            description: 'Pindahkan status reservasi ke status berikutnya.',
            icon: <RefreshCw className="h-4 w-4 text-emerald-600" />,
            defaultConfig: { target_status: 'CONFIRMED' },
        },
        {
            id: 'act_send_notification',
            category: 'action',
            type: 'send_notification',
            label: 'Kirim WhatsApp / Notifikasi',
            description: 'Kirim pesan otomatis konfirmasi, pengingat, atau instruksi.',
            icon: <Bell className="h-4 w-4 text-emerald-600" />,
            defaultConfig: { channel: 'whatsapp', template: 'booking_confirmed' },
        },
        {
            id: 'act_assign_resource',
            category: 'action',
            type: 'assign_resource',
            label: 'Tetapkan Staf / Ruangan',
            description: 'Auto-assign staf atau ruangan yang tersedia.',
            icon: <UserCheck className="h-4 w-4 text-emerald-600" />,
            defaultConfig: {},
        },
        {
            id: 'act_add_note',
            category: 'action',
            type: 'add_note',
            label: 'Tambah Catatan Internal',
            description: 'Beri catatan audit pada reservasi.',
            icon: <FileText className="h-4 w-4 text-emerald-600" />,
            defaultConfig: { note: 'Diproses otomatis oleh sistem workflow.' },
        },
    ],
    delay: [
        {
            id: 'delay_timer',
            category: 'delay',
            type: 'delay',
            label: 'Jeda Waktu (Delay)',
            description: 'Tunda eksekusi langkah berikutnya dalam menit/jam/hari.',
            icon: <Clock className="h-4 w-4 text-purple-600" />,
            defaultConfig: { duration: 15, unit: 'minutes' },
        },
    ],
};

export const NodeLibrary: React.FC<NodeLibraryProps> = ({ onAddNode }) => {
    const handleDragStart = (e: React.DragEvent, template: NodeTemplateItem) => {
        e.dataTransfer.setData('application/reactflow-node', JSON.stringify(template));
        e.dataTransfer.effectAllowed = 'move';
    };

    return (
        <div className="flex h-full flex-col border-r border-slate-200 bg-white">
            <div className="border-b border-slate-200 px-4 py-3">
                <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider">
                    Library Node
                </h3>
                <p className="mt-0.5 text-[11px] text-slate-500">
                    Tarik ke kanvas atau klik tombol + untuk menambahkan node.
                </p>
            </div>

            <div className="flex-1 overflow-y-auto p-3 space-y-4 text-xs">
                {/* 1. Triggers */}
                <div className="space-y-2">
                    <span className="flex items-center gap-1.5 text-[11px] font-bold text-blue-700 uppercase tracking-wider">
                        <Zap className="h-3.5 w-3.5" /> Pemicu (Trigger)
                    </span>
                    <div className="space-y-1.5">
                        {NODE_TEMPLATES.trigger.map((tmpl) => (
                            <div
                                key={tmpl.id}
                                draggable
                                onDragStart={(e) => handleDragStart(e, tmpl)}
                                className="group flex cursor-grab items-center justify-between rounded-[8px] border border-slate-200 bg-slate-50/50 p-2 transition-all hover:border-blue-400 hover:bg-blue-50/30 active:cursor-grabbing"
                            >
                                <div className="flex items-center gap-2 overflow-hidden pr-1">
                                    <div className="flex h-6 w-6 shrink-0 items-center justify-center rounded-[6px] bg-white border border-slate-200 shadow-2xs">
                                        {tmpl.icon}
                                    </div>
                                    <div className="truncate">
                                        <div className="truncate text-[11px] font-semibold text-slate-900">
                                            {tmpl.label}
                                        </div>
                                        <div className="truncate text-[10px] text-slate-500">
                                            {tmpl.description}
                                        </div>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => onAddNode(tmpl)}
                                    title="Tambah ke kanvas (Alternatif Klik)"
                                    className="shrink-0 rounded-[6px] border border-slate-200 bg-white p-1 text-slate-600 transition-colors hover:border-blue-600 hover:bg-blue-600 hover:text-white"
                                >
                                    <Plus className="h-3 w-3" />
                                </button>
                            </div>
                        ))}
                    </div>
                </div>

                {/* 2. Conditions */}
                <div className="space-y-2">
                    <span className="flex items-center gap-1.5 text-[11px] font-bold text-amber-700 uppercase tracking-wider">
                        <GitBranch className="h-3.5 w-3.5" /> Kondisi (Condition)
                    </span>
                    <div className="space-y-1.5">
                        {NODE_TEMPLATES.condition.map((tmpl) => (
                            <div
                                key={tmpl.id}
                                draggable
                                onDragStart={(e) => handleDragStart(e, tmpl)}
                                className="group flex cursor-grab items-center justify-between rounded-[8px] border border-slate-200 bg-slate-50/50 p-2 transition-all hover:border-amber-400 hover:bg-amber-50/30 active:cursor-grabbing"
                            >
                                <div className="flex items-center gap-2 overflow-hidden pr-1">
                                    <div className="flex h-6 w-6 shrink-0 items-center justify-center rounded-[6px] bg-white border border-slate-200 shadow-2xs">
                                        {tmpl.icon}
                                    </div>
                                    <div className="truncate">
                                        <div className="truncate text-[11px] font-semibold text-slate-900">
                                            {tmpl.label}
                                        </div>
                                        <div className="truncate text-[10px] text-slate-500">
                                            {tmpl.description}
                                        </div>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => onAddNode(tmpl)}
                                    title="Tambah ke kanvas"
                                    className="shrink-0 rounded-[6px] border border-slate-200 bg-white p-1 text-slate-600 transition-colors hover:border-amber-600 hover:bg-amber-600 hover:text-white"
                                >
                                    <Plus className="h-3 w-3" />
                                </button>
                            </div>
                        ))}
                    </div>
                </div>

                {/* 3. Actions */}
                <div className="space-y-2">
                    <span className="flex items-center gap-1.5 text-[11px] font-bold text-emerald-700 uppercase tracking-wider">
                        <CheckCircle2 className="h-3.5 w-3.5" /> Aksi (Action)
                    </span>
                    <div className="space-y-1.5">
                        {NODE_TEMPLATES.action.map((tmpl) => (
                            <div
                                key={tmpl.id}
                                draggable
                                onDragStart={(e) => handleDragStart(e, tmpl)}
                                className="group flex cursor-grab items-center justify-between rounded-[8px] border border-slate-200 bg-slate-50/50 p-2 transition-all hover:border-emerald-400 hover:bg-emerald-50/30 active:cursor-grabbing"
                            >
                                <div className="flex items-center gap-2 overflow-hidden pr-1">
                                    <div className="flex h-6 w-6 shrink-0 items-center justify-center rounded-[6px] bg-white border border-slate-200 shadow-2xs">
                                        {tmpl.icon}
                                    </div>
                                    <div className="truncate">
                                        <div className="truncate text-[11px] font-semibold text-slate-900">
                                            {tmpl.label}
                                        </div>
                                        <div className="truncate text-[10px] text-slate-500">
                                            {tmpl.description}
                                        </div>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => onAddNode(tmpl)}
                                    title="Tambah ke kanvas"
                                    className="shrink-0 rounded-[6px] border border-slate-200 bg-white p-1 text-slate-600 transition-colors hover:border-emerald-600 hover:bg-emerald-600 hover:text-white"
                                >
                                    <Plus className="h-3 w-3" />
                                </button>
                            </div>
                        ))}
                    </div>
                </div>

                {/* 4. Delays */}
                <div className="space-y-2">
                    <span className="flex items-center gap-1.5 text-[11px] font-bold text-purple-700 uppercase tracking-wider">
                        <Clock className="h-3.5 w-3.5" /> Jeda (Delay)
                    </span>
                    <div className="space-y-1.5">
                        {NODE_TEMPLATES.delay.map((tmpl) => (
                            <div
                                key={tmpl.id}
                                draggable
                                onDragStart={(e) => handleDragStart(e, tmpl)}
                                className="group flex cursor-grab items-center justify-between rounded-[8px] border border-slate-200 bg-slate-50/50 p-2 transition-all hover:border-purple-400 hover:bg-purple-50/30 active:cursor-grabbing"
                            >
                                <div className="flex items-center gap-2 overflow-hidden pr-1">
                                    <div className="flex h-6 w-6 shrink-0 items-center justify-center rounded-[6px] bg-white border border-slate-200 shadow-2xs">
                                        {tmpl.icon}
                                    </div>
                                    <div className="truncate">
                                        <div className="truncate text-[11px] font-semibold text-slate-900">
                                            {tmpl.label}
                                        </div>
                                        <div className="truncate text-[10px] text-slate-500">
                                            {tmpl.description}
                                        </div>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => onAddNode(tmpl)}
                                    title="Tambah ke kanvas"
                                    className="shrink-0 rounded-[6px] border border-slate-200 bg-white p-1 text-slate-600 transition-colors hover:border-purple-600 hover:bg-purple-600 hover:text-white"
                                >
                                    <Plus className="h-3 w-3" />
                                </button>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
};
