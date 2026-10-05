<?php

namespace App\Domain\Notification\Enums;

enum NotificationEvent: string
{
    case BOOKING_CREATED = 'BOOKING_CREATED';
    case BOOKING_CONFIRMED = 'BOOKING_CONFIRMED';
    case BOOKING_RESCHEDULED = 'BOOKING_RESCHEDULED';
    case BOOKING_CANCELLED = 'BOOKING_CANCELLED';
    case BOOKING_REMINDER_H1 = 'BOOKING_REMINDER_H1';
    case PAYMENT_PENDING = 'PAYMENT_PENDING';
    case PAYMENT_RECEIVED = 'PAYMENT_RECEIVED';
    case BOOKING_COMPLETED = 'BOOKING_COMPLETED';
    case NO_SHOW = 'NO_SHOW';
    case MARKETING_BROADCAST = 'MARKETING_BROADCAST';

    public function label(): string
    {
        return match ($this) {
            self::BOOKING_CREATED => 'Booking Dibuat',
            self::BOOKING_CONFIRMED => 'Booking Dikonfirmasi',
            self::BOOKING_RESCHEDULED => 'Perubahan Jadwal (Reschedule)',
            self::BOOKING_CANCELLED => 'Pembatalan Booking',
            self::BOOKING_REMINDER_H1 => 'Pengingat Jadwal (H-1)',
            self::PAYMENT_PENDING => 'Menunggu Pembayaran',
            self::PAYMENT_RECEIVED => 'Pembayaran Diterima',
            self::BOOKING_COMPLETED => 'Layanan Selesai',
            self::NO_SHOW => 'Tidak Hadir (No Show)',
            self::MARKETING_BROADCAST => 'Pesan Pemasaran / Promosi',
        };
    }
}
