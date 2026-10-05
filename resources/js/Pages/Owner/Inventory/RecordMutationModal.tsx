import { router } from '@inertiajs/react';
import { AlertCircle, ArrowDownUp, Check, X } from 'lucide-react';
import React, { useState } from 'react';
import { InventoryItem } from './types';

interface RecordMutationModalProps {
    isOpen: boolean;
    onClose: () => void;
    item: InventoryItem | null;
    movementTypes: Array<{ value: string; label: string; multiplier: number }>;
}

export const RecordMutationModal: React.FC<RecordMutationModalProps> = ({
    isOpen,
    onClose,
    item,
    movementTypes,
}) => {
    const [type, setType] = useState('PURCHASE');
    const [quantity, setQuantity] = useState(1);
    const [unitCost, setUnitCost] = useState(0);
    const [notes, setNotes] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    if (!isOpen || !item) return null;

    const selectedTypeObj = movementTypes.find((m) => m.value === type);
    const multiplier = selectedTypeObj ? selectedTypeObj.multiplier : 1;
    const stockDelta = multiplier * (quantity || 0);
    const estimatedFinalStock = (item?.current_stock ?? 0) + stockDelta;
    const isNegativeInvalid = estimatedFinalStock < 0 && !item?.allow_negative_stock;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isNegativeInvalid) {
            setError('Stok akhir tidak boleh negatif untuk item ini.');
            return;
        }

        setLoading(true);
        setError(null);

        router.post(
            `/app/inventory/items/${item.id}/movements`,
            {
                type,
                quantity,
                unit_cost_idr: unitCost,
                notes,
            },
            {
                onSuccess: () => {
                    setLoading(false);
                    onClose();
                },
                onError: (err) => {
                    setLoading(false);
                    setError(err.general || Object.values(err)[0] || 'Gagal mencatat mutasi.');
                },
            }
        );
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
            <div className="bg-white rounded-xl shadow-xl w-full max-w-lg border border-slate-200">
                <div className="flex items-center justify-between p-5 border-b border-slate-100">
                    <div className="flex items-center gap-2">
                        <div className="p-2 bg-blue-50 text-blue-600 rounded-lg">
                            <ArrowDownUp className="w-5 h-5" />
                        </div>
                        <div>
                            <h3 className="font-semibold text-slate-900">Catat Mutasi Stok</h3>
                            <p className="text-xs text-slate-500">
                                {item.name} ({item.sku || 'No SKU'})
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-1 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="p-5 space-y-4">
                    {error && (
                        <div className="flex items-center gap-2 p-3 text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg">
                            <AlertCircle className="w-4 h-4 shrink-0" />
                            <span>{error}</span>
                        </div>
                    )}

                    {/* Stock Overview Banner */}
                    <div className="p-3 bg-slate-50 border border-slate-200 rounded-lg flex items-center justify-between text-xs">
                        <div>
                            <span className="text-slate-500">Stok Saat Ini:</span>
                            <span className="ml-1 font-semibold text-slate-900">
                                {item.current_stock} {item.unit}
                            </span>
                        </div>
                        <div>
                            <span className="text-slate-500">Direservasi Booking:</span>
                            <span className="ml-1 font-semibold text-amber-600">
                                {item.reserved_stock} {item.unit}
                            </span>
                        </div>
                        <div>
                            <span className="text-slate-500">Tersedia:</span>
                            <span className="ml-1 font-semibold text-emerald-600">
                                {item.available_stock} {item.unit}
                            </span>
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Tipe Mutasi <span className="text-red-500">*</span>
                        </label>
                        <select
                            value={type}
                            onChange={(e) => setType(e.target.value)}
                            className="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent"
                        >
                            {movementTypes.map((t) => (
                                <option key={t.value} value={t.value}>
                                    {t.label} ({t.multiplier > 0 ? '+ Tambah' : '- Kurang'})
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1">
                                Jumlah ({item.unit}) <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="number"
                                min={1}
                                required
                                value={quantity}
                                onChange={(e) => setQuantity(Math.max(1, parseInt(e.target.value) || 1))}
                                className="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1">
                                Biaya Satuan (IDR - Opsional)
                            </label>
                            <input
                                type="number"
                                min={0}
                                value={unitCost}
                                onChange={(e) => setUnitCost(parseInt(e.target.value) || 0)}
                                className="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent"
                            />
                        </div>
                    </div>

                    {/* Resulting stock preview */}
                    <div
                        className={`p-3 rounded-lg border text-xs flex items-center justify-between ${
                            isNegativeInvalid
                                ? 'bg-red-50 border-red-200 text-red-700'
                                : 'bg-blue-50/50 border-blue-200 text-blue-900'
                        }`}
                    >
                        <span>Estimasi Stok Baru:</span>
                        <div className="font-semibold text-sm">
                            {item.current_stock} {multiplier > 0 ? '+' : '-'} {quantity} ={' '}
                            <span className={isNegativeInvalid ? 'text-red-600 underline' : 'text-blue-700'}>
                                {estimatedFinalStock} {item.unit}
                            </span>
                        </div>
                    </div>
                    {isNegativeInvalid && (
                        <p className="text-xs text-red-600">
                            Peringatan: Stok item ini tidak boleh minus. Kurangi jumlah mutasi keluar.
                        </p>
                    )}

                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Catatan / Keterangan Mutasi
                        </label>
                        <textarea
                            rows={2}
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            placeholder="Contoh: Faktur Pembelian #INV-998 / Barang rusak saat pemakaian"
                            className="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent"
                        />
                    </div>

                    <div className="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 text-xs font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={loading || isNegativeInvalid}
                            className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50"
                        >
                            <Check className="w-4 h-4" />
                            {loading ? 'Menyimpan...' : 'Catat Mutasi'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};
