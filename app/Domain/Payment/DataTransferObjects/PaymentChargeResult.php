<?php

namespace App\Domain\Payment\DataTransferObjects;

class PaymentChargeResult
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public bool $success,
        public string $provider,
        public ?string $providerTransactionId = null,
        public ?string $snapToken = null,
        public ?string $checkoutUrl = null,
        public ?string $qrString = null,
        public array $rawResponse = [],
        public ?string $errorMessage = null,
    ) {}

    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public static function success(
        string $provider,
        ?string $providerTransactionId = null,
        ?string $snapToken = null,
        ?string $checkoutUrl = null,
        ?string $qrString = null,
        array $rawResponse = []
    ): self {
        return new self(
            success: true,
            provider: $provider,
            providerTransactionId: $providerTransactionId,
            snapToken: $snapToken,
            checkoutUrl: $checkoutUrl,
            qrString: $qrString,
            rawResponse: $rawResponse,
        );
    }

    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public static function failure(
        string $provider,
        string $errorMessage,
        array $rawResponse = []
    ): self {
        return new self(
            success: false,
            provider: $provider,
            rawResponse: $rawResponse,
            errorMessage: $errorMessage,
        );
    }
}
