<?php

namespace App\Domain\Business\Services;

use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Business\Models\CalendarException;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class BusinessCalendarService
{
    /**
     * Initialize default operating hours for a business (7 days).
     */
    public function initializeDefaultHours(Business $business): void
    {
        $defaultSchedule = [
            0 => ['is_open' => false, 'open_time' => '09:00:00', 'close_time' => '17:00:00', 'breaks' => []], // Minggu
            1 => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '17:00:00', 'breaks' => [['name' => 'Istirahat Siang', 'start_time' => '12:00:00', 'end_time' => '13:00:00']]], // Senin
            2 => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '17:00:00', 'breaks' => [['name' => 'Istirahat Siang', 'start_time' => '12:00:00', 'end_time' => '13:00:00']]], // Selasa
            3 => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '17:00:00', 'breaks' => [['name' => 'Istirahat Siang', 'start_time' => '12:00:00', 'end_time' => '13:00:00']]], // Rabu
            4 => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '17:00:00', 'breaks' => [['name' => 'Istirahat Siang', 'start_time' => '12:00:00', 'end_time' => '13:00:00']]], // Kamis
            5 => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '17:00:00', 'breaks' => [['name' => 'Istirahat Siang', 'start_time' => '12:00:00', 'end_time' => '13:00:00']]], // Jumat
            6 => ['is_open' => true, 'open_time' => '09:00:00', 'close_time' => '15:00:00', 'breaks' => []], // Sabtu
        ];

        foreach ($defaultSchedule as $day => $settings) {
            BusinessHour::updateOrCreate(
                [
                    'tenant_id' => $business->tenant_id,
                    'business_id' => $business->id,
                    'day_of_week' => $day,
                ],
                [
                    'is_open' => $settings['is_open'],
                    'open_time' => $settings['open_time'],
                    'close_time' => $settings['close_time'],
                    'breaks' => $settings['breaks'],
                ]
            );
        }
    }

    /**
     * Convert UTC timestamp to Business local time.
     */
    public function toLocal(CarbonInterface $utcDateTime, string $timezone = 'Asia/Jakarta'): Carbon
    {
        return Carbon::parse($utcDateTime)->setTimezone($timezone);
    }

    /**
     * Convert Business local time to UTC timestamp for storage.
     */
    public function toUtc(CarbonInterface $localDateTime, string $timezone = 'Asia/Jakarta'): Carbon
    {
        return Carbon::parse($localDateTime, $timezone)->setTimezone('UTC');
    }

    /**
     * Determine if the business is open at the given UTC timestamp.
     * Evaluates: Operating Hours + Breaks + Holidays/Blackout Dates.
     */
    public function isBusinessOpenAt(Business $business, CarbonInterface $dateTimeUtc): bool
    {
        $timezone = $business->timezone ?: 'Asia/Jakarta';
        $localTime = $this->toLocal($dateTimeUtc, $timezone);
        $dateString = $localTime->format('Y-m-d');
        $timeString = $localTime->format('H:i:s');
        $dayOfWeek = (int) $localTime->dayOfWeek; // 0 = Sunday, 1 = Monday...

        // 1. Check Calendar Exceptions (Holidays, Blackout Dates, Special Hours)
        /** @var CalendarException|null $exception */
        $exception = CalendarException::where('business_id', $business->id)
            ->whereDate('date', $dateString)
            ->first();

        if ($exception) {
            if ($exception->is_closed) {
                return false;
            }

            // Special open hours check
            if ($exception->open_time && $exception->close_time) {
                return $timeString >= $exception->open_time && $timeString < $exception->close_time;
            }
        }

        // 2. Check Standard Operating Hours for the Day of Week
        /** @var BusinessHour|null $businessHour */
        $businessHour = BusinessHour::where('business_id', $business->id)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (! $businessHour || ! $businessHour->is_open) {
            return false;
        }

        // Check if within open and close window
        if ($timeString < $businessHour->open_time || $timeString >= $businessHour->close_time) {
            return false;
        }

        // 3. Check Breaks (e.g. Lunch break)
        if (is_array($businessHour->breaks) && ! empty($businessHour->breaks)) {
            /** @var array{name?: string, start_time?: string, end_time?: string} $break */
            foreach ($businessHour->breaks as $break) {
                $breakStart = $break['start_time'] ?? '';
                $breakEnd = $break['end_time'] ?? '';

                if ($breakStart && $breakEnd) {
                    if ($timeString >= $breakStart && $timeString < $breakEnd) {
                        return false;
                    }
                }
            }
        }

        return true;
    }
}
