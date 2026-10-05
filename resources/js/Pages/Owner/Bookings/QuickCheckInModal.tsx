import {
    AlertCircle,
    Check,
    CheckCircle2,
    Clock,
    QrCode,
    Search,
    ShieldAlert,
    User,
    X,
} from 'lucide-react';
import React, { useEffect, useRef, useState } from 'react';
import { Button, Modal, useToast } from '../../../Components/ui';
import { BookingItem } from './types';

interface QuickCheckInModalProps {
    isOpen: boolean;
    onClose: () => void;
    onCheckInSuccess: (updatedBooking: BookingItem) => void;
}

interface LookupPreview {
    booking: BookingItem;
    window_status: {
        is_open: boolean;
        status: string;
        earliest_check_in_at: string;
        latest_check_in_at: string;
        message: string;
    };
    can_check_in: boolean;
}

export const QuickCheckInModal: React.FC<QuickCheckInModalProps> = ({
    isOpen,
    onClose,
    onCheckInSuccess,
}) => {
    const toast = useToast();
    const inputRef = useRef<HTMLInputElement>(null);

    const [code, setCode] = useState('');
    const [isLoadingLookup, setIsLoadingLookup] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [lookupResult, setLookupResult] = useState<LookupPreview | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [deskOverride, setDeskOverride] = useState(false);
    const [notes, setNotes] = useState('');

    // Auto-focus input on modal open
    useEffect(() => {
        if (isOpen) {
            setCode('');
            setLookupResult(null);
            setErrorMessage(null);
            setDeskOverride(false);
            setNotes('');
            setTimeout(() => {
                inputRef.current?.focus();
            }, 100);
        }
    }, [isOpen]);

    const handleLookup = async (inputCode: string) => {
        const query = inputCode.trim();
        if (!query) {
            setLookupResult(null);
            setErrorMessage(null);
            return;
        }

        setIsLoadingLookup(true);
        setErrorMessage(null);

        try {
            const res = await fetch(
                `/app/bookings/check-in/lookup?code=${encodeURIComponent(query)}`,
                {
                    headers: {
                        Accept: 'application/json',
                    },
                }
            );

            const data = await res.json();
            if (res.ok) {
                setLookupResult(data);
                // If window closed, auto-suggest override
                if (!data.window_status?.is_open) {
                    setDeskOverride(true);
                } else {
                    setDeskOverride(false);
                }
            } else {
                setLookupResult(null);
                setErrorMessage(data.message || 'Booking tidak ditemukan.');
            }
        } catch {
            setLookupResult(null);
            setErrorMessage('Gagal mencari booking. Cek koneksi.');
        } finally {
            setIsLoadingLookup(false);
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (lookupResult && (lookupResult.window_status.is_open || deskOverride)) {
                handleSubmitCheckIn();
            } else {
                handleLookup(code);
            }
        }
    };

    const handleSubmitCheckIn = async () => {
        const targetCode = lookupResult ? lookupResult.booking.code : code.trim();
        if (!targetCode) {
            toast.error('Masukkan kode booking terlebih dahulu.');
            return;
        }

        setIsSubmitting(true);
        try {
            const csrfToken =
                (
                    document.querySelector(
                        'meta[name="csrf-token"]'
                    ) as HTMLMetaElement
                )?.content || '';

            const res = await fetch('/app/bookings/check-in', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    code: targetCode,
                    desk_override: deskOverride,
                    notes: notes.trim() || null,
                    method: 'CODE',
                }),
            });

            const data = await res.json();
            if (res.ok) {
                toast.success(data.message || 'Check-in berhasil!');
                if (data.booking) {
                    onCheckInSuccess(data.booking);
                }
                onClose();
            } else {
                toast.error(data.message || 'Gagal melakukan check-in.');
                setErrorMessage(data.message || 'Gagal melakukan check-in.');
            }
        } catch {
            toast.error('Gagal memproses check-in. Coba lagi.');
        } finally {
            setIsSubmitting(false);
        }
    };

    const formatDateTime = (isoString?: string) => {
        if (!isoString) return '-';
        const d = new Date(isoString);
        return d.toLocaleDateString('id-ID', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title="Check-in Cepat (QR / Kode Booking)"
            description="Pindai QR code pelanggan atau masukkan kode booking untuk check-in meja depan instan."
            size="lg"
        >
            <div className="space-y-4 pt-1">
                {/* Search Bar / Barcode Input */}
                <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-slate-700">
                        Kode Booking / Scan QR Token / URL
                    </label>
                    <div className="relative flex items-center">
                        <div className="pointer-events-none absolute left-3 text-slate-400">
                            <QrCode className="h-4 w-4" />
                        </div>
                        <input
                            ref={inputRef}
                            type="text"
                            value={code}
                            onChange={(e) => {
                                setCode(e.target.value);
                                setErrorMessage(null);
                            }}
                            onKeyDown={handleKeyDown}
                            placeholder="Contoh: BK-20261010-00001 atau scan barcode..."
                            className="w-full rounded-[8px] border border-slate-300 bg-white py-2.5 pr-20 pl-9 font-mono text-sm text-slate-900 placeholder-slate-400 transition-colors focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600"
                        />
                        <button
                            type="button"
                            onClick={() => handleLookup(code)}
                            disabled={isLoadingLookup || !code.trim()}
                            className="absolute right-1.5 inline-flex items-center gap-1 rounded-[6px] bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 transition hover:bg-slate-200 disabled:opacity-50"
                        >
                            {isLoadingLookup ? (
                                <span className="h-3 w-3 animate-spin rounded-full border border-slate-600 border-t-transparent" />
                            ) : (
                                <Search className="h-3.5 w-3.5" />
                            )}
                            Cari
                        </button>
                    </div>
                    <p className="text-[11px] text-slate-500">
                        Tekan <kbd className="rounded bg-slate-100 px-1 py-0.5 font-mono text-[10px] text-slate-700">Enter</kbd> untuk mencari atau langsung konfirmasi check-in.
                    </p>
                </div>

                {/* Error Banner */}
                {errorMessage && (
                    <div className="flex items-start gap-2.5 rounded-[8px] border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800">
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0 text-rose-600" />
                        <div className="flex-1">
                            <p className="font-semibold">Perhatian</p>
                            <p className="mt-0.5 leading-relaxed">{errorMessage}</p>
                        </div>
                    </div>
                )}

                {/* Lookup Card Preview */}
                {lookupResult && (
                    <div className="space-y-3 rounded-[10px] border border-slate-200 bg-slate-50/70 p-3.5 text-xs">
                        <div className="flex items-start justify-between border-b border-slate-200/80 pb-2.5">
                            <div>
                                <div className="flex items-center gap-2">
                                    <span className="font-mono text-sm font-bold text-slate-900">
                                        {lookupResult.booking.code}
                                    </span>
                                    <span
                                        className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold ${
                                            lookupResult.booking.status_category === 'CHECKED_IN'
                                                ? 'bg-emerald-100 text-emerald-800'
                                                : lookupResult.booking.status_category === 'CONFIRMED'
                                                  ? 'bg-blue-100 text-blue-800'
                                                  : 'bg-slate-200 text-slate-700'
                                        }`}
                                    >
                                        {lookupResult.booking.status?.name || lookupResult.booking.status_category}
                                    </span>
                                </div>
                                <p className="mt-0.5 text-slate-500">
                                    Dibuat: {formatDateTime(lookupResult.booking.created_at)}
                                </p>
                            </div>

                            {/* Window Status Pill */}
                            <div className="text-right">
                                {lookupResult.window_status.is_open ? (
                                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 border border-emerald-200">
                                        <Clock className="h-3 w-3" />
                                        Jendela Buka
                                    </span>
                                ) : (
                                    <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700 border border-amber-200">
                                        <AlertCircle className="h-3 w-3" />
                                        Di Luar Jendela
                                    </span>
                                )}
                            </div>
                        </div>

                        {/* Customer & Service Info Grid */}
                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <div className="rounded-[8px] bg-white p-2.5 border border-slate-100">
                                <span className="text-[10px] font-medium tracking-wide uppercase text-slate-400">
                                    Pelanggan
                                </span>
                                <p className="mt-0.5 font-semibold text-slate-900">
                                    {lookupResult.booking.customer?.name || 'Customer'}
                                </p>
                                <p className="text-[11px] text-slate-600 font-mono">
                                    {lookupResult.booking.customer?.phone_e164 || '-'}
                                </p>
                            </div>

                            <div className="rounded-[8px] bg-white p-2.5 border border-slate-100">
                                <span className="text-[10px] font-medium tracking-wide uppercase text-slate-400">
                                    Layanan & Jadwal
                                </span>
                                <p className="mt-0.5 font-semibold text-slate-900">
                                    {lookupResult.booking.service?.name ||
                                        lookupResult.booking.service_snapshot?.name ||
                                        'Layanan'}
                                </p>
                                <p className="text-[11px] text-blue-600 font-medium">
                                    {formatDateTime(lookupResult.booking.start_at)}
                                </p>
                            </div>
                        </div>

                        {/* Window Message Explanation */}
                        {lookupResult.window_status?.message && (
                            <p className="text-[11px] text-slate-600 italic">
                                * {lookupResult.window_status.message}
                            </p>
                        )}

                        {/* Desk Override Checkbox */}
                        {!lookupResult.window_status.is_open && (
                            <div className="mt-2 rounded-[8px] border border-amber-200 bg-amber-50/80 p-2.5">
                                <label className="flex items-start gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={deskOverride}
                                        onChange={(e) => setDeskOverride(e.target.checked)}
                                        className="mt-0.5 h-4 w-4 rounded border-amber-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <div className="text-xs text-amber-900">
                                        <span className="font-semibold">
                                            Otorisasi Override Meja Depan (Desk Override)
                                        </span>
                                        <p className="text-[11px] text-amber-800 mt-0.5">
                                            Centang untuk tetap memproses check-in customer di luar rentang jendela waktu resmi.
                                        </p>
                                    </div>
                                </label>
                            </div>
                        )}

                        {/* Optional Staff Notes */}
                        <div className="space-y-1 pt-1">
                            <label className="text-[11px] font-medium text-slate-600">
                                Catatan Check-in (Opsional)
                            </label>
                            <input
                                type="text"
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                placeholder="Contoh: Datang bersama 1 teman, kursi no. 2"
                                className="w-full rounded-[6px] border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 placeholder-slate-400 focus:border-blue-600 focus:outline-none"
                            />
                        </div>
                    </div>
                )}

                {/* Action Buttons */}
                <div className="flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
                    <Button variant="ghost" size="sm" onClick={onClose} disabled={isSubmitting}>
                        Batal
                    </Button>
                    <Button
                        variant="primary"
                        size="sm"
                        onClick={handleSubmitCheckIn}
                        disabled={
                            isSubmitting ||
                            !code.trim() ||
                            (lookupResult !== null &&
                                !lookupResult.window_status.is_open &&
                                !deskOverride)
                        }
                        className="gap-1.5"
                    >
                        {isSubmitting ? (
                            <span className="h-3.5 w-3.5 animate-spin rounded-full border border-white border-t-transparent" />
                        ) : (
                            <Check className="h-3.5 w-3.5" />
                        )}
                        <span>Konfirmasi Check-in</span>
                    </Button>
                </div>
            </div>
        </Modal>
    );
};
