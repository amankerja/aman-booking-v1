<?php

namespace App\Domain\Payment\Services;

use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Services\BookingStateMachine;
use App\Domain\Business\Models\Business;
use App\Domain\Identity\Models\User;
use App\Domain\Payment\DataTransferObjects\PaymentChargeResult;
use App\Domain\Payment\DataTransferObjects\WebhookVerificationResult;
use App\Domain\Payment\Models\Invoice;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\PaymentRefund;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager,
        protected BookingStateMachine $stateMachine
    ) {}

    /**
     * Create an Invoice for a Booking based on payment model (PRD 45, 214).
     */
    public function createInvoiceForBooking(
        Booking $booking,
        string $paymentModel = Invoice::MODEL_FULL_PAYMENT,
        ?int $depositAmount = null,
        ?string $notes = null
    ): Invoice {
        $totalAmount = (int) $booking->total_idr;

        // Determine amount due based on model
        $amountDue = match ($paymentModel) {
            Invoice::MODEL_NO_PAYMENT => 0,
            Invoice::MODEL_DEPOSIT => (int) ($depositAmount ?? $booking->deposit_idr ?: round($totalAmount * 0.3)),
            Invoice::MODEL_PARTIAL_PAYMENT => (int) ($depositAmount ?? round($totalAmount * 0.5)),
            default => $totalAmount, // full_payment
        };

        // Ensure amount due does not exceed total
        $amountDue = min($amountDue, $totalAmount);

        // Generate invoice number: INV-YYYYMMDD-XXXX
        $datePrefix = Carbon::now()->format('Ymd');
        $randomSuffix = strtoupper(Str::random(5));
        $invoiceNumber = "INV-{$datePrefix}-{$booking->id}-{$randomSuffix}";

        $status = ($amountDue <= 0) ? Invoice::STATUS_PAID : Invoice::STATUS_UNPAID;

        $businessId = $booking->getAttribute('business_id')
            ?: Business::where('tenant_id', $booking->tenant_id)->value('id');

        return Invoice::create([
            'tenant_id' => $booking->tenant_id,
            'business_id' => $businessId,
            'booking_id' => $booking->id,
            'invoice_number' => $invoiceNumber,
            'payment_model' => $paymentModel,
            'amount_total_idr' => $totalAmount,
            'amount_due_idr' => $amountDue,
            'amount_paid_idr' => 0,
            'status' => $status,
            'due_at' => $booking->hold_expires_at ?? Carbon::now()->addHours(24),
            'notes' => $notes,
            'metadata' => [
                'booking_code' => $booking->code,
                'service_name' => $booking->service_snapshot['name'] ?? null,
            ],
        ]);
    }

    /**
     * Generate an online payment charge via the selected gateway (Midtrans / Xendit).
     *
     * @param  array<string, mixed>  $options
     */
    public function createChargeForInvoice(
        Invoice $invoice,
        string $provider = Payment::PROVIDER_MIDTRANS,
        array $options = []
    ): PaymentChargeResult {
        /** @var Business $business */
        $business = $invoice->business;
        $gateway = $this->gatewayManager->resolve($provider, $business);

        $chargeResult = $gateway->createCharge($invoice, $options);

        if (! $chargeResult->success) {
            return $chargeResult;
        }

        // Generate payment number: PAY-YYYYMMDD-XXXX
        $datePrefix = Carbon::now()->format('Ymd');
        $randomSuffix = strtoupper(Str::random(4));
        $paymentNumber = "PAY-{$datePrefix}-{$invoice->id}-{$randomSuffix}";

        Payment::create([
            'tenant_id' => $invoice->tenant_id,
            'business_id' => $invoice->business_id,
            'booking_id' => $invoice->booking_id,
            'invoice_id' => $invoice->id,
            'payment_number' => $paymentNumber,
            'provider' => $provider,
            'provider_transaction_id' => $chargeResult->providerTransactionId,
            'payment_method' => Payment::METHOD_QRIS,
            'amount_idr' => $invoice->amount_due_idr,
            'status' => Payment::STATUS_PENDING,
            'snap_token' => $chargeResult->snapToken,
            'checkout_url' => $chargeResult->checkoutUrl,
            'qr_string' => $chargeResult->qrString,
            'provider_payload' => $options,
            'provider_response' => $chargeResult->rawResponse,
        ]);

        $invoice->update(['status' => Invoice::STATUS_PENDING]);

        return $chargeResult;
    }

    /**
     * Process incoming webhook from Midtrans or Xendit with strict signature verification
     * and idempotency protection against duplicate/replayed payloads (PRD 204.3, 215.4, Prompt 4.1).
     *
     * @return array{
     *     status: string,
     *     message: string,
     *     http_code: int,
     *     payment_id?: int,
     *     booking_id?: int
     * }
     */
    public function processWebhook(string $provider, Request $request): array
    {
        // 1. Identify Order ID / Invoice Number from incoming payload
        $orderId = (string) (
            $request->input('order_id')
            ?: $request->input('external_id')
            ?: $request->input('data.reference_id')
            ?: ''
        );

        $invoice = null;
        if ($orderId !== '') {
            $invoice = Invoice::with(['booking', 'business'])
                ->where('invoice_number', $orderId)
                ->first();

            if (! $invoice) {
                // Fallback match by booking code
                $booking = Booking::where('code', $orderId)->first();
                if ($booking) {
                    $invoice = Invoice::with(['booking', 'business'])
                        ->where('booking_id', $booking->id)
                        ->latest('id')
                        ->first();
                }
            }
        }

        // 2. Resolve gateway for the specific business (or platform fallback)
        $business = $invoice?->business;
        $gateway = $this->gatewayManager->resolve($provider, $business);
        $result = $gateway->verifyWebhook($request);

        // 3. Signature / Token verification
        if (! $result->isValid) {
            Log::warning("Webhook signature verification failed for provider [{$provider}]", [
                'error' => $result->errorMessage,
                'ip' => $request->ip(),
            ]);

            return [
                'status' => 'error',
                'message' => $result->errorMessage ?: 'Signature webhook tidak valid.',
                'http_code' => 401,
            ];
        }

        // 4. Verify that invoice was found
        if (! $invoice) {
            Log::warning("Invoice not found for webhook order_id [{$orderId}]", ['provider' => $provider]);

            return [
                'status' => 'not_found',
                'message' => 'Invoice atau reservasi tidak ditemukan di sistem.',
                'http_code' => 404,
            ];
        }

        // 5. IDEMPOTENCY CHECK (PRD 204.3, 214):
        // If this provider_event_id has already been processed and persisted, return 200 OK immediately
        // without running side-effects again.
        if (! empty($result->providerEventId)) {
            $existingPayment = Payment::where('provider', $provider)
                ->where('provider_event_id', $result->providerEventId)
                ->first();

            if ($existingPayment) {
                Log::info("Duplicate webhook event [{$result->providerEventId}] received and bypassed idempotently.", [
                    'provider' => $provider,
                    'payment_id' => $existingPayment->id,
                ]);

                return [
                    'status' => 'duplicate',
                    'message' => 'Event webhook telah diproses sebelumnya (Idempotent).',
                    'http_code' => 200,
                    'payment_id' => $existingPayment->id,
                    'booking_id' => $existingPayment->booking_id,
                ];
            }
        }

        $booking = $invoice->booking;
        $isSuccess = ($result->status === Payment::STATUS_SETTLEMENT);

        return DB::transaction(function () use ($provider, $result, $invoice, $isSuccess) {
            /** @var Booking $booking */
            $booking = Booking::withoutGlobalScopes()
                ->where('id', $invoice->booking_id)
                ->lockForUpdate()
                ->first() ?? $invoice->booking;

            // Find existing pending payment for this invoice or create a new record
            $payment = Payment::where('invoice_id', $invoice->id)
                ->where('provider', $provider)
                ->latest('id')
                ->first();

            // EVENT ORDERING PROTECTION:
            // If already settled, do not downgrade back to pending due to out-of-order delayed delivery
            if ($payment && $payment->isSuccessful() && $result->status === Payment::STATUS_PENDING) {
                return [
                    'status' => 'ignored',
                    'message' => 'Pembayaran sudah lunas sebelumnya. Event pending terlambat diabaikan.',
                    'http_code' => 200,
                    'payment_id' => $payment->id,
                    'booking_id' => $booking->id,
                ];
            }

            if (! $payment) {
                $datePrefix = Carbon::now()->format('Ymd');
                $randomSuffix = strtoupper(Str::random(4));
                $payment = new Payment([
                    'tenant_id' => $invoice->tenant_id,
                    'business_id' => $invoice->business_id,
                    'booking_id' => $invoice->booking_id,
                    'invoice_id' => $invoice->id,
                    'payment_number' => "PAY-{$datePrefix}-{$invoice->id}-{$randomSuffix}",
                    'provider' => $provider,
                ]);
            }

            $payment->provider_event_id = $result->providerEventId;
            $payment->provider_transaction_id = $result->providerTransactionId ?: $payment->provider_transaction_id;
            $payment->payment_method = $result->paymentMethod;
            $payment->amount_idr = $result->amountIdr ?: $payment->amount_idr ?: $invoice->amount_due_idr;
            $payment->status = $result->status;
            $payment->paid_at = $isSuccess ? ($result->paidAt ?: Carbon::now()) : $payment->paid_at;
            $payment->provider_response = $result->rawPayload;
            $payment->save();

            // Update Invoice
            if ($isSuccess) {
                $newPaidAmount = $invoice->amount_paid_idr + $payment->amount_idr;
                $invoice->amount_paid_idr = $newPaidAmount;

                if ($newPaidAmount >= $invoice->amount_due_idr) {
                    $invoice->status = Invoice::STATUS_PAID;
                } else {
                    $invoice->status = Invoice::STATUS_PARTIAL;
                }
                $invoice->save();

                // Update Booking payment status
                if ($invoice->isPaid()) {
                    $booking->payment_status = ($invoice->payment_model === Invoice::MODEL_DEPOSIT)
                        ? 'PARTIAL' // Deposit paid, remaining balance due at venue
                        : 'PAID';    // Fully paid
                } else {
                    $booking->payment_status = 'PARTIAL';
                }
                $booking->save();

                // STATE MACHINE TRANSITION (PRD 210, 213, Prompt 4.1, 4.2):
                // Valid payment transitions PENDING -> CONFIRMED strictly through BookingStateMachine
                if ($booking->status_category === BookingStatusCategory::PENDING) {
                    try {
                        $this->stateMachine->transition(
                            $booking,
                            BookingStatusCategory::CONFIRMED,
                            [
                                'actor_type' => 'gateway',
                                'source' => "payment_{$provider}",
                                'reason' => "Pembayaran online via {$provider} sebesar Rp " . number_format($payment->amount_idr, 0, ',', '.') . " berhasil terkonfirmasi.",
                                'bypass_payment_guard' => true,
                            ]
                        );
                    } catch (\Throwable $e) {
                        Log::error("State machine transition error on webhook payment: " . $e->getMessage());
                    }
                } elseif ($booking->status_category === BookingStatusCategory::EXPIRED) {
                    Log::warning("Payment received for already EXPIRED booking [{$booking->code}]. Recorded in ledger, requires manual owner review.", [
                        'payment_id' => $payment->id,
                        'amount_idr' => $payment->amount_idr,
                    ]);
                }
            } elseif (in_array($result->status, [Payment::STATUS_FAILED, Payment::STATUS_EXPIRED], true)) {
                if (! $invoice->isPaid()) {
                    $invoice->status = ($result->status === Payment::STATUS_EXPIRED) ? Invoice::STATUS_FAILED : Invoice::STATUS_FAILED;
                    $invoice->save();
                }
            }

            return [
                'status' => 'success',
                'message' => 'Status pembayaran berhasil diperbarui.',
                'http_code' => 200,
                'payment_id' => $payment->id,
                'booking_id' => $booking->id,
            ];
        });
    }

    /**
     * Record a manual payment made on location (Cash / EDC / Venue QRIS) (PRD 212).
     */
    public function recordManualPayment(
        Invoice $invoice,
        int $amountIdr,
        string $paymentMethod = Payment::METHOD_CASH,
        ?string $notes = null,
        ?int $userId = null
    ): Payment {
        $booking = $invoice->booking;

        return DB::transaction(function () use ($invoice, $booking, $amountIdr, $paymentMethod, $notes, $userId) {
            $datePrefix = Carbon::now()->format('Ymd');
            $randomSuffix = strtoupper(Str::random(4));

            $payment = Payment::create([
                'tenant_id' => $invoice->tenant_id,
                'business_id' => $invoice->business_id,
                'booking_id' => $invoice->booking_id,
                'invoice_id' => $invoice->id,
                'payment_number' => "PAY-MANUAL-{$datePrefix}-{$invoice->id}-{$randomSuffix}",
                'provider' => Payment::PROVIDER_MANUAL,
                'payment_method' => $paymentMethod,
                'amount_idr' => $amountIdr,
                'status' => Payment::STATUS_SETTLEMENT,
                'paid_at' => Carbon::now(),
                'provider_payload' => ['notes' => $notes, 'recorded_by' => $userId],
            ]);

            $newPaidAmount = $invoice->amount_paid_idr + $amountIdr;
            $invoice->amount_paid_idr = $newPaidAmount;
            $invoice->status = ($newPaidAmount >= $invoice->amount_due_idr) ? Invoice::STATUS_PAID : Invoice::STATUS_PARTIAL;
            $invoice->save();

            // Update Booking payment status
            $booking->payment_status = ($newPaidAmount >= $invoice->amount_total_idr)
                ? 'PAID'
                : (($newPaidAmount >= $invoice->amount_due_idr && $invoice->payment_model === Invoice::MODEL_DEPOSIT)
                    ? 'PARTIAL'
                    : 'PARTIAL');
            $booking->save();

            // Transition PENDING -> CONFIRMED if required
            if ($booking->status_category === BookingStatusCategory::PENDING) {
                $this->stateMachine->transition(
                    $booking,
                    BookingStatusCategory::CONFIRMED,
                    [
                        'actor_id' => $userId,
                        'actor_type' => 'user',
                        'source' => 'manual_payment',
                        'reason' => "Pembayaran manual dicatat sebesar Rp " . number_format($amountIdr, 0, ',', '.'),
                        'bypass_payment_guard' => true,
                    ]
                );
            }

            Audit::record([
                'tenant_id' => $invoice->tenant_id,
                'actor_id' => $userId,
                'actor_role' => 'owner',
                'action' => 'payment.record',
                'entity_type' => Payment::class,
                'entity_id' => $payment->id,
                'after' => ['amount_idr' => $amountIdr, 'method' => $paymentMethod],
                'source' => 'app',
            ]);

            return $payment;
        });
    }

    /**
     * Request a refund with Owner approval gate (PRD 212).
     */
    public function requestRefund(
        Payment $payment,
        int $amountIdr,
        string $reason,
        User $user
    ): PaymentRefund {
        $isOwner = $user->is_super_admin
            || $user->hasRole('Owner')
            || $user->ownedTenants()->where('id', $payment->tenant_id)->exists();

        return DB::transaction(function () use ($payment, $amountIdr, $reason, $user, $isOwner) {
            $refund = PaymentRefund::create([
                'tenant_id' => $payment->tenant_id,
                'payment_id' => $payment->id,
                'booking_id' => $payment->booking_id,
                'amount_idr' => min($amountIdr, $payment->amount_idr),
                'reason' => $reason,
                'requested_by_user_id' => $user->id,
                'approved_by_user_id' => $isOwner ? $user->id : null,
                'status' => $isOwner ? PaymentRefund::STATUS_APPROVED : PaymentRefund::STATUS_PENDING,
                'notes' => $isOwner ? 'Disetujui otomatis oleh Owner.' : 'Menunggu persetujuan Owner.',
            ]);

            if ($isOwner) {
                $this->applyRefundEffects($refund);
            }

            Audit::record([
                'tenant_id' => $payment->tenant_id,
                'actor_id' => $user->id,
                'actor_role' => $isOwner ? 'owner' : 'manager',
                'action' => 'payment.refund_requested',
                'entity_type' => PaymentRefund::class,
                'entity_id' => $refund->id,
                'after' => ['amount_idr' => $refund->amount_idr, 'status' => $refund->status],
                'source' => 'app',
            ]);

            return $refund;
        });
    }

    /**
     * Approve a pending refund request (Owner only, PRD 212).
     */
    public function approveRefund(PaymentRefund $refund, User $approver): PaymentRefund
    {
        $isOwner = $approver->is_super_admin
            || $approver->hasRole('Owner')
            || $approver->ownedTenants()->where('id', $refund->tenant_id)->exists();

        if (! $isOwner) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Hanya Pemilik Usaha (Owner) yang berhak menyetujui refund.');
        }

        return DB::transaction(function () use ($refund, $approver) {
            $refund->approved_by_user_id = $approver->id;
            $refund->status = PaymentRefund::STATUS_APPROVED;
            $refund->notes = 'Disetujui oleh ' . $approver->name;
            $refund->save();

            $this->applyRefundEffects($refund);

            Audit::record([
                'tenant_id' => $refund->tenant_id,
                'actor_id' => $approver->id,
                'actor_role' => 'owner',
                'action' => 'payment.refund_approved',
                'entity_type' => PaymentRefund::class,
                'entity_id' => $refund->id,
                'after' => ['status' => PaymentRefund::STATUS_APPROVED],
                'source' => 'app',
            ]);

            return $refund;
        });
    }

    /**
     * Apply refund side effects to invoice and booking.
     */
    protected function applyRefundEffects(PaymentRefund $refund): void
    {
        $payment = $refund->payment;
        $booking = $refund->booking;
        $invoice = $payment->invoice;

        // Update payment status
        $isFullRefund = ($refund->amount_idr >= $payment->amount_idr);
        $payment->status = $isFullRefund ? Payment::STATUS_REFUNDED : Payment::STATUS_REFUNDED;
        $payment->save();

        // Adjust invoice
        $invoice->amount_paid_idr = max(0, $invoice->amount_paid_idr - $refund->amount_idr);
        $invoice->status = ($invoice->amount_paid_idr <= 0) ? Invoice::STATUS_UNPAID : Invoice::STATUS_PARTIAL;
        $invoice->save();

        // Adjust booking
        $booking->payment_status = $isFullRefund ? 'REFUNDED' : 'PARTIAL_REFUND';
        $booking->save();
    }
}
