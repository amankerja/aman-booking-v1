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

class MidtransGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected ?string $serverKey = null,
        protected ?string $clientKey = null,
        protected bool $isProduction = false
    ) {
        $this->serverKey = $serverKey ?: (string) config('services.midtrans.server_key', '');
        $this->clientKey = $clientKey ?: (string) config('services.midtrans.client_key', '');
        $this->isProduction = $isProduction || (bool) config('services.midtrans.is_production', false);
    }

    public function getProviderName(): string
    {
        return Payment::PROVIDER_MIDTRANS;
    }

    public function getBaseUrl(): string
    {
        return $this->isProduction
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }

    /**
     * Create Midtrans Snap charge transaction.
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

        $serverKey = $this->serverKey;
        if (empty($serverKey)) {
            // If no server key is configured in dev/testing, return mock success
            $mockToken = 'mock_snap_token_' . $invoice->invoice_number;
            $mockRedirect = 'https://app.sandbox.midtrans.com/snap/v2/vtweb/' . $mockToken;

            return PaymentChargeResult::success(
                provider: $this->getProviderName(),
                providerTransactionId: 'midtrans_trx_' . $invoice->invoice_number,
                snapToken: $mockToken,
                checkoutUrl: $mockRedirect,
                qrString: '00020101021226680016ID.CO.MIDTRANS0108MOCKQRIS520458125303360540' . $amountToCharge . '5802ID5908MOCKNAME6007JAKARTA6304ABCD',
                rawResponse: ['token' => $mockToken, 'redirect_url' => $mockRedirect]
            );
        }

        $payload = [
            'transaction_details' => [
                'order_id' => $invoice->invoice_number,
                'gross_amount' => (int) $amountToCharge,
            ],
            'customer_details' => [
                'first_name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone_e164,
            ],
            'item_details' => [
                [
                    'id' => 'BOOKING-' . $booking->code,
                    'price' => (int) $amountToCharge,
                    'quantity' => 1,
                    'name' => mb_substr('Booking ' . ($booking->service_snapshot['name'] ?? 'Layanan'), 0, 50),
                ],
            ],
            // Enable QRIS as mandatory and other common Indonesian payment methods
            'enabled_payments' => [
                'gopay',
                'shopeepay',
                'qris',
                'bca_va',
                'bni_va',
                'bri_va',
                'mandiri_va',
                'permata_va',
                'other_va',
                'credit_card',
            ],
        ];

        try {
            $response = Http::withBasicAuth($serverKey, '')
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(15)
                ->post($this->getBaseUrl(), $payload);

            if (! $response->successful()) {
                $err = $response->json('error_messages.0') ?? $response->body();
                Log::error('Midtrans Snap creation error: ' . $err, ['status' => $response->status()]);

                return PaymentChargeResult::failure($this->getProviderName(), 'Gagal memproses pembayaran Midtrans: ' . $err);
            }

            $data = $response->json();
            $token = (string) ($data['token'] ?? '');
            $redirectUrl = (string) ($data['redirect_url'] ?? '');

            return PaymentChargeResult::success(
                provider: $this->getProviderName(),
                providerTransactionId: (string) ($data['token'] ?? null),
                snapToken: $token,
                checkoutUrl: $redirectUrl,
                rawResponse: $data
            );
        } catch (\Throwable $e) {
            Log::error('Midtrans Exception: ' . $e->getMessage());

            return PaymentChargeResult::failure($this->getProviderName(), 'Koneksi ke gateway pembayaran gagal: ' . $e->getMessage());
        }
    }

    /**
     * Verify Midtrans notification webhook signature and parse event data.
     * PRD 204.3, 215.4.
     */
    public function verifyWebhook(Request $request): WebhookVerificationResult
    {
        $payload = $request->all();

        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signatureKey = (string) ($payload['signature_key'] ?? '');
        $transactionStatus = (string) ($payload['transaction_status'] ?? '');
        $fraudStatus = (string) ($payload['fraud_status'] ?? '');
        $transactionId = (string) ($payload['transaction_id'] ?? '');
        $paymentType = (string) ($payload['payment_type'] ?? 'qris');

        if (empty($orderId) || empty($statusCode) || empty($grossAmount) || empty($signatureKey)) {
            return WebhookVerificationResult::invalid(
                $this->getProviderName(),
                'Payload webhook Midtrans tidak lengkap.',
                $payload
            );
        }

        // Verify SHA512 signature: sha512(order_id + status_code + gross_amount + ServerKey)
        $serverKey = $this->serverKey;
        $calculatedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        if (! hash_equals($calculatedSignature, $signatureKey)) {
            return WebhookVerificationResult::invalid(
                $this->getProviderName(),
                'Signature webhook Midtrans tidak valid atau telah dimodifikasi.',
                $payload
            );
        }

        // Normalize status
        $normalizedStatus = Payment::STATUS_PENDING;
        if ($transactionStatus === 'capture') {
            $normalizedStatus = ($fraudStatus === 'challenge') ? Payment::STATUS_PENDING : Payment::STATUS_SETTLEMENT;
        } elseif ($transactionStatus === 'settlement') {
            $normalizedStatus = Payment::STATUS_SETTLEMENT;
        } elseif ($transactionStatus === 'pending') {
            $normalizedStatus = Payment::STATUS_PENDING;
        } elseif (in_array($transactionStatus, ['deny', 'cancel', 'expire'], true)) {
            $normalizedStatus = ($transactionStatus === 'expire') ? Payment::STATUS_EXPIRED : Payment::STATUS_FAILED;
        } elseif (in_array($transactionStatus, ['refund', 'partial_refund'], true)) {
            $normalizedStatus = Payment::STATUS_REFUNDED;
        }

        // Parse amount
        $amountInt = (int) round((float) $grossAmount);

        // Normalize payment method
        $paymentMethod = match ($paymentType) {
            'qris', 'gopay', 'shopeepay' => Payment::METHOD_QRIS,
            'bank_transfer', 'echannel' => Payment::METHOD_BANK_TRANSFER,
            'credit_card' => Payment::METHOD_CREDIT_CARD,
            default => Payment::METHOD_OTHER,
        };

        // Unique provider event ID: composite of transaction_id + transaction_status + settlement_time/current time
        $eventId = $transactionId . '_' . $transactionStatus;

        $paidAt = null;
        if ($normalizedStatus === Payment::STATUS_SETTLEMENT) {
            $timeStr = $payload['settlement_time'] ?? $payload['transaction_time'] ?? null;
            $paidAt = $timeStr ? Carbon::parse($timeStr) : Carbon::now();
        }

        return WebhookVerificationResult::valid(
            provider: $this->getProviderName(),
            orderId: $orderId,
            amountIdr: $amountInt,
            status: $normalizedStatus,
            paymentMethod: $paymentMethod,
            providerEventId: $eventId,
            providerTransactionId: $transactionId,
            paidAt: $paidAt,
            rawPayload: $payload
        );
    }
}
