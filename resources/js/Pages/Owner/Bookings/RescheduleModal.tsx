import { Calendar, Clock, History, Loader2 } from 'lucide-react';
import React, { useEffect, useState } from 'react';
import { Button, Modal, useToast } from '../../../Components/ui';
import { BookingItem, SlotItem } from './types';

interface RescheduleModalProps {
    isOpen: boolean;
    onClose: () => void;
    booking: BookingItem | null;
    onRescheduled: (updatedBooking: BookingItem) => void;
}

export const RescheduleModal: React.FC<RescheduleModalProps> = ({
    isOpen,
    onClose,
    booking,
    onRescheduled,
}) => {
    const toast = useToast();
    const [selectedDate, setSelectedDate] = useState<string>(
        booking
            ? new Date(booking.start_at).toISOString().split('T')[0]
            : new Date().toISOString().split('T')[0]
    );
    const [selectedSlot, setSelectedSlot] = useState<SlotItem | null>(null);
    const [slots, setSlots] = useState<SlotItem[]>([]);
    const [isLoadingSlots, setIsLoadingSlots] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    useEffect(() => {
        if (booking) {
            setSelectedDate(
                new Date(booking.start_at).toISOString().split('T')[0]
            );
            setSelectedSlot(null);
        }
    }, [booking]);

    useEffect(() => {
        if (!isOpen || !booking || !selectedDate) {
            setSlots([]);
            return;
        }

        let isCancelled = false;
        const fetchSlots = async () => {
            setIsLoadingSlots(true);
            try {
                const params = new URLSearchParams({
                    service_id: String(booking.service_id),
                    date: selectedDate,
                });

                const res = await fetch(
                    `/app/bookings/slots?${params.toString()}`,
                    {
                        headers: { Accept: 'application/json' },
                    }
                );
                if (res.ok && !isCancelled) {
                    const data = await res.json();
                    setSlots(data.slots || []);
                    setSelectedSlot(null);
                }
            } catch {
                if (!isCancelled) {
                    toast.error('Gagal memuat ketersediaan slot.');
                }
            } finally {
                if (!isCancelled) {
                    setIsLoadingSlots(false);
                }
            }
        };

        fetchSlots();

        return () => {
            isCancelled = true;
        };
    }, [isOpen, booking, selectedDate, toast]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!booking || !selectedSlot) {
            toast.error('Pilih jam slot baru.');
            return;
        }

        setIsSubmitting(true);
        try {
            const res = await fetch(`/app/bookings/${booking.id}/reschedule`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]'
                            ) as HTMLMetaElement
                        )?.content || '',
                },
                body: JSON.stringify({
                    new_start_at: selectedSlot.start_at,
                }),
            });

            const data = await res.json();
            if (res.ok) {
                toast.success(
                    data.message || 'Jadwal booking berhasil dipindahkan!'
                );
                onRescheduled(data.booking);
                onClose();
            } else {
                toast.error(data.message || 'Gagal memindahkan jadwal.');
            }
        } catch {
            toast.error('Terjadi kesalahan jaringan.');
        } finally {
            setIsSubmitting(false);
        }
    };

    if (!booking) return null;

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title={
                <div className="flex items-center gap-2 text-sm font-semibold text-slate-900">
                    <History className="h-4 w-4 text-blue-600" />
                    <span>Pindahkan Jadwal (Reschedule) — {booking.code}</span>
                </div>
            }
            description="Pilih tanggal dan slot waktu baru. Sistem akan memeriksa bentrok resource dan batas kebijakan secara otomatis."
            size="md"
        >
            <form onSubmit={handleSubmit} className="space-y-4 text-xs">
                {/* Current Schedule Banner */}
                <div className="rounded-[8px] border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700">
                    <span className="mb-1 block font-semibold text-slate-900">
                        Jadwal Saat Ini:
                    </span>
                    <div className="flex items-center gap-3">
                        <span className="flex items-center gap-1">
                            <Calendar className="h-3.5 w-3.5 text-slate-400" />
                            {new Date(booking.start_at).toLocaleDateString(
                                'id-ID',
                                {
                                    weekday: 'long',
                                    day: 'numeric',
                                    month: 'short',
                                    year: 'numeric',
                                }
                            )}
                        </span>
                        <span className="flex items-center gap-1 font-mono font-medium">
                            <Clock className="h-3.5 w-3.5 text-slate-400" />
                            {new Date(booking.start_at).toLocaleTimeString(
                                'id-ID',
                                {
                                    hour: '2-digit',
                                    minute: '2-digit',
                                }
                            )}{' '}
                            -{' '}
                            {new Date(booking.end_at).toLocaleTimeString(
                                'id-ID',
                                {
                                    hour: '2-digit',
                                    minute: '2-digit',
                                }
                            )}
                        </span>
                    </div>
                </div>

                {/* Date Picker */}
                <div>
                    <label className="mb-1 block text-xs font-medium text-slate-700">
                        Pilih Tanggal Baru
                    </label>
                    <input
                        type="date"
                        value={selectedDate}
                        onChange={(e) => setSelectedDate(e.target.value)}
                        required
                        className="w-full rounded-[8px] border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-900 focus:border-blue-600 focus:outline-none"
                    />
                </div>

                {/* Slots Grid */}
                <div className="rounded-[10px] border border-slate-200 bg-slate-50 p-3">
                    <div className="mb-2 flex items-center justify-between">
                        <span className="flex items-center gap-1 text-[11px] font-semibold tracking-wider text-slate-700 uppercase">
                            <Clock className="h-3 w-3 text-slate-400" />
                            Pilih Jam Slot Baru
                        </span>
                        {isLoadingSlots && (
                            <span className="flex items-center gap-1 text-[11px] text-blue-600">
                                <Loader2 className="h-3 w-3 animate-spin" />
                                Cek slot...
                            </span>
                        )}
                    </div>

                    {isLoadingSlots ? (
                        <div className="py-6 text-center text-xs text-slate-400">
                            Memeriksa ketersediaan...
                        </div>
                    ) : slots.length === 0 ? (
                        <div className="py-6 text-center text-xs text-slate-400">
                            Tidak ada slot tersedia untuk tanggal ini.
                        </div>
                    ) : (
                        <div className="grid max-h-48 grid-cols-3 gap-1.5 overflow-y-auto pr-1 sm:grid-cols-4">
                            {slots.map((slot, idx) => {
                                const isSelected =
                                    selectedSlot?.start_at === slot.start_at;
                                return (
                                    <button
                                        key={idx}
                                        type="button"
                                        disabled={!slot.is_available}
                                        onClick={() => setSelectedSlot(slot)}
                                        title={slot.reason_message || undefined}
                                        className={`rounded-[6px] px-2 py-1.5 text-center font-mono text-xs transition-all ${
                                            isSelected
                                                ? 'bg-blue-600 font-semibold text-white ring-2 ring-blue-600 ring-offset-1'
                                                : slot.is_available
                                                  ? 'border border-slate-200 bg-white text-slate-800 hover:border-blue-600 hover:bg-blue-50'
                                                  : 'cursor-not-allowed border border-transparent bg-slate-100 text-slate-400 line-through'
                                        }`}
                                    >
                                        {slot.start_time}
                                    </button>
                                );
                            })}
                        </div>
                    )}
                </div>

                <div className="flex justify-end gap-2 border-t border-slate-200 pt-2">
                    <Button type="button" variant="outline" onClick={onClose}>
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        variant="primary"
                        disabled={!selectedSlot}
                        isLoading={isSubmitting}
                    >
                        Simpan Jadwal Baru
                    </Button>
                </div>
            </form>
        </Modal>
    );
};
