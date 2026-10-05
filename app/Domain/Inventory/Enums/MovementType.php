<?php

namespace App\Domain\Inventory\Enums;

enum MovementType: string
{
    case OPENING = 'OPENING';
    case PURCHASE = 'PURCHASE';
    case ADJUSTMENT_IN = 'ADJUSTMENT_IN';
    case ADJUSTMENT_OUT = 'ADJUSTMENT_OUT';
    case CONSUMED_BY_BOOKING = 'CONSUMED_BY_BOOKING';
    case SALE = 'SALE';
    case RETURN = 'RETURN';
    case WASTE = 'WASTE';

    public function label(): string
    {
        return match ($this) {
            self::OPENING => 'Stok Awal',
            self::PURCHASE => 'Pembelian Masuk',
            self::ADJUSTMENT_IN => 'Penyesuaian Masuk',
            self::ADJUSTMENT_OUT => 'Penyesuaian Keluar',
            self::CONSUMED_BY_BOOKING => 'Pemakaian Booking',
            self::SALE => 'Penjualan Langsung',
            self::RETURN => 'Retur Masuk',
            self::WASTE => 'Barang Rusak / Kadaluarsa',
        };
    }

    /**
     * Determine if this mutation type increases stock (+1) or decreases (-1).
     */
    public function multiplier(): int
    {
        return match ($this) {
            self::OPENING,
            self::PURCHASE,
            self::ADJUSTMENT_IN,
            self::RETURN => 1,

            self::ADJUSTMENT_OUT,
            self::CONSUMED_BY_BOOKING,
            self::SALE,
            self::WASTE => -1,
        };
    }
}
