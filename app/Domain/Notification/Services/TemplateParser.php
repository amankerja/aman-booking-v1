<?php

namespace App\Domain\Notification\Services;

use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use Carbon\Carbon;

class TemplateParser
{
    /**
     * Replace template placeholders with real values.
     * Supported syntax: {{variable.name}} or {{ variable.name }}
     *
     * @param  string  $template
     * @param  array<string, string|int|float|null>  $variables
     * @return string
     */
    public function parse(string $template, array $variables): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/', function ($matches) use ($variables) {
            $key = $matches[1];

            return isset($variables[$key]) ? (string) $variables[$key] : $matches[0];
        }, $template);
    }

    /**
     * Extract all variables from a booking instance for template hydration.
     *
     * @param  Booking  $booking
     * @param  string|null  $rawManageToken
     * @return array<string, string>
     */
    public function extractBookingVariables(Booking $booking, ?string $rawManageToken = null): array
    {
        // Ensure relationships are loaded if not already
        if (! $booking->relationLoaded('customer')) {
            $booking->load('customer');
        }
        if (! $booking->relationLoaded('service')) {
            $booking->load('service');
        }
        if (! $booking->relationLoaded('allocations.resource.resourceType')) {
            $booking->load('allocations.resource.resourceType');
        }

        /** @var Business|null $business */
        $business = Business::withoutGlobalScopes()
            ->where('tenant_id', $booking->tenant_id)
            ->first();

        $tz = $booking->business_timezone ?: ($business?->timezone ?: 'Asia/Jakarta');

        $startAt = $booking->start_at->copy()->setTimezone($tz);
        $endAt = $booking->end_at->copy()->setTimezone($tz);

        $tzLabel = match ($tz) {
            'Asia/Jakarta' => 'WIB',
            'Asia/Makassar' => 'WITA',
            'Asia/Jayapura' => 'WIT',
            default => '',
        };

        $timeLabel = trim($startAt->format('H:i') . ' - ' . $endAt->format('H:i') . ' ' . $tzLabel);

        // Staff & Resource from allocations
        $allocatedStaff = $booking->allocations
            ->filter(fn ($alloc) => $alloc->role === 'staff' || ($alloc->resource && $alloc->resource->resourceType && $alloc->resource->resourceType->is_staff))
            ->map(fn ($alloc) => $alloc->resource?->name)
            ->filter()
            ->first();

        $allocatedResource = $booking->allocations
            ->filter(fn ($alloc) => $alloc->role !== 'staff' && (! $alloc->resource || ! $alloc->resource->resourceType || ! $alloc->resource->resourceType->is_staff))
            ->map(fn ($alloc) => $alloc->resource?->name)
            ->filter()
            ->first();

        // Payment status translation
        $paymentStatus = match ($booking->payment_status) {
            'PAID' => 'Lunas',
            'PARTIALLY_PAID' => 'Sebagian Dibayar',
            'REFUNDED' => 'Dikembalikan (Refund)',
            default => 'Belum Dibayar',
        };

        // Manage booking token URL
        $token = $rawManageToken ?: ($booking->raw_manage_token ?: $booking->manage_token);
        $manageUrl = '';
        if ($business && $business->slug && $token) {
            $manageUrl = url("/{$business->slug}/booking/manage/{$token}");
        }

        $businessName = $business?->name ?: 'Aman Booking';
        $businessAddress = $business?->address ?: '-';
        $businessPhone = $business?->whatsapp ?: ($business?->phone ?: '-');

        $totalFormatted = 'Rp ' . number_format($booking->total_idr, 0, ',', '.');
        $servicePriceFormatted = 'Rp ' . number_format($booking->service ? $booking->service->price_idr : $booking->total_idr, 0, ',', '.');

        return [
            'customer.name' => $booking->customer ? $booking->customer->name : 'Pelanggan',
            'customer.phone' => $booking->customer ? $booking->customer->phone_e164 : '',
            'customer.email' => $booking->customer ? ($booking->customer->email ?: '') : '',
            'business.name' => $businessName,
            'business.address' => $businessAddress,
            'business.phone' => $businessPhone,
            'booking.code' => $booking->code,
            'service.name' => $booking->service ? $booking->service->name : 'Layanan',
            'service.price' => $servicePriceFormatted,
            'booking.date' => $startAt->locale('id')->isoFormat('dddd, D MMMM YYYY'),
            'booking.time' => $timeLabel,
            'staff.name' => $allocatedStaff ?: '-',
            'resource.name' => $allocatedResource ?: '-',
            'booking.total' => $totalFormatted,
            'payment.status' => $paymentStatus,
            'manage_booking_url' => $manageUrl,
        ];
    }
}
