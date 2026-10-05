import { ArrowDown, ArrowUp, Calendar, History, Package, User, X } from 'lucide-react';
import React, { useEffect, useState } from 'react';
import { InventoryItem, InventoryMovement } from './types';

interface ItemMovementDrawerProps {
    isOpen: boolean;
    onClose: () => void;
    item: InventoryItem | null;
}

export const ItemMovementDrawer: React.FC<ItemMovementDrawerProps> = ({
    isOpen,
    onClose,
    item,
}) => {
    const [movements, setMovements] = useState<InventoryMovement[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (isOpen && item) {
            setLoading(true);
            fetch(`/app/inventory/items/${item.id}/movements`)
                .then((res) => res.json())
                .then((data) => {
                    setMovements(data.movements || []);
                    setLoading(false);
                })
                .catch(() => {
                    setLoading(false);
                });
        }
    }, [isOpen, item]);

    if (!isOpen || !item) return null;

    const getTypeColor = (type: string, multiplier: number) => {
        if (type === 'CONSUMED_BY_BOOKING') {
            return 'bg-blue-50 text-blue-700 border-blue-200';
        }
        if (multiplier > 0) {
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        }
        return 'bg-rose-50 text-rose-700 border-rose-200';
    };

    return (
        <div className="fixed inset-0 z-50 overflow-hidden bg-slate-900/50 backdrop-blur-xs flex justify-end">
            <div className="w-full max-w-xl bg-white h-full shadow-2xl flex flex-col border-l border-slate-200 animate-in slide-in-from-right duration-200">
                {/* Header */}
                <div className="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <div className="p-2 bg-blue-50 text-blue-600 rounded-lg">
                            <History className="w-5 h-5" />
                        </div>
                        <div>
                            <h3 className="font-semibold text-slate-900">Riwayat Mutasi Stok</h3>
                            <p className="text-xs text-slate-500">
                                {item.name} {item.sku ? `(${item.sku})` : ''}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                {/* Stock Stats strip */}
                <div className="p-4 bg-slate-50 border-b border-slate-100 grid grid-cols-3 gap-2 text-center text-xs">
                    <div className="bg-white p-2.5 rounded-lg border border-slate-200">
                        <span className="text-slate-400 block text-[10px]">Stok Fisik</span>
                        <span className="font-bold text-slate-900 text-sm">
                            {item.current_stock} {item.unit}
                        </span>
                    </div>
                    <div className="bg-white p-2.5 rounded-lg border border-slate-200">
                        <span className="text-slate-400 block text-[10px]">Reservasi Booking</span>
                        <span className="font-bold text-amber-600 text-sm">
                            {item.reserved_stock} {item.unit}
                        </span>
                    </div>
                    <div className="bg-white p-2.5 rounded-lg border border-slate-200">
                        <span className="text-slate-400 block text-[10px]">Stok Tersedia</span>
                        <span className="font-bold text-emerald-600 text-sm">
                            {item.available_stock} {item.unit}
                        </span>
                    </div>
                </div>

                {/* Movement Timeline */}
                <div className="flex-1 overflow-y-auto p-5 space-y-3">
                    {loading ? (
                        <div className="text-center py-10 text-slate-400 text-xs">
                            Memuat riwayat mutasi...
                        </div>
                    ) : movements.length === 0 ? (
                        <div className="text-center py-12 text-slate-400">
                            <Package className="w-10 h-10 mx-auto mb-2 text-slate-300" />
                            <p className="text-sm font-medium text-slate-600">Belum ada riwayat mutasi</p>
                            <p className="text-xs text-slate-400">
                                Catat mutasi awal atau terima booking untuk melihat log mutasi.
                            </p>
                        </div>
                    ) : (
                        movements.map((m) => {
                            const isPositive = m.multiplier > 0;
                            return (
                                <div
                                    key={m.id}
                                    className="p-3.5 bg-white border border-slate-200 rounded-lg hover:border-slate-300 transition-colors"
                                >
                                    <div className="flex items-start justify-between gap-2 mb-1.5">
                                        <div className="flex items-center gap-1.5">
                                            <span
                                                className={`inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-medium rounded-full border ${getTypeColor(
                                                    m.type,
                                                    m.multiplier
                                                )}`}
                                            >
                                                {isPositive ? (
                                                    <ArrowUp className="w-3 h-3" />
                                                ) : (
                                                    <ArrowDown className="w-3 h-3" />
                                                )}
                                                {m.type_label}
                                            </span>
                                            {m.booking_code && (
                                                <span className="text-[11px] font-mono text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded">
                                                    {m.booking_code}
                                                </span>
                                            )}
                                        </div>
                                        <div className="text-right">
                                            <span
                                                className={`text-sm font-bold ${
                                                    isPositive ? 'text-emerald-600' : 'text-slate-800'
                                                }`}
                                            >
                                                {isPositive ? `+${m.quantity}` : `-${m.quantity}`} {item.unit}
                                            </span>
                                            <span className="block text-[10px] text-slate-400">
                                                {m.stock_before} &rarr; {m.stock_after} {item.unit}
                                            </span>
                                        </div>
                                    </div>

                                    {m.notes && (
                                        <p className="text-xs text-slate-600 mb-2 italic bg-slate-50 p-2 rounded border border-slate-100">
                                            "{m.notes}"
                                        </p>
                                    )}

                                    <div className="flex items-center justify-between text-[11px] text-slate-400 pt-1 border-t border-slate-100">
                                        <div className="flex items-center gap-1">
                                            <User className="w-3 h-3" />
                                            <span>{m.actor_name}</span>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <Calendar className="w-3 h-3" />
                                            <span>{m.created_at}</span>
                                        </div>
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>

                {/* Footer */}
                <div className="p-4 border-t border-slate-100 bg-slate-50 flex justify-end">
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2 text-xs font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-100"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    );
};
