<?php

namespace App\Domain\Payment\Gateways;

use App\Domain\Payment\Contracts\PaymentGatewayInterface;
use App\Domain\Payment\DataTransferObjects\PaymentChargeResult;
use App\Domain\Payment\DataTransferObjects\WebhookVerificationResult;
use App\Domain\Payment\Models\Invoice;
use App\Domain\Payment\Models\Payment;
use Illuminate\Http\Request;

class ManualGateway implements PaymentGatewayInterface
{
    public function getProviderName(): string
    {
        return Payment::PROVIDER_MANUAL;
    }

    public function createCharge(Invoice $invoice, array $options = []): PaymentChargeResult
    {
        return PaymentChargeResult::success(
            provider: $this->getProviderName(),
            providerTransactionId: 'MANUAL-' . $invoice->invoice_number,
            rawResponse: ['notes' => 'Pembayaran manual di kasir / transfer langsung']
        );
    }

    public function verifyWebhook(Request $request): WebhookVerificationResult
    {
        return WebhookVerificationResult::invalid(
            $this->getProviderName(),
            'Manual gateway tidak mendukung webhook masuk.'
        );
    }
}
