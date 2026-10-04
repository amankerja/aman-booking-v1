<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\BookingCounter;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class BookingCodeGenerator
{
    /**
     * Generate atomic, sequential booking code: BK-YYYYMMDD-NNNNN (PRD 214).
     * Uses row-level locking on booking_counters table.
     */
    public function generate(int $tenantId, ?CarbonInterface $date = null): string
    {
        $dateObj = $date ? Carbon::parse($date) : Carbon::today();
        $dateStr = $dateObj->format('Y-m-d');
        $dateCompact = $dateObj->format('Ymd');

        /** @var BookingCounter $counter */
        $counter = DB::transaction(function () use ($tenantId, $dateStr) {
            /** @var BookingCounter|null $record */
            $record = BookingCounter::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('date', $dateStr)
                ->lockForUpdate()
                ->first();

            if (! $record) {
                try {
                    $record = BookingCounter::withoutGlobalScopes()->create([
                        'tenant_id' => $tenantId,
                        'date' => $dateStr,
                        'last_number' => 1,
                    ]);
                } catch (QueryException $e) {
                    /** @var BookingCounter|null $record */
                    $record = BookingCounter::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where('date', $dateStr)
                        ->lockForUpdate()
                        ->first();

                    if (! $record) {
                        throw $e;
                    }

                    $record->increment('last_number');
                    $record->refresh();
                }
            } else {
                $record->increment('last_number');
                $record->refresh();
            }

            return $record;
        });

        $sequence = str_pad((string) $counter->last_number, 5, '0', STR_PAD_LEFT);

        return "BK-{$dateCompact}-{$sequence}";
    }
}
