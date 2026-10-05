import {
    Check,
    Copy,
    Trash2,
    X,
} from 'lucide-react';
import React from 'react';
import { Button } from '../../../Components/ui/Button';
import { Input } from '../../../Components/ui/Input';
import { Textarea } from '../../../Components/ui/Textarea';
import { CustomWorkflowNode } from './types';

interface StatusOption {
    id: number | string;
    code: string;
    name: string;
}

interface NodePropertyEditorProps {
    node: CustomWorkflowNode | null;
    statuses: StatusOption[];
    onUpdateNodeData: (nodeId: string, updatedData: Partial<CustomWorkflowNode['data']>) => void;
    onDuplicateNode: (nodeId: string) => void;
    onDeleteNode: (nodeId: string) => void;
    onClose: () => void;
}

export const NodePropertyEditor: React.FC<NodePropertyEditorProps> = ({
    node,
    statuses,
    onUpdateNodeData,
    onDuplicateNode,
    onDeleteNode,
    onClose,
}) => {
    if (!node) {
        return (
            <div className="flex h-full flex-col items-center justify-center border-l border-slate-200 bg-white p-6 text-center">
                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 mb-3">
                    <Check className="h-6 w-6" />
                </div>
                <h4 className="text-xs font-semibold text-slate-800">Tidak Ada Node Terpilih</h4>
                <p className="mt-1 text-[11px] text-slate-500 max-w-[200px]">
                    Klik salah satu node pada kanvas untuk mengonfigurasi properti dan aksinya.
                </p>
            </div>
        );
    }

    const { label, category, type, config = {}, disabled } = node.data;

    const handleConfigChange = (key: string, value: unknown) => {
        onUpdateNodeData(node.id, {
            config: {
                ...config,
                [key]: value,
            },
        });
    };

    return (
        <div className="flex h-full flex-col border-l border-slate-200 bg-white">
            {/* Header */}
            <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <div>
                    <span className="text-[10px] font-bold uppercase tracking-wider text-blue-600">
                        Properti Node ({category})
                    </span>
                    <h3 className="text-xs font-bold text-slate-900 truncate max-w-[180px]">
                        {label}
                    </h3>
                </div>
                <button
                    type="button"
                    onClick={onClose}
                    className="rounded-[6px] p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                >
                    <X className="h-4 w-4" />
                </button>
            </div>

            {/* Form Fields */}
            <div className="flex-1 overflow-y-auto p-4 space-y-4 text-xs">
                {/* 1. Label */}
                <div>
                    <label className="block text-xs font-medium text-slate-700 mb-1">
                        Nama / Judul Node <span className="text-rose-500">*</span>
                    </label>
                    <Input
                        value={label}
                        onChange={(e) => onUpdateNodeData(node.id, { label: e.target.value })}
                        placeholder="Nama langkah alur..."
                        className="text-xs"
                    />
                </div>

                {/* 2. Specific Config based on Subtype */}
                {type === 'change_status' && (
                    <div className="space-y-1">
                        <label className="block text-xs font-medium text-slate-700">
                            Target Status Booking <span className="text-rose-500">*</span>
                        </label>
                        <select
                            value={(config.target_status as string) || 'CONFIRMED'}
                            onChange={(e) => handleConfigChange('target_status', e.target.value)}
                            className="w-full rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:outline-none"
                        >
                            {statuses.length > 0 ? (
                                statuses.map((s) => (
                                    <option key={s.id} value={s.code}>
                                        {s.name} ({s.code})
                                    </option>
                                ))
                            ) : (
                                <>
                                    <option value="PENDING">Menunggu Pembayaran (PENDING)</option>
                                    <option value="CONFIRMED">Terkonfirmasi (CONFIRMED)</option>
                                    <option value="CHECKED_IN">Check-in (CHECKED_IN)</option>
                                    <option value="IN_PROGRESS">Sedang Berjalan (IN_PROGRESS)</option>
                                    <option value="COMPLETED">Selesai (COMPLETED)</option>
                                    <option value="CANCELLED">Dibatalkan (CANCELLED)</option>
                                </>
                            )}
                        </select>
                        <p className="text-[10px] text-slate-400">
                            Status yang akan diterapkan saat alur mencapai node ini.
                        </p>
                    </div>
                )}

                {type === 'payment_status' && (
                    <div className="space-y-1">
                        <label className="block text-xs font-medium text-slate-700">
                            Kriteria Status Pembayaran
                        </label>
                        <select
                            value={(config.payment_status as string) || 'PAID'}
                            onChange={(e) => handleConfigChange('payment_status', e.target.value)}
                            className="w-full rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:outline-none"
                        >
                            <option value="PAID">Lunas (PAID)</option>
                            <option value="PARTIAL">Uang Muka / DP (PARTIAL)</option>
                            <option value="UNPAID">Belum Bayar (UNPAID)</option>
                        </select>
                    </div>
                )}

                {type === 'booking_status' && (
                    <div className="space-y-1">
                        <label className="block text-xs font-medium text-slate-700">
                            Periksa Status Booking
                        </label>
                        <select
                            value={(config.status as string) || 'CONFIRMED'}
                            onChange={(e) => handleConfigChange('status', e.target.value)}
                            className="w-full rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:outline-none"
                        >
                            <option value="PENDING">PENDING</option>
                            <option value="CONFIRMED">CONFIRMED</option>
                            <option value="CHECKED_IN">CHECKED_IN</option>
                            <option value="IN_PROGRESS">IN_PROGRESS</option>
                            <option value="COMPLETED">COMPLETED</option>
                            <option value="CANCELLED">CANCELLED</option>
                        </select>
                    </div>
                )}

                {type === 'total_amount' && (
                    <div className="space-y-1">
                        <label className="block text-xs font-medium text-slate-700">
                            Nilai Minimum Transaksi (Rp)
                        </label>
                        <Input
                            type="number"
                            value={String(config.amount ?? 100000)}
                            onChange={(e) => handleConfigChange('amount', Number(e.target.value))}
                            className="text-xs"
                        />
                    </div>
                )}

                {type === 'send_notification' && (
                    <div className="space-y-3">
                        <div>
                            <label className="block text-xs font-medium text-slate-700 mb-1">
                                Kanal Notifikasi
                            </label>
                            <select
                                value={(config.channel as string) || 'whatsapp'}
                                onChange={(e) => handleConfigChange('channel', e.target.value)}
                                className="w-full rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:outline-none"
                            >
                                <option value="whatsapp">WhatsApp (Disarankan)</option>
                                <option value="email">Email</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 mb-1">
                                Template / Pesan
                            </label>
                            <select
                                value={(config.template as string) || 'booking_confirmed'}
                                onChange={(e) => handleConfigChange('template', e.target.value)}
                                className="w-full rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:outline-none"
                            >
                                <option value="booking_confirmed">Konfirmasi Reservasi Baru</option>
                                <option value="reminder_h1">Pengingat Jadwal (H-1 Jam)</option>
                                <option value="payment_instructions">Instruksi Pembayaran & DP</option>
                                <option value="service_completed">Terima Kasih & Ulasan Layanan</option>
                            </select>
                        </div>
                    </div>
                )}

                {type === 'delay' && (
                    <div className="space-y-3">
                        <div>
                            <label className="block text-xs font-medium text-slate-700 mb-1">
                                Durasi Jeda
                            </label>
                            <Input
                                type="number"
                                min={1}
                                value={String(config.duration ?? 15)}
                                onChange={(e) => handleConfigChange('duration', Math.max(1, Number(e.target.value)))}
                                className="text-xs"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 mb-1">
                                Satuan Waktu
                            </label>
                            <select
                                value={(config.unit as string) || 'minutes'}
                                onChange={(e) => handleConfigChange('unit', e.target.value)}
                                className="w-full rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-blue-600 focus:outline-none"
                            >
                                <option value="minutes">Menit</option>
                                <option value="hours">Jam</option>
                                <option value="days">Hari</option>
                            </select>
                        </div>
                    </div>
                )}

                {type === 'add_note' && (
                    <div>
                        <label className="block text-xs font-medium text-slate-700 mb-1">
                            Isi Catatan Internal
                        </label>
                        <Textarea
                            rows={3}
                            value={(config.note as string) || ''}
                            onChange={(e) => handleConfigChange('note', e.target.value)}
                            placeholder="Catatan yang akan ditambahkan ke riwayat booking..."
                            className="text-xs"
                        />
                    </div>
                )}

                {/* 3. Disable Node toggle */}
                <div className="flex items-center justify-between rounded-[8px] border border-slate-200 bg-slate-50 p-2.5">
                    <span className="text-xs font-medium text-slate-700">Nonaktifkan Node Ini</span>
                    <input
                        type="checkbox"
                        checked={Boolean(disabled)}
                        onChange={(e) => onUpdateNodeData(node.id, { disabled: e.target.checked })}
                        className="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                    />
                </div>
            </div>

            {/* Footer Action Buttons */}
            <div className="border-t border-slate-200 p-3 space-y-2">
                <Button
                    type="button"
                    variant="outline"
                    className="w-full justify-center gap-1.5 text-xs text-slate-700"
                    onClick={() => onDuplicateNode(node.id)}
                >
                    <Copy className="h-3.5 w-3.5" /> Duplikasikan Node
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    className="w-full justify-center gap-1.5 text-xs text-rose-600 hover:bg-rose-50 hover:border-rose-300"
                    onClick={() => onDeleteNode(node.id)}
                >
                    <Trash2 className="h-3.5 w-3.5" /> Hapus Node
                </Button>
            </div>
        </div>
    );
};
