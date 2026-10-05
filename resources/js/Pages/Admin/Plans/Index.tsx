import { router } from '@inertiajs/react';
import {
    AlertCircle,
    Check,
    CheckCircle2,
    CreditCard,
    Edit3,
    Layers,
    Shield,
    Users,
    X,
} from 'lucide-react';
import React, { useState } from 'react';
import { Button, Input, Modal, useToast } from '../../../Components/ui';
import { AdminLayout } from '../../../Layouts/AdminLayout';

interface PlanItem {
    id: number;
    code: string;
    name: string;
    price_idr: number;
    billing_cycle: string;
    limits: Record<string, any> | null;
    features: Record<string, any> | null;
    is_active: boolean;
    subscriptions_count?: number;
}

interface AdminPlansIndexProps {
    plans: PlanItem[];
}

export default function AdminPlansIndex({ plans }: AdminPlansIndexProps) {
    const toast = useToast();

    const [isEditOpen, setIsEditOpen] = useState(false);
    const [editingPlan, setEditingPlan] = useState<PlanItem | null>(null);
    const [editForm, setEditForm] = useState({
        name: '',
        price_idr: 0,
        billing_cycle: 'MONTHLY',
        max_members: 3,
        max_services: 5,
        max_monthly_bookings: 100,
        inventory_enabled: false,
        whatsapp_enabled: false,
        online_payments_enabled: false,
        is_active: true,
    });
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleOpenEdit = (plan: PlanItem) => {
        setEditingPlan(plan);
        setEditForm({
            name: plan.name,
            price_idr: plan.price_idr,
            billing_cycle: plan.billing_cycle || 'MONTHLY',
            max_members: plan.limits?.max_members || 3,
            max_services: plan.limits?.max_services || 5,
            max_monthly_bookings: plan.limits?.max_monthly_bookings || 100,
            inventory_enabled: Boolean(plan.features?.inventory),
            whatsapp_enabled: Boolean(plan.features?.whatsapp_notifications),
            online_payments_enabled: Boolean(plan.features?.online_payments),
            is_active: Boolean(plan.is_active),
        });
        setIsEditOpen(true);
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingPlan) return;
        setIsSubmitting(true);

        const updatedLimits = {
            ...(editingPlan.limits || {}),
            max_members: Number(editForm.max_members),
            max_services: Number(editForm.max_services),
            max_monthly_bookings: Number(editForm.max_monthly_bookings),
        };

        const updatedFeatures = {
            ...(editingPlan.features || {}),
            inventory: editForm.inventory_enabled,
            whatsapp_notifications: editForm.whatsapp_enabled,
            online_payments: editForm.online_payments_enabled,
        };

        router.put(
            `/admin/plans/${editingPlan.id}`,
            {
                name: editForm.name,
                price_idr: Number(editForm.price_idr),
                billing_cycle: editForm.billing_cycle,
                limits: updatedLimits,
                features: updatedFeatures,
                is_active: editForm.is_active,
            },
            {
                onSuccess: () => {
                    setIsEditOpen(false);
                    setEditingPlan(null);
                    toast.success('Paket langganan berhasil diperbarui.');
                },
                onError: () => toast.error('Gagal memperbarui paket langganan.'),
                onFinish: () => setIsSubmitting(false),
            }
        );
    };

    return (
        <AdminLayout title="Manajemen Paket Langganan">
            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-bold tracking-tight text-slate-900">
                                Paket Langganan & Fitur
                            </h1>
                            <span className="rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700">
                                PRD 75
                            </span>
                        </div>
                        <p className="mt-0.5 text-xs text-slate-500">
                            Konfigurasi tier paket, kuota batas (limits), fitur aktif, dan harga tanpa merusak data tenant existing.
                        </p>
                    </div>
                </div>

                {/* Plans Grid */}
                <div className="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
                    {plans.map((p) => (
                        <div
                            key={p.id}
                            className={`flex flex-col justify-between rounded-[14px] border bg-white p-5 shadow-none transition-colors ${
                                p.is_active
                                    ? 'border-slate-200 hover:border-slate-300'
                                    : 'border-slate-200 bg-slate-50/50 opacity-75'
                            }`}
                        >
                            <div>
                                <div className="flex items-center justify-between">
                                    <span className="font-mono text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                                        {p.code}
                                    </span>
                                    <span
                                        className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${
                                            p.is_active
                                                ? 'bg-emerald-50 text-emerald-700'
                                                : 'bg-slate-100 text-slate-500'
                                        }`}
                                    >
                                        {p.is_active ? 'Aktif' : 'Nonaktif'}
                                    </span>
                                </div>

                                <div className="mt-2">
                                    <h3 className="text-lg font-bold text-slate-900">
                                        {p.name}
                                    </h3>
                                    <div className="mt-1 flex items-baseline gap-1">
                                        <span className="text-2xl font-extrabold text-slate-900">
                                            Rp {Number(p.price_idr).toLocaleString('id-ID')}
                                        </span>
                                        <span className="text-xs text-slate-400">
                                            / {p.billing_cycle}
                                        </span>
                                    </div>
                                    <p className="mt-1 text-[11px] text-slate-400">
                                        Digunakan oleh {p.subscriptions_count || 0} tenant aktif
                                    </p>
                                </div>

                                {/* Limits summary */}
                                <div className="mt-4 space-y-2 border-t border-slate-100 pt-3 text-xs">
                                    <div className="flex items-center justify-between text-slate-600">
                                        <span>Batas Anggota / Staf:</span>
                                        <span className="font-semibold text-slate-900">
                                            {p.limits?.max_members || 'Unlimited'}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between text-slate-600">
                                        <span>Batas Layanan:</span>
                                        <span className="font-semibold text-slate-900">
                                            {p.limits?.max_services || 'Unlimited'}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between text-slate-600">
                                        <span>Batas Booking / Bulan:</span>
                                        <span className="font-semibold text-slate-900">
                                            {p.limits?.max_monthly_bookings || 'Unlimited'}
                                        </span>
                                    </div>
                                </div>

                                {/* Features badges */}
                                <div className="mt-4 border-t border-slate-100 pt-3">
                                    <span className="mb-2 block text-[10px] font-semibold tracking-wider text-slate-400 uppercase">
                                        Fitur Termasuk
                                    </span>
                                    <div className="space-y-1.5 text-xs">
                                        <div className="flex items-center gap-2">
                                            {p.features?.whatsapp_notifications ? (
                                                <Check className="h-3.5 w-3.5 text-emerald-600" />
                                            ) : (
                                                <X className="h-3.5 w-3.5 text-slate-300" />
                                            )}
                                            <span className={p.features?.whatsapp_notifications ? 'text-slate-700' : 'text-slate-400'}>
                                                Notifikasi WhatsApp
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {p.features?.online_payments ? (
                                                <Check className="h-3.5 w-3.5 text-emerald-600" />
                                            ) : (
                                                <X className="h-3.5 w-3.5 text-slate-300" />
                                            )}
                                            <span className={p.features?.online_payments ? 'text-slate-700' : 'text-slate-400'}>
                                                Online Payment Gateway
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {p.features?.inventory ? (
                                                <Check className="h-3.5 w-3.5 text-emerald-600" />
                                            ) : (
                                                <X className="h-3.5 w-3.5 text-slate-300" />
                                            )}
                                            <span className={p.features?.inventory ? 'text-slate-700' : 'text-slate-400'}>
                                                Inventory & Stok
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="mt-5 pt-3 border-t border-slate-100">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => handleOpenEdit(p)}
                                    className="w-full gap-1.5 text-xs"
                                >
                                    <Edit3 className="h-3.5 w-3.5" />
                                    <span>Konfigurasi Paket</span>
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Edit Plan Modal */}
            <Modal
                isOpen={isEditOpen}
                onClose={() => setIsEditOpen(false)}
                title={`Edit Paket: ${editingPlan?.name}`}
                description="Perubahan batas kuota dan harga paket tersimpan tanpa mengubah histori tagihan lama (PRD 75)."
            >
                <form onSubmit={handleEditSubmit} className="space-y-3">
                    <Input
                        label="Nama Paket"
                        required
                        value={editForm.name}
                        onChange={(e) => setEditForm({ ...editForm, name: e.target.value })}
                    />

                    <div className="grid grid-cols-2 gap-2">
                        <Input
                            label="Harga (IDR)"
                            type="number"
                            required
                            min="0"
                            value={editForm.price_idr}
                            onChange={(e) => setEditForm({ ...editForm, price_idr: Number(e.target.value) })}
                        />

                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-700">
                                Siklus Tagihan
                            </label>
                            <select
                                value={editForm.billing_cycle}
                                onChange={(e) => setEditForm({ ...editForm, billing_cycle: e.target.value })}
                                className="h-9 w-full rounded-[8px] border border-slate-200 bg-[#f8fafc] px-3 text-xs text-slate-900 focus:border-blue-600 focus:bg-white focus:outline-none"
                            >
                                <option value="MONTHLY">Bulanan (MONTHLY)</option>
                                <option value="ANNUAL">Tahunan (ANNUAL)</option>
                            </select>
                        </div>
                    </div>

                    <div className="border-t border-slate-100 pt-2">
                        <span className="mb-2 block text-xs font-semibold text-slate-900">
                            Batas Kuota Resource (Limits)
                        </span>
                        <div className="grid grid-cols-3 gap-2">
                            <Input
                                label="Max Anggota"
                                type="number"
                                min="1"
                                value={editForm.max_members}
                                onChange={(e) => setEditForm({ ...editForm, max_members: Number(e.target.value) })}
                            />
                            <Input
                                label="Max Layanan"
                                type="number"
                                min="1"
                                value={editForm.max_services}
                                onChange={(e) => setEditForm({ ...editForm, max_services: Number(e.target.value) })}
                            />
                            <Input
                                label="Max Booking/Bulan"
                                type="number"
                                min="1"
                                value={editForm.max_monthly_bookings}
                                onChange={(e) => setEditForm({ ...editForm, max_monthly_bookings: Number(e.target.value) })}
                            />
                        </div>
                    </div>

                    <div className="border-t border-slate-100 pt-2 space-y-2">
                        <span className="block text-xs font-semibold text-slate-900">
                            Fitur Diaktifkan
                        </span>
                        <label className="flex items-center gap-2 text-xs">
                            <input
                                type="checkbox"
                                checked={editForm.whatsapp_enabled}
                                onChange={(e) => setEditForm({ ...editForm, whatsapp_enabled: e.target.checked })}
                                className="rounded border-slate-300 text-blue-600"
                            />
                            <span>Notifikasi WhatsApp Aktif</span>
                        </label>
                        <label className="flex items-center gap-2 text-xs">
                            <input
                                type="checkbox"
                                checked={editForm.online_payments_enabled}
                                onChange={(e) => setEditForm({ ...editForm, online_payments_enabled: e.target.checked })}
                                className="rounded border-slate-300 text-blue-600"
                            />
                            <span>Online Payment Gateway Aktif</span>
                        </label>
                        <label className="flex items-center gap-2 text-xs">
                            <input
                                type="checkbox"
                                checked={editForm.inventory_enabled}
                                onChange={(e) => setEditForm({ ...editForm, inventory_enabled: e.target.checked })}
                                className="rounded border-slate-300 text-blue-600"
                            />
                            <span>Manajemen Inventory & Bahan</span>
                        </label>
                        <label className="flex items-center gap-2 text-xs">
                            <input
                                type="checkbox"
                                checked={editForm.is_active}
                                onChange={(e) => setEditForm({ ...editForm, is_active: e.target.checked })}
                                className="rounded border-slate-300 text-blue-600"
                            />
                            <span className="font-semibold text-slate-900">Paket Ditampilkan Publik / Aktif Dijual</span>
                        </label>
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => setIsEditOpen(false)}>
                            Batal
                        </Button>
                        <Button type="submit" variant="primary" isLoading={isSubmitting}>
                            Simpan Konfigurasi
                        </Button>
                    </div>
                </form>
            </Modal>
        </AdminLayout>
    );
}
