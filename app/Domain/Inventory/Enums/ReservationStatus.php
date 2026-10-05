<?php

namespace App\Domain\Inventory\Enums;

enum ReservationStatus: string
{
    case RESERVED = 'RESERVED';
    case CONSUMED = 'CONSUMED';
    case RELEASED = 'RELEASED';
}
