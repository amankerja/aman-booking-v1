<?php

namespace App\Domain\Payment\DataTransferObjects;

use Carbon\Carbon;

class WebhookVerificationResult
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public bool $isValid,
        public string $provider,
        public ?string $providerEventId = null,
        public ?string $providerTransactionId = null,
        public string $orderId = '',
        public int $amountIdr = 0,
        public string $status = 'PENDING', // SETTLEMENT, PENDING, FAILED, EXPIRED, REFUNDED
        public string $paymentMethod = 'qris',
        public ?Carbon $paidAt = null,
        public array $rawPayload = [],
        public ?string $errorMessage = null,
    ) {}

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public static function valid(
        string $provider,
        string $orderId,
        int $amountIdr,
        string $status,
        string $paymentMethod = 'qris',
        ?string $providerEventId = null,
        ?string $providerTransactionId = null,
        ?Carbon $paidAt = null,
        array $rawPayload = []
    ): self {
        return new self(
            isValid: true,
            provider: $provider,
            providerEventId: $providerEventId,
            providerTransactionId: $providerTransactionId,
            orderId: $orderId,
            amountIdr: $amountIdr,
            status: $status,
            paymentMethod: $paymentMethod,
            paidAt: $paidAt ?? Carbon::now(),
            rawPayload: $rawPayload,
        );
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public static function invalid(
        string $provider,
        string $errorMessage,
        array $rawPayload = []
    ): self {
        return new self(
            isValid: false,
            provider: $provider,
            rawPayload: $rawPayload,
            errorMessage: $errorMessage,
        );
    }
}
