import { router } from '@inertiajs/react';
import { AlertCircle, Check, Layers, Plus, Trash2, X } from 'lucide-react';
import React, { useEffect, useState } from 'react';
import { InventoryItem, ServiceWithInventory } from './types';

interface ServiceMappingModalProps {
    isOpen: boolean;
    onClose: () => void;
    services: ServiceWithInventory[];
    inventoryItems: InventoryItem[];
    stockModes: Array<{ value: string; label: string }>;
}

export const ServiceMappingModal: React.FC<ServiceMappingModalProps> = ({
    isOpen,
    onClose,
    services,
    inventoryItems,
    stockModes,
}) => {
    if (!isOpen) return null;

    const [selectedServiceId, setSelectedServiceId] = useState<number>(services[0]?.id || 0);
    const [mappings, setMappings] = useState<
        Array<{
            inventory_item_id: number;
            quantity: number;
            deduction_mode: string | null;
        }>
    >([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Update mappings when selected service changes
    useEffect(() => {
        const found = services.find((s) => s.id === selectedServiceId);
        if (found && found.inventory_items) {
            setMappings(
                found.inventory_items.map((m) => ({
                    inventory_item_id: m.inventory_item_id,
                    quantity: m.quantity,
                    deduction_mode: m.deduction_mode,
                }))
            );
        } else {
            setMappings([]);
        }
    }, [selectedServiceId, services]);

    const handleAddRow = () => {
        if (inventoryItems.length === 0) return;
        setMappings([
            ...mappings,
            {
                inventory_item_id: inventoryItems[0].id,
                quantity: 1,
                deduction_mode: null,
            },
        ]);
    };

    const handleRemoveRow = (index: number) => {
        setMappings(mappings.filter((_, i) => i !== index));
    };

    const handleUpdateRow = (
        index: number,
        field: 'inventory_item_id' | 'quantity' | 'deduction_mode',
        value: any
    ) => {
        const updated = [...mappings];
        updated[index] = { ...updated[index], [field]: value };
        setMappings(updated);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setError(null);

        router.post(
            `/app/inventory/services/${selectedServiceId}/mappings`,
            { mappings },
            {
                onSuccess: () => {
                    setLoading(false);
                    onClose();
                },
                onError: (err) => {
                    setLoading(false);
                    setError(err.general || Object.values(err)[0] || 'Gagal menyimpan mapping kebutuhan bahan.');
                },
            }
        );
    };

    const selectedService = services.find((s) => s.id === selectedServiceId);

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
            <div className="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto border border-slate-200">
                <div className="flex items-center justify-between p-5 border-b border-slate-100">
                    <div className="flex items-center gap-2">
                        <div className="p-2 bg-blue-50 text-blue-600 rounded-lg">
                            <Layers className="w-5 h-5" />
                        </div>
                        <div>
                            <h3 className="font-semibold text-slate-900">Kebutuhan Bahan per Layanan</h3>
                            <p className="text-xs text-slate-500">
                                Tentukan barang/bahan yang otomatis dialokasikan saat booking layanan
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

                    {/* Service Selector */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Pilih Layanan
                        </label>
                        <select
                            value={selectedServiceId}
                            onChange={(e) => setSelectedServiceId(Number(e.target.value))}
                            className="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent font-medium"
                        >
                            {services.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.name} ({s.duration_minutes} mnt - Rp {Number(s.price_idr).toLocaleString('id-ID')})
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Mappings Table */}
                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <label className="text-xs font-semibold text-slate-700">
                                Daftar Item yang Dikonsumsi
                            </label>
                            <button
                                type="button"
                                onClick={handleAddRow}
                                className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-md transition-colors"
                            >
                                <Plus className="w-3.5 h-3.5" />
                                Tambah Item Bahan
                            </button>
                        </div>

                        {mappings.length === 0 ? (
                            <div className="p-6 text-center border border-dashed border-slate-200 rounded-lg bg-slate-50 text-slate-400 text-xs">
                                Belum ada bahan yang dialokasikan untuk layanan{' '}
                                <span className="font-semibold text-slate-600">
                                    {selectedService?.name}
                                </span>
                                . Klik tombol "Tambah Item Bahan" di atas.
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {mappings.map((row, idx) => (
                                    <div
                                        key={idx}
                                        className="p-3 bg-slate-50 border border-slate-200 rounded-lg grid grid-cols-1 md:grid-cols-12 gap-2 items-center text-xs"
                                    >
                                        <div className="md:col-span-5">
                                            <span className="block text-[10px] text-slate-400 md:hidden mb-1">
                                                Item
                                            </span>
                                            <select
                                                value={row.inventory_item_id}
                                                onChange={(e) =>
                                                    handleUpdateRow(
                                                        idx,
                                                        'inventory_item_id',
                                                        Number(e.target.value)
                                                    )
                                                }
                                                className="w-full px-2 py-1.5 bg-white border border-slate-200 rounded text-xs"
                                            >
                                                {inventoryItems.map((item) => (
                                                    <option key={item.id} value={item.id}>
                                                        {item.name} ({item.current_stock} {item.unit})
                                                    </option>
                                                ))}
                                            </select>
                                        </div>

                                        <div className="md:col-span-2">
                                            <span className="block text-[10px] text-slate-400 md:hidden mb-1">
                                                Qty
                                            </span>
                                            <input
                                                type="number"
                                                min={1}
                                                required
                                                value={row.quantity}
                                                onChange={(e) =>
                                                    handleUpdateRow(
                                                        idx,
                                                        'quantity',
                                                        Math.max(1, parseInt(e.target.value) || 1)
                                                    )
                                                }
                                                className="w-full px-2 py-1.5 bg-white border border-slate-200 rounded text-xs"
                                                placeholder="Qty"
                                            />
                                        </div>

                                        <div className="md:col-span-4">
                                            <span className="block text-[10px] text-slate-400 md:hidden mb-1">
                                                Mode Deduksi
                                            </span>
                                            <select
                                                value={row.deduction_mode || ''}
                                                onChange={(e) =>
                                                    handleUpdateRow(
                                                        idx,
                                                        'deduction_mode',
                                                        e.target.value || null
                                                    )
                                                }
                                                className="w-full px-2 py-1.5 bg-white border border-slate-200 rounded text-xs"
                                            >
                                                <option value="">Default Bisnis</option>
                                                {stockModes.map((m) => (
                                                    <option key={m.value} value={m.value}>
                                                        {m.label}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>

                                        <div className="md:col-span-1 text-right">
                                            <button
                                                type="button"
                                                onClick={() => handleRemoveRow(idx)}
                                                className="p-1 text-slate-400 hover:text-red-600 rounded hover:bg-red-50"
                                            >
                                                <Trash2 className="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="p-3 bg-blue-50/50 border border-blue-100 rounded-lg text-xs text-blue-800">
                        <p className="font-semibold mb-0.5">Cara Kerja Alokasi Stok:</p>
                        <p className="text-[11px] text-blue-700">
                            Saat customer memesan layanan ini, stok item di atas akan otomatis diperiksa dan
                            direservasi/dipotong sesuai mode yang dipilih. Jika stok habis dan stok minus tidak
                            diizinkan, booking akan ditolak secara otomatis untuk mencegah double booking bahan.
                        </p>
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
                            disabled={loading}
                            className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50"
                        >
                            <Check className="w-4 h-4" />
                            {loading ? 'Menyimpan...' : 'Simpan Kebutuhan Bahan'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};
