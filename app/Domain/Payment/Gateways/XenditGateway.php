<?php

namespace App\Domain\Payment\Gateways;

use App\Domain\Payment\Contracts\PaymentGatewayInterface;
use App\Domain\Payment\DataTransferObjects\PaymentChargeResult;
use App\Domain\Payment\DataTransferObjects\WebhookVerificationResult;
use App\Domain\Payment\Models\Invoice;
use App\Domain\Payment\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XenditGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected ?string $secretKey = null,
        protected ?string $publicKey = null,
        protected ?string $webhookToken = null,
        protected bool $isProduction = false
    ) {
        $this->secretKey = $secretKey ?: (string) config('services.xendit.secret_key', '');
        $this->publicKey = $publicKey ?: (string) config('services.xendit.public_key', '');
        $this->webhookToken = $webhookToken ?: (string) config('services.xendit.webhook_token', '');
        $this->isProduction = $isProduction || (bool) config('services.xendit.is_production', false);
    }

    public function getProviderName(): string
    {
        return Payment::PROVIDER_XENDIT;
    }

    public function getBaseUrl(): string
    {
        return 'https://api.xendit.co/v2/invoices';
    }

    /**
     * Create Xendit Invoice.
     *
     * @param  array<string, mixed>  $options
     */
    public function createCharge(Invoice $invoice, array $options = []): PaymentChargeResult
    {
        $booking = $invoice->booking;
        $customer = $booking->customer;

        $amountToCharge = $invoice->amount_due_idr;
        if ($amountToCharge <= 0) {
            return PaymentChargeResult::failure(
                $this->getProviderName(),
                'Jumlah pembayaran harus lebih besar dari 0 Rupiah.'
            );
        }

        $secretKey = $this->secretKey;
        if (empty($secretKey)) {
            $mockInvoiceUrl = 'https://checkout-staging.xendit.co/v2/' . $invoice->invoice_number;

            return PaymentChargeResult::success(
                provider: $this->getProviderName(),
                providerTransactionId: 'xendit_inv_' . $invoice->invoice_number,
                checkoutUrl: $mockInvoiceUrl,
                qrString: '00020101021226680016ID.CO.XENDIT0108MOCKQRIS520458125303360540' . $amountToCharge . '5802ID5908MOCKNAME6007JAKARTA6304ABCD',
                rawResponse: ['invoice_url' => $mockInvoiceUrl]
            );
        }

        $payload = [
            'external_id' => $invoice->invoice_number,
            'amount' => (int) $amountToCharge,
            'description' => 'Pembayaran Booking ' . $booking->code,
            'customer' => [
                'given_names' => $customer->name,
                'email' => $customer->email,
                'mobile_number' => $customer->phone_e164,
            ],
            'payment_methods' => ['QRIS', 'BCA', 'BNI', 'BRI', 'MANDIRI', 'OVO', 'DANA', 'SHOPEEPAY'],
        ];

        try {
            $response = Http::withBasicAuth($secretKey, '')
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(15)
                ->post($this->getBaseUrl(), $payload);

            if (! $response->successful()) {
                $err = $response->json('message') ?? $response->body();
                Log::error('Xendit Invoice error: ' . $err, ['status' => $response->status()]);

                return PaymentChargeResult::failure($this->getProviderName(), 'Gagal membuat invoice Xendit: ' . $err);
            }

            $data = $response->json();

            return PaymentChargeResult::success(
                provider: $this->getProviderName(),
                providerTransactionId: (string) ($data['id'] ?? null),
                checkoutUrl: (string) ($data['invoice_url'] ?? ''),
                rawResponse: $data
            );
        } catch (\Throwable $e) {
            Log::error('Xendit Exception: ' . $e->getMessage());

            return PaymentChargeResult::failure($this->getProviderName(), 'Koneksi ke Xendit gagal: ' . $e->getMessage());
        }
    }

    /**
     * Verify Xendit Webhook Token and parse event data.
     */
    public function verifyWebhook(Request $request): WebhookVerificationResult
    {
        $payload = $request->all();

        // 1. Verify token header
        $headerToken = (string) $request->header('x-callback-token', '');
        $configuredToken = $this->webhookToken;

        if (! empty($configuredToken) && ! hash_equals($configuredToken, $headerToken)) {
            return WebhookVerificationResult::invalid(
                $this->getProviderName(),
                'Token verifikasi webhook Xendit tidak valid.',
                $payload
            );
        }

        $orderId = (string) ($payload['external_id'] ?? '');
        $statusStr = strtoupper((string) ($payload['status'] ?? ''));
        $amount = (int) round((float) ($payload['paid_amount'] ?? $payload['amount'] ?? 0));
        $xenditId = (string) ($payload['id'] ?? '');
        $channel = (string) ($payload['payment_channel'] ?? $payload['payment_method'] ?? 'qris');

        if (empty($orderId) || empty($statusStr)) {
            return WebhookVerificationResult::invalid(
                $this->getProviderName(),
                'Payload webhook Xendit tidak lengkap.',
                $payload
            );
        }

        $normalizedStatus = match ($statusStr) {
            'PAID', 'SETTLED' => Payment::STATUS_SETTLEMENT,
            'PENDING' => Payment::STATUS_PENDING,
            'EXPIRED' => Payment::STATUS_EXPIRED,
            default => Payment::STATUS_FAILED,
        };

        $paymentMethod = match (strtoupper($channel)) {
            'QRIS' => Payment::METHOD_QRIS,
            'BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA' => Payment::METHOD_BANK_TRANSFER,
            'OVO', 'DANA', 'LINKAJA', 'SHOPEEPAY' => Payment::METHOD_QRIS,
            'CREDIT_CARD' => Payment::METHOD_CREDIT_CARD,
            default => Payment::METHOD_OTHER,
        };

        $paidAt = null;
        if ($normalizedStatus === Payment::STATUS_SETTLEMENT) {
            $paidAtStr = $payload['paid_at'] ?? $payload['updated'] ?? null;
            $paidAt = $paidAtStr ? Carbon::parse($paidAtStr) : Carbon::now();
        }

        $eventId = $xenditId . '_' . $statusStr;

        return WebhookVerificationResult::valid(
            provider: $this->getProviderName(),
            orderId: $orderId,
            amountIdr: $amount,
            status: $normalizedStatus,
            paymentMethod: $paymentMethod,
            providerEventId: $eventId,
            providerTransactionId: $xenditId,
            paidAt: $paidAt,
            rawPayload: $payload
        );
    }
}
