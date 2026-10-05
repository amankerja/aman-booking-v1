<?php

namespace App\Http\Controllers\Public;

use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Services\PaymentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Handle incoming webhooks from payment gateways (Midtrans / Xendit).
     *
     * Strict signature verification & Idempotency protection against replay attacks (PRD 204.3, 214, 215.4).
     */
    public function handle(string $provider, Request $request): JsonResponse
    {
        $normalizedProvider = strtolower(trim($provider));

        if (! in_array($normalizedProvider, [Payment::PROVIDER_MIDTRANS, Payment::PROVIDER_XENDIT], true)) {
            return response()->json([
                'status' => 'error',
                'message' => "Provider [{$provider}] tidak didukung.",
            ], 400);
        }

        $result = $this->paymentService->processWebhook($normalizedProvider, $request);

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
            'payment_id' => $result['payment_id'] ?? null,
            'booking_id' => $result['booking_id'] ?? null,
        ], $result['http_code']);
    }
}
