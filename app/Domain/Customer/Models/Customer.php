<?php

namespace App\Domain\Customer\Models;

use App\Domain\Booking\Models\Booking;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $phone_e164
 * @property string|null $email
 * @property array<string>|null $tags
 * @property Carbon|null $marketing_consent_at
 * @property int $no_show_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'customers';

    protected $fillable = [
        'tenant_id',
        'name',
        'phone_e164',
        'email',
        'tags',
        'marketing_consent_at',
        'no_show_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'marketing_consent_at' => 'datetime',
            'no_show_count' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Normalize an Indonesian/international phone number into E.164 (+62...).
     * PRD 210 point 14.
     */
    public static function normalizePhone(string $phone): string
    {
        $cleaned = preg_replace('/[^\d+]/', '', trim($phone)) ?? '';

        if (str_starts_with($cleaned, '+')) {
            return $cleaned;
        }

        if (str_starts_with($cleaned, '08')) {
            return '+62'.substr($cleaned, 1);
        }

        if (str_starts_with($cleaned, '628')) {
            return '+'.$cleaned;
        }

        if (str_starts_with($cleaned, '8')) {
            return '+62'.$cleaned;
        }

        return $cleaned;
    }

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }
}
