<?php

namespace App\Domain\Inventory\Enums;

enum StockDeductionMode: string
{
    case RESERVE_ON_BOOKING = 'RESERVE_ON_BOOKING';
    case DEDUCT_ON_SERVICE = 'DEDUCT_ON_SERVICE';
    case DEDUCT_ON_COMPLETE = 'DEDUCT_ON_COMPLETE';

    public function label(): string
    {
        return match ($this) {
            self::RESERVE_ON_BOOKING => 'Reservasi saat Booking (Potong saat Hadir)',
            self::DEDUCT_ON_SERVICE => 'Potong saat Check-in / Layanan',
            self::DEDUCT_ON_COMPLETE => 'Potong saat Booking Selesai',
        };
    }
}
