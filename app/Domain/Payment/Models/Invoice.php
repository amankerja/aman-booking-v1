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
 * @property string $invoice_number
 * @property string $payment_model
 * @property int $amount_total_idr
 * @property int $amount_due_idr
 * @property int $amount_paid_idr
 * @property string $status
 * @property Carbon|null $due_at
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Business $business
 * @property-read Booking $booking
 * @property-read Collection<int, Payment> $payments
 */
class Invoice extends Model
{
    use BelongsToTenant;

    public const STATUS_UNPAID = 'UNPAID';
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PARTIAL = 'PARTIAL';
    public const STATUS_PAID = 'PAID';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const MODEL_NO_PAYMENT = 'no_payment';
    public const MODEL_FULL_PAYMENT = 'full_payment';
    public const MODEL_DEPOSIT = 'deposit';
    public const MODEL_PARTIAL_PAYMENT = 'partial_payment';

    protected $guarded = ['id'];

    protected $casts = [
        'amount_total_idr' => 'integer',
        'amount_due_idr' => 'integer',
        'amount_paid_idr' => 'integer',
        'due_at' => 'datetime',
        'metadata' => 'array',
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
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Check if invoice is fully settled.
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID || $this->amount_paid_idr >= $this->amount_due_idr;
    }

    /**
     * Get remaining balance to pay.
     */
    public function remainingDue(): int
    {
        $remaining = $this->amount_due_idr - $this->amount_paid_idr;

        return max(0, $remaining);
    }
}
