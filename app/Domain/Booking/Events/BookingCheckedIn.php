<?php

namespace App\Domain\Booking\Events;

use App\Domain\Booking\Models\Booking;
use App\Domain\Identity\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCheckedIn
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public Booking $booking,
        public ?User $actor = null,
        public string $method = 'MANUAL',
        public ?Carbon $checkedInAt = null,
        public array $metadata = []
    ) {
        $this->checkedInAt = $checkedInAt ?? now();
    }
}
