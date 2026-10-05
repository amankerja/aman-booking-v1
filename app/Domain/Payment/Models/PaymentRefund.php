<?php

namespace App\Domain\Payment\Models;

use App\Domain\Booking\Models\Booking;
use App\Domain\Identity\Models\User;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $payment_id
 * @property int $booking_id
 * @property int $amount_idr
 * @property string $reason
 * @property int $requested_by_user_id
 * @property int|null $approved_by_user_id
 * @property string $status
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Payment $payment
 * @property-read Booking $booking
 * @property-read User $requestedBy
 * @property-read User|null $approvedBy
 */
class PaymentRefund extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_PROCESSED = 'PROCESSED';

    protected $guarded = ['id'];

    protected $casts = [
        'amount_idr' => 'integer',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
