<?php

namespace App\Domain\Payment\Contracts;

use App\Domain\Payment\DataTransferObjects\PaymentChargeResult;
use App\Domain\Payment\DataTransferObjects\WebhookVerificationResult;
use App\Domain\Payment\Models\Invoice;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /**
     * Create an online transaction / charge / invoice on the payment gateway.
     *
     * @param  array<string, mixed>  $options
     */
    public function createCharge(Invoice $invoice, array $options = []): PaymentChargeResult;

    /**
     * Verify the webhook authenticity and extract normalized transaction payload.
     */
    public function verifyWebhook(Request $request): WebhookVerificationResult;

    /**
     * Get unique provider identifier code.
     */
    public function getProviderName(): string;
}
