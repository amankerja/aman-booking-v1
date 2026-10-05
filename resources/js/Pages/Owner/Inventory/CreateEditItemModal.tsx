import { router } from '@inertiajs/react';
import { AlertCircle, Check, Package, X } from 'lucide-react';
import React, { useEffect, useState } from 'react';
import { InventoryItem } from './types';

interface CreateEditItemModalProps {
    isOpen: boolean;
    onClose: () => void;
    itemToEdit: InventoryItem | null;
    categories: string[];
}

export const CreateEditItemModal: React.FC<CreateEditItemModalProps> = ({
    isOpen,
    onClose,
    itemToEdit,
    categories,
}) => {
    const isEdit = itemToEdit !== null;

    const [form, setForm] = useState({
        sku: '',
        name: '',
        description: '',
        category: '',
        unit: 'pcs',
        cost_price_idr: 0,
        sale_price_idr: 0,
        initial_stock: 0,
        minimum_stock: 5,
        allow_negative_stock: false,
        is_active: true,
    });

    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (itemToEdit) {
            setForm({
                sku: itemToEdit.sku || '',
                name: itemToEdit.name,
                description: itemToEdit.description || '',
                category: itemToEdit.category || '',
                unit: itemToEdit.unit || 'pcs',
                cost_price_idr: itemToEdit.cost_price_idr,
                sale_price_idr: itemToEdit.sale_price_idr || 0,
                initial_stock: itemToEdit.current_stock,
                minimum_stock: itemToEdit.minimum_stock,
                allow_negative_stock: itemToEdit.allow_negative_stock,
                is_active: itemToEdit.is_active,
            });
        } else {
            setForm({
                sku: '',
                name: '',
                description: '',
                category: categories[0] || 'Bahan Habis Pakai',
                unit: 'pcs',
                cost_price_idr: 0,
                sale_price_idr: 0,
                initial_stock: 0,
                minimum_stock: 5,
                allow_negative_stock: false,
                is_active: true,
            });
        }
        setErrors({});
    }, [itemToEdit, isOpen]);

    if (!isOpen) return null;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        if (isEdit && itemToEdit) {
            router.put(`/app/inventory/items/${itemToEdit.id}`, form, {
                onSuccess: () => {
                    setLoading(false);
                    onClose();
                },
                onError: (err) => {
                    setLoading(false);
                    setErrors(err);
                },
            });
        } else {
            router.post('/app/inventory/items', form, {
                onSuccess: () => {
                    setLoading(false);
                    onClose();
                },
                onError: (err) => {
                    setLoading(false);
                    setErrors(err);
                },
            });
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
            <div className="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto border border-slate-200">
                <div className="flex items-center justify-between p-5 border-b border-slate-100">
                    <div className="flex items-center gap-2">
                        <div className="p-2 bg-blue-50 text-blue-600 rounded-lg">
                            <Package className="w-5 h-5" />
                        </div>
                        <div>
                            <h3 className="font-semibold text-slate-900">
                                {isEdit ? 'Edit Item Inventori' : 'Tambah Item Inventori'}
                            </h3>
                            <p className="text-xs text-slate-500">
                                {isEdit
                                    ? 'Perbarui detail barang atau parameter minimum stok'
                                    : 'Tambahkan bahan atau barang yang digunakan dalam operasional'}
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
                    {errors.general && (
                        <div className="flex items-center gap-2 p-3 text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg">
                            <AlertCircle className="w-4 h-4 shrink-0" />
                            <span>{errors.general}</span>
                        </div>
                    )}

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1">
                                Kode / SKU (Opsional)
                            </label>
                            <input
                                type="text"
                                value={form.sku}
                                onChange={(e) => setForm({ ...form, sku: e.target.value })}
                                placeholder="Contoh: ITM-001"
                                className="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent"
                            />
                            {errors.sku && <p className="text-xs text-red-600 mt-1">{errors.sku}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1">
                                Kategori
                            </label>
                            <input
                                type="text"
                                list="inventory-categories"
                                value={form.category}
                                onChange={(e) => setForm({ ...form, category: e.target.value })}
                                placeholder="Contoh: Consumable / Obat / Minyak"
                                className="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent"
                            />
                            <datalist id="inventory-categories">
                                {categories.map((c) => (
                                    <option key={c} value={c} />
                                ))}
                            </datalist>
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Nama Barang / Bahan <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            required
                            value={form.name}
                            onChange={(e) => setForm({ ...form, name: e.target.value })}
                            placeholder="Contoh: Minyak Pijat Aromaterapi 1000ml"
                            className="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent"
                        />
                        {errors.name && <p className="text-xs text-red-600 mt-1">{errors.name}</p>}
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1">
                                Satuan <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                required
                                value={form.unit}
                                onChange={(e) => setForm({ ...form, unit: e.target.value })}
                                placeholder="pcs, ml, btl, box"
                                className="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent"
                            />
                            {errors.unit && <p className="text-xs text-red-600 mt-1">{errors.unit}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1">
                                Harga Modal (IDR)
                            </label>
                            <input
                                type="number"
                                min={0}
                                value={form.cost_price_idr}
                                onChange={(e) => setForm({ ...form, cost_price_idr: parseInt(e.target.value) || 0 })}
                                className="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent"
                            />
                            {errors.cost_price_idr && <p className="text-xs text-red-600 mt-1">{errors.cost_price_idr}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1">
                                Harga Jual (IDR - Opsional)
                            </label>
                            <input
                                type="number"
                                min={0}
                                value={form.sale_price_idr}
                                onChange={(e) => setForm({ ...form, sale_price_idr: parseInt(e.target.value) || 0 })}
                                className="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                        {!isEdit && (
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Stok Awal Masuk
                                </label>
                                <input
                                    type="number"
                                    min={0}
                                    value={form.initial_stock}
                                    onChange={(e) => setForm({ ...form, initial_stock: parseInt(e.target.value) || 0 })}
                                    className="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent"
                                />
                                <span className="text-[11px] text-slate-400">Dicatat otomatis sebagai mutasi Stok Awal</span>
                            </div>
                        )}

                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1">
                                Peringatan Stok Minimum
                            </label>
                            <input
                                type="number"
                                min={0}
                                value={form.minimum_stock}
                                onChange={(e) => setForm({ ...form, minimum_stock: parseInt(e.target.value) || 0 })}
                                className="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent"
                            />
                            <span className="text-[11px] text-slate-400">Status berubah ke 'Menipis' jika stok ≤ nilai ini</span>
                        </div>
                    </div>

                    <div className="p-3 bg-slate-50 rounded-lg border border-slate-200 space-y-2">
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={form.allow_negative_stock}
                                onChange={(e) => setForm({ ...form, allow_negative_stock: e.target.checked })}
                                className="w-4 h-4 text-blue-600 rounded-sm border-slate-300 focus:ring-blue-500"
                            />
                            <span className="text-xs font-medium text-slate-800">
                                Izinkan Stok Negatif (Minus)
                            </span>
                        </label>
                        <p className="text-[11px] text-slate-500 pl-6">
                            Jika diaktifkan, booking atau penjualan tetap dapat diproses meski stok tercatat 0 atau kurang.
                        </p>
                    </div>

                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                            Catatan / Deskripsi (Opsional)
                        </label>
                        <textarea
                            rows={2}
                            value={form.description}
                            onChange={(e) => setForm({ ...form, description: e.target.value })}
                            placeholder="Keterangan tambahan barang..."
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
                            disabled={loading}
                            className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50"
                        >
                            <Check className="w-4 h-4" />
                            {loading ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Tambah Barang'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};
