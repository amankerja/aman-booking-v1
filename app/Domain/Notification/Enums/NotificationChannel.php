<?php

namespace App\Domain\Notification\Enums;

enum NotificationChannel: string
{
    case EMAIL = 'EMAIL';
    case WHATSAPP = 'WHATSAPP';
}
