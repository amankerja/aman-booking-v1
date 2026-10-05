import { Play } from 'lucide-react';
import React, { useState } from 'react';
import { Button } from '../../../Components/ui/Button';
import { Input } from '../../../Components/ui/Input';
import { Modal } from '../../../Components/ui/Modal';
import { DryRunResult } from './types';

interface TestSimulationModalProps {
    isOpen: boolean;
    onClose: () => void;
    onRunSimulation: (mockPayload: Record<string, unknown>) => Promise<DryRunResult>;
}

export const TestSimulationModal: React.FC<TestSimulationModalProps> = ({
    isOpen,
    onClose,
    onRunSimulation,
}) => {
    const [mockStatus, setMockStatus] = useState('PENDING');
    const [mockPaymentStatus, setMockPaymentStatus] = useState('PAID');
    const [mockTotalIdr, setMockTotalIdr] = useState('150000');
    const [isRunning, setIsRunning] = useState(false);
    const [result, setResult] = useState<DryRunResult | null>(null);

    const handleApplyPreset = (preset: 'paid' | 'unpaid' | 'vip') => {
        if (preset === 'paid') {
            setMockStatus('PENDING');
            setMockPaymentStatus('PAID');
            setMockTotalIdr('150000');
        } else if (preset === 'unpaid') {
            setMockStatus('PENDING');
            setMockPaymentStatus('UNPAID');
            setMockTotalIdr('75000');
        } else if (preset === 'vip') {
            setMockStatus('PENDING');
            setMockPaymentStatus('PAID');
            setMockTotalIdr('650000');
        }
        setResult(null);
    };

    const handleStartTest = async () => {
        setIsRunning(true);
        try {
            const data = await onRunSimulation({
                status: mockStatus,
                payment_status: mockPaymentStatus,
                total_idr: Number(mockTotalIdr),
            });
            setResult(data);
        } finally {
            setIsRunning(false);
        }
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title={
                <div className="flex items-center gap-2 text-sm font-semibold text-slate-900">
                    <Play className="h-4 w-4 text-blue-600" />
                    <span>Uji Alur Kerja (Test Dry-Run)</span>
                </div>
            }
            description="Simulasikan eksekusi graf alur kerja dengan data booking tiruan tanpa memengaruhi data produksi."
            size="lg"
        >
            <div className="space-y-4 text-xs">
                {/* 1. Quick Presets */}
                <div className="space-y-1.5">
                    <label className="block text-xs font-semibold text-slate-700">
                        Pilih Skenario Cepat:
                    </label>
                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            onClick={() => handleApplyPreset('paid')}
                            className="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-700 hover:border-blue-600 hover:bg-blue-50"
                        >
                            ✓ Lunas Langsung (PAID)
                        </button>
                        <button
                            type="button"
                            onClick={() => handleApplyPreset('unpaid')}
                            className="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-700 hover:border-amber-600 hover:bg-amber-50"
                        >
                            ⏱ Belum Bayar (UNPAID)
                        </button>
                        <button
                            type="button"
                            onClick={() => handleApplyPreset('vip')}
                            className="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-700 hover:border-purple-600 hover:bg-purple-50"
                        >
                            ⭐ Transaksi Besar (&gt; Rp 500rb)
                        </button>
                    </div>
                </div>

                {/* 2. Mock Data Inputs */}
                <div className="grid grid-cols-1 gap-3 rounded-[10px] border border-slate-200 bg-slate-50 p-3 sm:grid-cols-3">
                    <div>
                        <label className="block text-[11px] font-medium text-slate-600 mb-1">
                            Status Awal
                        </label>
                        <select
                            value={mockStatus}
                            onChange={(e) => setMockStatus(e.target.value)}
                            className="w-full rounded-[6px] border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-900 focus:border-blue-600 focus:outline-none"
                        >
                            <option value="PENDING">PENDING</option>
                            <option value="CONFIRMED">CONFIRMED</option>
                            <option value="CHECKED_IN">CHECKED_IN</option>
                        </select>
                    </div>

                    <div>
                        <label className="block text-[11px] font-medium text-slate-600 mb-1">
                            Status Pembayaran
                        </label>
                        <select
                            value={mockPaymentStatus}
                            onChange={(e) => setMockPaymentStatus(e.target.value)}
                            className="w-full rounded-[6px] border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-900 focus:border-blue-600 focus:outline-none"
                        >
                            <option value="PAID">PAID (Lunas)</option>
                            <option value="PARTIAL">PARTIAL (DP)</option>
                            <option value="UNPAID">UNPAID (Belum Bayar)</option>
                        </select>
                    </div>

                    <div>
                        <label className="block text-[11px] font-medium text-slate-600 mb-1">
                            Total Transaksi (Rp)
                        </label>
                        <Input
                            type="number"
                            value={mockTotalIdr}
                            onChange={(e) => setMockTotalIdr(e.target.value)}
                            className="text-xs"
                        />
                    </div>
                </div>

                {/* Run button */}
                <div className="flex justify-end">
                    <Button
                        type="button"
                        variant="primary"
                        onClick={handleStartTest}
                        isLoading={isRunning}
                        className="gap-1.5"
                    >
                        <Play className="h-3.5 w-3.5" /> Jalankan Simulasi
                    </Button>
                </div>

                {/* Results View */}
                {result && (
                    <div className="space-y-3 rounded-[10px] border border-slate-200 bg-white p-3.5">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                            <span className="font-semibold text-slate-900">
                                Hasil Simulasi Eksekusi:
                            </span>
                            <span
                                className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${
                                    result.success
                                        ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                        : 'bg-rose-50 text-rose-700 border border-rose-200'
                                }`}
                            >
                                {result.success ? 'BERHASIL (SIMULATED)' : 'GAGAL'}
                            </span>
                        </div>

                        {/* Step-by-step trace */}
                        <div className="space-y-2">
                            <span className="text-[11px] font-medium text-slate-500 uppercase tracking-wider">
                                Urutan Langkah Alur ({result.steps.length} langkah):
                            </span>
                            <div className="space-y-1.5 max-h-56 overflow-y-auto pr-1">
                                {result.steps.map((step, idx) => (
                                    <div
                                        key={idx}
                                        className="flex items-start gap-2.5 rounded-[8px] border border-slate-100 bg-slate-50 p-2 text-xs"
                                    >
                                        <div className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white border border-slate-200 text-[10px] font-bold text-slate-700">
                                            {idx + 1}
                                        </div>
                                        <div className="flex-1 overflow-hidden">
                                            <div className="flex items-center justify-between">
                                                <span className="font-bold text-slate-900">
                                                    {step.title}
                                                </span>
                                                <span className="text-[10px] uppercase font-semibold text-slate-400">
                                                    {step.type}
                                                </span>
                                            </div>
                                            <p className="mt-0.5 text-[11px] text-slate-600">
                                                {step.detail}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Summary */}
                        {result.success && (
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 border-t border-slate-100 pt-2.5 text-[11px]">
                                <div className="rounded-[6px] bg-slate-50 p-2">
                                    <span className="text-slate-500 block">Status Akhir:</span>
                                    <strong className="text-slate-900 text-xs">
                                        {result.final_status}
                                    </strong>
                                </div>
                                <div className="rounded-[6px] bg-slate-50 p-2">
                                    <span className="text-slate-500 block">
                                        Notifikasi Terpicu:
                                    </span>
                                    <strong className="text-slate-900 text-xs">
                                        {result.notifications.length} Pesan
                                    </strong>
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </Modal>
    );
};
