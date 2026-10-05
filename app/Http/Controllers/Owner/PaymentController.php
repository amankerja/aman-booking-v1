<?php

namespace App\Http\Controllers\Owner;

use App\Domain\Business\Models\Business;
use App\Domain\Payment\Models\Invoice;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\PaymentRefund;
use App\Domain\Payment\Services\PaymentService;
use App\Domain\Tenant\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    protected function getActiveTenant(Request $request): Tenant
    {
        /** @var Tenant|null $tenant */
        $tenant = TenantContext::getTenant();

        if (! $tenant && $user = $request->user()) {
            $tenant = $user->ownedTenants()->first()
                ?? $user->memberships()->first()?->tenant;
        }

        if (! $tenant) {
            abort(403, 'Akses ditolak: Tenant tidak ditemukan.');
        }

        return $tenant;
    }

    /**
     * Display payments, invoices, refunds, and cashier dashboard (PRD 45, 60, 204.3, 210, 212).
     */
    public function index(Request $request): Response
    {
        $tenant = $this->getActiveTenant($request);

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $search = $request->string('search')->trim()->value();
        $status = $request->string('status', 'ALL')->value();
        $provider = $request->string('provider', 'ALL')->value();
        $tab = $request->string('tab', 'invoices')->value();

        // 1. Invoices Query
        $invoicesQuery = Invoice::where('tenant_id', $tenant->id)
            ->with(['booking.customer', 'payments'])
            ->latest('id');

        if ($status !== 'ALL') {
            $invoicesQuery->where('status', $status);
        }

        if ($search !== '') {
            $invoicesQuery->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('booking', function ($bq) use ($search) {
                        $bq->where('code', 'like', "%{$search}%")
                            ->orWhereHas('customer', function ($cq) use ($search) {
                                $cq->where('name', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $invoices = $invoicesQuery->paginate(15, ['*'], 'invoices_page')
            ->withQueryString();

        // 2. Payments Query
        $paymentsQuery = Payment::where('tenant_id', $tenant->id)
            ->with(['invoice', 'booking.customer'])
            ->latest('id');

        if ($provider !== 'ALL') {
            $paymentsQuery->where('provider', $provider);
        }

        if ($status !== 'ALL') {
            $paymentsQuery->where('status', $status);
        }

        if ($search !== '') {
            $paymentsQuery->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                    ->orWhere('provider_transaction_id', 'like', "%{$search}%")
                    ->orWhereHas('booking', function ($bq) use ($search) {
                        $bq->where('code', 'like', "%{$search}%")
                            ->orWhereHas('customer', function ($cq) use ($search) {
                                $cq->where('name', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $payments = $paymentsQuery->paginate(15, ['*'], 'payments_page')
            ->withQueryString();

        // 3. Pending Refunds (Owner Gate)
        $pendingRefunds = PaymentRefund::where('tenant_id', $tenant->id)
            ->with(['payment.invoice', 'booking.customer', 'requestedBy'])
            ->where('status', PaymentRefund::STATUS_PENDING)
            ->latest('id')
            ->get();

        // 4. Financial Counters
        $totalCollected = (int) Payment::where('tenant_id', $tenant->id)
            ->where('status', Payment::STATUS_SETTLEMENT)
            ->sum('amount_idr');

        $totalPending = (int) Invoice::where('tenant_id', $tenant->id)
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PENDING, Invoice::STATUS_PARTIAL])
            ->sum('amount_due_idr');

        $totalRefunded = (int) PaymentRefund::where('tenant_id', $tenant->id)
            ->where('status', PaymentRefund::STATUS_APPROVED)
            ->sum('amount_idr');

        $totalInvoicesCount = Invoice::where('tenant_id', $tenant->id)->count();

        // 5. Gateway Settings & Permissions
        $paymentSettings = $business->settings['payment_gateway'] ?? [];
        $isOwner = $request->user()?->is_super_admin
            || $request->user()?->hasRole('Owner')
            || ($request->user() && $tenant->owner_user_id === $request->user()->id);

        return Inertia::render('Owner/Payments/Index', [
            'invoices' => $invoices,
            'payments' => $payments,
            'pending_refunds' => $pendingRefunds,
            'metrics' => [
                'total_collected_idr' => $totalCollected,
                'total_pending_idr' => $totalPending,
                'total_refunded_idr' => $totalRefunded,
                'total_invoices_count' => $totalInvoicesCount,
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'provider' => $provider,
                'tab' => $tab,
            ],
            'settings' => [
                'default_provider' => $paymentSettings['default_provider'] ?? 'midtrans',
                'default_model' => $paymentSettings['default_model'] ?? 'full_payment',
                'deposit_percentage' => (int) ($paymentSettings['deposit_percentage'] ?? 30),
                'is_production' => (bool) ($paymentSettings['is_production'] ?? false),
                'has_midtrans_key' => ! empty($paymentSettings['midtrans_server_key']),
                'has_xendit_key' => ! empty($paymentSettings['xendit_secret_key']),
                'midtrans_client_key' => $paymentSettings['midtrans_client_key'] ?? '',
                'xendit_public_key' => $paymentSettings['xendit_public_key'] ?? '',
                'xendit_webhook_token' => $paymentSettings['xendit_webhook_token'] ?? '',
            ],
            'permissions' => [
                'can_approve_refund' => $isOwner,
            ],
            'business' => [
                'id' => $business->id,
                'slug' => $business->slug,
                'name' => $business->name,
            ],
        ]);
    }

    /**
     * Show single invoice details.
     */
    public function show(int $id, Request $request): Response
    {
        $tenant = $this->getActiveTenant($request);

        $invoice = Invoice::where('tenant_id', $tenant->id)
            ->with(['booking.customer', 'booking.service', 'payments.refunds', 'business'])
            ->findOrFail($id);

        return Inertia::render('Owner/Payments/Show', [
            'invoice' => $invoice,
        ]);
    }

    /**
     * Record a manual payment (Cash / Venue EDC / Direct QRIS) on an Invoice (PRD 212).
     */
    public function manualPayment(Request $request): RedirectResponse
    {
        $tenant = $this->getActiveTenant($request);

        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'amount_idr' => ['required', 'integer', 'min:1000'],
            'payment_method' => ['required', 'string', 'in:cash,bank_transfer,qris,edc,other'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var Invoice $invoice */
        $invoice = Invoice::where('tenant_id', $tenant->id)->findOrFail($validated['invoice_id']);

        $this->paymentService->recordManualPayment(
            invoice: $invoice,
            amountIdr: (int) $validated['amount_idr'],
            paymentMethod: $validated['payment_method'],
            notes: $validated['notes'] ?? null,
            userId: $request->user()?->id
        );

        return back()->with('success', 'Pembayaran kasir/manual berhasil dicatat dan status reservasi diperbarui.');
    }

    /**
     * Request a payment refund (PRD 212).
     * If user is Owner, auto-approved. If Manager/Staff, queued for Owner approval.
     */
    public function requestRefund(Request $request): RedirectResponse
    {
        $tenant = $this->getActiveTenant($request);

        $validated = $request->validate([
            'payment_id' => ['required', 'integer', 'exists:payments,id'],
            'amount_idr' => ['required', 'integer', 'min:1000'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        /** @var Payment $payment */
        $payment = Payment::where('tenant_id', $tenant->id)->findOrFail($validated['payment_id']);

        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $refund = $this->paymentService->requestRefund(
            payment: $payment,
            amountIdr: (int) $validated['amount_idr'],
            reason: $validated['reason'],
            user: $user
        );

        if ($refund->status === PaymentRefund::STATUS_APPROVED) {
            return back()->with('success', 'Pengembalian dana (refund) berhasil diproses.');
        }

        return back()->with('success', 'Permintaan refund telah diajukan dan menunggu persetujuan Pemilik Usaha (Owner).');
    }

    /**
     * Approve a pending refund request (Owner only gate, PRD 212).
     */
    public function approveRefund(int $id, Request $request): RedirectResponse
    {
        $tenant = $this->getActiveTenant($request);

        /** @var PaymentRefund $refund */
        $refund = PaymentRefund::where('tenant_id', $tenant->id)->findOrFail($id);

        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $isOwner = $user->is_super_admin
            || $user->hasRole('Owner')
            || $tenant->owner_user_id === $user->id;

        if (! $isOwner) {
            return back()->with('error', 'Hanya Pemilik Usaha (Owner) yang berhak menyetujui pengembalian dana.');
        }

        $this->paymentService->approveRefund($refund, $user);

        return back()->with('success', 'Permintaan refund berhasil disetujui dan saldo invoice disesuaikan.');
    }

    /**
     * Update Payment Gateway & Cashier Settings for the Business.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $tenant = $this->getActiveTenant($request);

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $validated = $request->validate([
            'default_provider' => ['required', 'string', 'in:midtrans,xendit,manual'],
            'default_model' => ['required', 'string', 'in:no_payment,deposit,full_payment,partial_payment'],
            'deposit_percentage' => ['required', 'integer', 'min:5', 'max:100'],
            'is_production' => ['required', 'boolean'],
            'midtrans_server_key' => ['nullable', 'string', 'max:255'],
            'midtrans_client_key' => ['nullable', 'string', 'max:255'],
            'xendit_secret_key' => ['nullable', 'string', 'max:255'],
            'xendit_public_key' => ['nullable', 'string', 'max:255'],
            'xendit_webhook_token' => ['nullable', 'string', 'max:255'],
        ]);

        $settings = $business->settings ?? [];
        $existingGateway = $settings['payment_gateway'] ?? [];

        // Retain existing keys if left blank in form submission
        $settings['payment_gateway'] = [
            'default_provider' => $validated['default_provider'],
            'default_model' => $validated['default_model'],
            'deposit_percentage' => (int) $validated['deposit_percentage'],
            'is_production' => (bool) $validated['is_production'],
            'midtrans_server_key' => ! empty($validated['midtrans_server_key']) ? $validated['midtrans_server_key'] : ($existingGateway['midtrans_server_key'] ?? null),
            'midtrans_client_key' => ! empty($validated['midtrans_client_key']) ? $validated['midtrans_client_key'] : ($existingGateway['midtrans_client_key'] ?? null),
            'xendit_secret_key' => ! empty($validated['xendit_secret_key']) ? $validated['xendit_secret_key'] : ($existingGateway['xendit_secret_key'] ?? null),
            'xendit_public_key' => ! empty($validated['xendit_public_key']) ? $validated['xendit_public_key'] : ($existingGateway['xendit_public_key'] ?? null),
            'xendit_webhook_token' => ! empty($validated['xendit_webhook_token']) ? $validated['xendit_webhook_token'] : ($existingGateway['xendit_webhook_token'] ?? null),
        ];

        $business->settings = $settings;
        $business->save();

        Audit::record([
            'tenant_id' => $tenant->id,
            'actor_id' => $request->user()?->id,
            'actor_role' => 'owner',
            'action' => 'payment.settings_updated',
            'entity_type' => Business::class,
            'entity_id' => $business->id,
            'after' => ['default_provider' => $validated['default_provider'], 'is_production' => $validated['is_production']],
            'source' => 'app',
        ]);

        return back()->with('success', 'Pengaturan payment gateway & kasir berhasil disimpan.');
    }
}
