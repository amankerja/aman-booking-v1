<?php

namespace App\Console\Commands;

use App\Domain\Booking\Services\BookingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireBookingHoldsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:expire-holds {--limit=100 : Maximum number of bookings to expire}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire pending bookings whose temporary reservation hold has passed (PRD 139, 210 point 4)';

    /**
     * Execute the console command.
     */
    public function handle(BookingService $bookingService): int
    {
        $limit = (int) ($this->option('limit') ?: 100);

        $expiredCount = $bookingService->expireExpiredHolds($limit);

        if ($expiredCount > 0) {
            $this->info("Berhasil melepas {$expiredCount} booking yang masa hold-nya telah kedaluwarsa.");
            Log::info("Command bookings:expire-holds processed {$expiredCount} expired booking holds.");
        } else {
            $this->comment('Tidak ada booking dengan status hold yang kedaluwarsa.');
        }

        return self::SUCCESS;
    }
}
