<?php

namespace App\Domain\Payment\Models;

use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $business_id
 * @property int $booking_id
 * @property int $invoice_id
 * @property string $payment_number
 * @property string $provider
 * @property string|null $provider_event_id
 * @property string|null $provider_transaction_id
 * @property string $payment_method
 * @property int $amount_idr
 * @property string $status
 * @property string|null $snap_token
 * @property string|null $checkout_url
 * @property string|null $qr_string
 * @property array<string, mixed>|null $provider_payload
 * @property array<string, mixed>|null $provider_response
 * @property Carbon|null $paid_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Business $business
 * @property-read Booking $booking
 * @property-read Invoice $invoice
 * @property-read Collection<int, PaymentRefund> $refunds
 */
class Payment extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_SETTLEMENT = 'SETTLEMENT';
    public const STATUS_PAID = 'PAID';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_REFUNDED = 'REFUNDED';

    public const PROVIDER_MIDTRANS = 'midtrans';
    public const PROVIDER_XENDIT = 'xendit';
    public const PROVIDER_MANUAL = 'manual';
    public const PROVIDER_CASH = 'cash';

    public const METHOD_QRIS = 'qris';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_GOPAY = 'gopay';
    public const METHOD_SHOPEEPAY = 'shopeepay';
    public const METHOD_CREDIT_CARD = 'credit_card';
    public const METHOD_CASH = 'cash';
    public const METHOD_OTHER = 'other';

    protected $guarded = ['id'];

    protected $casts = [
        'amount_idr' => 'integer',
        'provider_payload' => 'array',
        'provider_response' => 'array',
        'paid_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return HasMany<PaymentRefund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    /**
     * Check if payment is successful.
     */
    public function isSuccessful(): bool
    {
        return in_array($this->status, [self::STATUS_SETTLEMENT, self::STATUS_PAID], true);
    }
}
