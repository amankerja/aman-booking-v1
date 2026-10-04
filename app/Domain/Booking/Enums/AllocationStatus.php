<?php

namespace App\Domain\Booking\Enums;

enum AllocationStatus: string
{
    case ACTIVE = 'ACTIVE';
    case RELEASED = 'RELEASED';
    case CONSUMED = 'CONSUMED';
}
