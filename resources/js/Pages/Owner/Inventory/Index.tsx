import { Head, router } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    ArrowDownUp,
    Boxes,
    Coins,
    Edit2,
    Eye,
    Filter,
    History,
    Layers,
    Package,
    Plus,
    Search,
    SlidersHorizontal,
    Trash2,
} from 'lucide-react';
import React, { useState } from 'react';
import { OwnerLayout } from '../../../Layouts/OwnerLayout';
import { CreateEditItemModal } from './CreateEditItemModal';
import { ItemMovementDrawer } from './ItemMovementDrawer';
import { RecordMutationModal } from './RecordMutationModal';
import { ServiceMappingModal } from './ServiceMappingModal';
import {
    InventoryItem,
    InventoryStats,
    ServiceWithInventory,
} from './types';

interface IndexProps {
    is_module_active: boolean;
    items: {
        data: InventoryItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    stats: InventoryStats;
    categories: string[];
    services: ServiceWithInventory[];
    filters: {
        search: string;
        category: string;
        low_stock: boolean;
    };
    movement_types: Array<{ value: string; label: string; multiplier: number }>;
    stock_modes: Array<{ value: string; label: string }>;
}

export default function Index({
    is_module_active,
    items,
    stats,
    categories,
    services,
    filters,
    movement_types,
    stock_modes,
}: IndexProps) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedCategory, setSelectedCategory] = useState(filters.category || '');
    const [lowStockFilter, setLowStockFilter] = useState(filters.low_stock || false);

    // Modals
    const [isCreateEditOpen, setIsCreateEditOpen] = useState(false);
    const [itemToEdit, setItemToEdit] = useState<InventoryItem | null>(null);

    const [isMutationOpen, setIsMutationOpen] = useState(false);
    const [selectedItemForMutation, setSelectedItemForMutation] = useState<InventoryItem | null>(null);

    const [isHistoryOpen, setIsHistoryOpen] = useState(false);
    const [selectedItemForHistory, setSelectedItemForHistory] = useState<InventoryItem | null>(null);

    const [isMappingOpen, setIsMappingOpen] = useState(false);

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/app/inventory',
            {
                search: searchTerm,
                category: selectedCategory,
                low_stock: lowStockFilter ? '1' : '',
            },
            { preserveState: true }
        );
    };

    const handleCategoryChange = (cat: string) => {
        setSelectedCategory(cat);
        router.get(
            '/app/inventory',
            {
                search: searchTerm,
                category: cat,
                low_stock: lowStockFilter ? '1' : '',
            },
            { preserveState: true }
        );
    };

    const handleLowStockToggle = (checked: boolean) => {
        setLowStockFilter(checked);
        router.get(
            '/app/inventory',
            {
                search: searchTerm,
                category: selectedCategory,
                low_stock: checked ? '1' : '',
            },
            { preserveState: true }
        );
    };

    const handleResetFilters = () => {
        setSearchTerm('');
        setSelectedCategory('');
        setLowStockFilter(false);
        router.get('/app/inventory');
    };

    const handleDeleteItem = (item: InventoryItem) => {
        if (item.reserved_stock > 0) {
            alert(`Item '${item.name}' sedang direservasi untuk booking aktif dan tidak dapat dihapus.`);
            return;
        }

        if (confirm(`Apakah Anda yakin ingin menghapus item '${item.name}'?`)) {
            router.delete(`/app/inventory/items/${item.id}`);
        }
    };

    const handleToggleModule = (enable: boolean) => {
        router.post('/app/inventory/toggle', { enabled: enable });
    };

    const headerActions = (
        <div className="flex items-center gap-2">
            <button
                type="button"
                onClick={() => setIsMappingOpen(true)}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors shadow-2xs"
            >
                <Layers className="w-4 h-4 text-slate-500" />
                Kebutuhan Layanan
            </button>
            <button
                type="button"
                onClick={() => {
                    setItemToEdit(null);
                    setIsCreateEditOpen(true);
                }}
                className="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors shadow-2xs"
            >
                <Plus className="w-4 h-4" />
                Tambah Barang
            </button>
        </div>
    );

    return (
        <OwnerLayout
            title="Inventori & Stok"
            breadcrumbs={[{ label: 'Dashboard', href: '/app/dashboard' }, { label: 'Inventori' }]}
            actions={headerActions}
        >
            <Head title="Inventori & Stok" />

            <div className="space-y-6">
                {/* Module status banner if disabled */}
                {!is_module_active && (
                    <div className="p-4 bg-amber-50 border border-amber-200 rounded-xl flex items-center justify-between gap-4">
                        <div className="flex items-center gap-3">
                            <div className="p-2 bg-amber-100 text-amber-800 rounded-lg shrink-0">
                                <AlertCircle className="w-5 h-5" />
                            </div>
                            <div>
                                <h4 className="text-sm font-semibold text-amber-900">
                                    Modul Inventori Sedang Dinonaktifkan
                                </h4>
                                <p className="text-xs text-amber-700">
                                    Menu inventori disembunyikan dari navigasi umum dan reservasi stok saat
                                    booking diabaikan hingga modul ini diaktifkan.
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            onClick={() => handleToggleModule(true)}
                            className="px-3 py-1.5 text-xs font-semibold text-white bg-amber-600 hover:bg-amber-700 rounded-lg shrink-0 transition-colors"
                        >
                            Aktifkan Modul
                        </button>
                    </div>
                )}

                {/* 4 KPI Cards (OFALabs Style) */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {/* Card 1: Total Items */}
                    <div className="p-4 bg-white border border-slate-200 rounded-xl shadow-2xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500">Total Item Barang</span>
                            <div className="p-2 bg-blue-50 text-blue-600 rounded-lg">
                                <Boxes className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="mt-2 flex items-baseline gap-2">
                            <span className="text-2xl font-bold text-slate-900">
                                {stats.total_items}
                            </span>
                            <span className="text-xs text-slate-400">item</span>
                        </div>
                        <p className="text-[11px] text-slate-400 mt-1">Bahan & produk terdaftar</p>
                    </div>

                    {/* Card 2: Total Valuation */}
                    <div className="p-4 bg-white border border-slate-200 rounded-xl shadow-2xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500">Total Nilai Stok</span>
                            <div className="p-2 bg-emerald-50 text-emerald-600 rounded-lg">
                                <Coins className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="mt-2 flex items-baseline gap-1">
                            <span className="text-lg font-bold text-slate-900 truncate">
                                Rp {stats.total_valuation_idr.toLocaleString('id-ID')}
                            </span>
                        </div>
                        <p className="text-[11px] text-slate-400 mt-1">Berdasarkan harga modal</p>
                    </div>

                    {/* Card 3: Low Stock Items */}
                    <div className="p-4 bg-white border border-slate-200 rounded-xl shadow-2xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500">Stok Menipis</span>
                            <div
                                className={`p-2 rounded-lg ${
                                    stats.low_stock_count > 0
                                        ? 'bg-rose-50 text-rose-600'
                                        : 'bg-slate-100 text-slate-500'
                                }`}
                            >
                                <AlertTriangle className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="mt-2 flex items-baseline gap-2">
                            <span
                                className={`text-2xl font-bold ${
                                    stats.low_stock_count > 0 ? 'text-rose-600' : 'text-slate-900'
                                }`}
                            >
                                {stats.low_stock_count}
                            </span>
                            <span className="text-xs text-slate-400">item</span>
                        </div>
                        <p className="text-[11px] text-slate-400 mt-1">
                            {stats.low_stock_count > 0
                                ? 'Perlu pengadaan / restock'
                                : 'Semua item di atas batas aman'}
                        </p>
                    </div>

                    {/* Card 4: Monthly Mutations */}
                    <div className="p-4 bg-white border border-slate-200 rounded-xl shadow-2xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-slate-500">Mutasi Bulan Ini</span>
                            <div className="p-2 bg-purple-50 text-purple-600 rounded-lg">
                                <ArrowDownUp className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="mt-2 flex items-baseline gap-2">
                            <span className="text-2xl font-bold text-slate-900">
                                {stats.movements_this_month}
                            </span>
                            <span className="text-xs text-slate-400">transaksi</span>
                        </div>
                        <p className="text-[11px] text-slate-400 mt-1">Masuk, keluar & pemakaian booking</p>
                    </div>
                </div>

                {/* Search & Filter Toolbar */}
                <div className="p-4 bg-white border border-slate-200 rounded-xl shadow-2xs flex flex-col md:flex-row gap-3 items-center justify-between">
                    <form onSubmit={handleSearchSubmit} className="flex-1 w-full flex items-center gap-2">
                        <div className="relative flex-1">
                            <Search className="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
                            <input
                                type="text"
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                placeholder="Cari nama barang, SKU, atau kategori..."
                                className="w-full pl-9 pr-3 py-1.5 text-xs border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent"
                            />
                        </div>
                        <button
                            type="submit"
                            className="px-3 py-1.5 text-xs font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors"
                        >
                            Cari
                        </button>
                    </form>

                    <div className="flex items-center gap-2 w-full md:w-auto justify-between md:justify-end">
                        {/* Category Dropdown */}
                        <div className="flex items-center gap-1.5">
                            <Filter className="w-3.5 h-3.5 text-slate-400" />
                            <select
                                value={selectedCategory}
                                onChange={(e) => handleCategoryChange(e.target.value)}
                                className="px-2.5 py-1.5 text-xs border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent bg-white text-slate-700"
                            >
                                <option value="">Semua Kategori</option>
                                {categories.map((cat) => (
                                    <option key={cat} value={cat}>
                                        {cat}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Low Stock Filter Toggle */}
                        <label className="flex items-center gap-1.5 text-xs text-slate-700 cursor-pointer select-none px-2.5 py-1.5 rounded-lg border border-slate-200 bg-slate-50">
                            <input
                                type="checkbox"
                                checked={lowStockFilter}
                                onChange={(e) => handleLowStockToggle(e.target.checked)}
                                className="w-3.5 h-3.5 text-blue-600 rounded-sm border-slate-300 focus:ring-blue-500"
                            />
                            <span>Hanya Stok Menipis</span>
                        </label>

                        {(searchTerm || selectedCategory || lowStockFilter) && (
                            <button
                                type="button"
                                onClick={handleResetFilters}
                                className="text-xs text-slate-500 hover:text-slate-800 underline px-1"
                            >
                                Reset
                            </button>
                        )}
                    </div>
                </div>

                {/* Inventory Table */}
                <div className="bg-white border border-slate-200 rounded-xl shadow-2xs overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs text-slate-600">
                            <thead className="bg-slate-50 border-b border-slate-200 text-slate-700 font-semibold uppercase text-[10px] tracking-wider">
                                <tr>
                                    <th className="px-4 py-3">SKU / Kode</th>
                                    <th className="px-4 py-3">Nama Barang</th>
                                    <th className="px-4 py-3">Kategori</th>
                                    <th className="px-4 py-3 text-right">Stok Fisik</th>
                                    <th className="px-4 py-3 text-right">Direservasi</th>
                                    <th className="px-4 py-3 text-right">Tersedia</th>
                                    <th className="px-4 py-3 text-right">Harga Modal</th>
                                    <th className="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {items.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="text-center py-12 text-slate-400">
                                            <Package className="w-8 h-8 mx-auto mb-2 text-slate-300" />
                                            <p className="font-medium text-slate-600">Tidak ada item inventori</p>
                                            <p className="text-[11px] text-slate-400">
                                                Tambahkan barang baru untuk mulai mengelola stok dan alokasi booking.
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    items.data.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="hover:bg-slate-50/80 transition-colors"
                                        >
                                            <td className="px-4 py-3 font-mono text-slate-500">
                                                {item.sku || '-'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-slate-900">{item.name}</div>
                                                {item.is_low_stock && (
                                                    <span className="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-semibold bg-rose-50 text-rose-600 border border-rose-200 rounded mt-0.5">
                                                        <AlertTriangle className="w-2.5 h-2.5" />
                                                        Stok Menipis (&le; {item.minimum_stock})
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-slate-500">
                                                {item.category || '-'}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium text-slate-900">
                                                {item.current_stock} {item.unit}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {item.reserved_stock > 0 ? (
                                                    <span className="font-semibold text-amber-600">
                                                        {item.reserved_stock} {item.unit}
                                                    </span>
                                                ) : (
                                                    <span className="text-slate-400">0</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right font-bold">
                                                <span
                                                    className={
                                                        item.available_stock <= 0
                                                            ? 'text-rose-600'
                                                            : 'text-emerald-600'
                                                    }
                                                >
                                                    {item.available_stock} {item.unit}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-right text-slate-700">
                                                Rp {item.cost_price_idr.toLocaleString('id-ID')}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    {/* Catat Mutasi */}
                                                    <button
                                                        type="button"
                                                        title="Catat Mutasi Masuk/Keluar"
                                                        onClick={() => {
                                                            setSelectedItemForMutation(item);
                                                            setIsMutationOpen(true);
                                                        }}
                                                        className="p-1.5 text-blue-600 hover:bg-blue-50 rounded-md transition-colors"
                                                    >
                                                        <ArrowDownUp className="w-4 h-4" />
                                                    </button>

                                                    {/* Riwayat Mutasi */}
                                                    <button
                                                        type="button"
                                                        title="Riwayat Mutasi"
                                                        onClick={() => {
                                                            setSelectedItemForHistory(item);
                                                            setIsHistoryOpen(true);
                                                        }}
                                                        className="p-1.5 text-slate-500 hover:bg-slate-100 rounded-md transition-colors"
                                                    >
                                                        <History className="w-4 h-4" />
                                                    </button>

                                                    {/* Edit */}
                                                    <button
                                                        type="button"
                                                        title="Edit Item"
                                                        onClick={() => {
                                                            setItemToEdit(item);
                                                            setIsCreateEditOpen(true);
                                                        }}
                                                        className="p-1.5 text-slate-500 hover:bg-slate-100 rounded-md transition-colors"
                                                    >
                                                        <Edit2 className="w-4 h-4" />
                                                    </button>

                                                    {/* Hapus */}
                                                    <button
                                                        type="button"
                                                        title="Hapus Item"
                                                        onClick={() => handleDeleteItem(item)}
                                                        className="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-md transition-colors"
                                                    >
                                                        <Trash2 className="w-4 h-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {items.last_page > 1 && (
                        <div className="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                            <div>
                                Menampilkan {items.data.length} dari {items.total} item
                            </div>
                            <div className="flex items-center gap-1">
                                {items.links.map((link, idx) => (
                                    <button
                                        key={idx}
                                        disabled={!link.url || link.active}
                                        onClick={() => link.url && router.get(link.url)}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        className={`px-2.5 py-1 rounded border text-xs ${
                                            link.active
                                                ? 'bg-blue-600 text-white border-blue-600 font-semibold'
                                                : link.url
                                                ? 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'
                                                : 'bg-slate-50 text-slate-300 border-slate-100 cursor-not-allowed'
                                        }`}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Modals & Drawers */}
            <CreateEditItemModal
                isOpen={isCreateEditOpen}
                onClose={() => setIsCreateEditOpen(false)}
                itemToEdit={itemToEdit}
                categories={categories}
            />

            <RecordMutationModal
                isOpen={isMutationOpen}
                onClose={() => setIsMutationOpen(false)}
                item={selectedItemForMutation}
                movementTypes={movement_types}
            />

            <ItemMovementDrawer
                isOpen={isHistoryOpen}
                onClose={() => setIsHistoryOpen(false)}
                item={selectedItemForHistory}
            />

            <ServiceMappingModal
                isOpen={isMappingOpen}
                onClose={() => setIsMappingOpen(false)}
                services={services}
                inventoryItems={items.data}
                stockModes={stock_modes}
            />
        </OwnerLayout>
    );
}
