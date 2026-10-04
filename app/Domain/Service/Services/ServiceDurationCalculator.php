<?php

namespace App\Domain\Service\Services;

use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceAddon;
use App\Domain\Service\Models\ServiceVariant;

class ServiceDurationCalculator
{
    /**
     * Calculate effective service duration, price, and buffer breakdown.
     *
     * @param  array{
     *     variant_id?: int|null,
     *     quantity?: int,
     *     addon_ids?: array<int>,
     *     custom_duration_minutes?: int|null
     * }  $options
     * @return array{
     *     service_duration: int,
     *     addon_duration: int,
     *     buffer_before: int,
     *     buffer_after: int,
     *     total_service_duration: int,
     *     total_occupied_duration: int,
     *     base_price: float,
     *     variant_price: float,
     *     addons_price: float,
     *     total_price: float
     * }
     */
    public function calculate(Service $service, array $options = []): array
    {
        $quantity = max(1, (int) ($options['quantity'] ?? 1));
        $variantId = $options['variant_id'] ?? null;
        $addonIds = $options['addon_ids'] ?? [];
        $customMinutes = $options['custom_duration_minutes'] ?? null;

        // 1. Calculate Base Service Duration & Base Price
        $baseDuration = (int) $service->duration_minutes;
        $basePrice = (float) $service->price_idr;

        $selectedVariant = null;
        if ($variantId) {
            $selectedVariant = $service->variants->firstWhere('id', $variantId)
                ?? ServiceVariant::find($variantId);
        }

        if ($selectedVariant) {
            $variantDuration = (int) $selectedVariant->duration_minutes;
            $variantPrice = (float) $selectedVariant->price_idr;
            // Variant overrides base price and duration
            $effectiveDuration = $variantDuration;
            $effectivePrice = $variantPrice;
        } else {
            $variantDuration = 0;
            $variantPrice = 0;

            switch ($service->duration_type) {
                case 'PER_QUANTITY':
                    $minutesPerUnit = isset($service->duration_rule['minutes_per_unit'])
                        ? (int) $service->duration_rule['minutes_per_unit']
                        : $baseDuration;
                    $effectiveDuration = $minutesPerUnit * $quantity;
                    $effectivePrice = $basePrice * $quantity;
                    break;

                case 'VARIABLE':
                    $min = (int) ($service->duration_rule['min_minutes'] ?? $baseDuration);
                    $max = (int) ($service->duration_rule['max_minutes'] ?? $baseDuration);
                    if ($customMinutes !== null) {
                        $effectiveDuration = min(max($customMinutes, $min), $max);
                    } else {
                        $effectiveDuration = $baseDuration;
                    }
                    $effectivePrice = $basePrice;
                    break;

                case 'FIXED':
                default:
                    $effectiveDuration = $baseDuration;
                    $effectivePrice = $basePrice;
                    break;
            }
        }

        // 2. Calculate Addons Duration and Price
        $addonDuration = 0;
        $addonsPrice = 0;

        if (! empty($addonIds)) {
            $addons = ServiceAddon::whereIn('id', $addonIds)
                ->where('service_id', $service->id)
                ->where('is_active', true)
                ->get();

            foreach ($addons as $addon) {
                $addonDuration += (int) $addon->duration_minutes;
                $addonsPrice += (float) $addon->price_idr;
            }
        }

        // 3. Buffer calculation (PRD 124)
        $bufferBefore = (int) $service->buffer_before;
        $bufferAfter = (int) $service->buffer_after;

        $totalServiceDuration = $effectiveDuration + $addonDuration;
        $totalOccupiedDuration = $totalServiceDuration + $bufferBefore + $bufferAfter;
        $totalPrice = $effectivePrice + $addonsPrice;

        return [
            'service_duration' => $effectiveDuration,
            'addon_duration' => $addonDuration,
            'buffer_before' => $bufferBefore,
            'buffer_after' => $bufferAfter,
            'total_service_duration' => $totalServiceDuration,
            'total_occupied_duration' => $totalOccupiedDuration,
            'base_price' => $basePrice,
            'variant_price' => $variantPrice,
            'addons_price' => $addonsPrice,
            'total_price' => $totalPrice,
        ];
    }
}
