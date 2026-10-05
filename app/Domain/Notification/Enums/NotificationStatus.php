<?php

namespace App\Domain\Notification\Enums;

enum NotificationStatus: string
{
    case PENDING = 'PENDING';
    case SENT = 'SENT';
    case FAILED = 'FAILED';
    case DEAD_LETTER = 'DEAD_LETTER';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Antrean',
            self::SENT => 'Terkirim',
            self::FAILED => 'Gagal (Dalam Antrean Ulang)',
            self::DEAD_LETTER => 'Gagal Permanen (Dead Letter)',
        };
    }
}
