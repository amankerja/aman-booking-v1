<?php

namespace App\Domain\Customer\Models;

use App\Domain\Booking\Models\Booking;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
 * @property string|null $notes
 * @property Carbon|null $marketing_consent_at
 * @property int $no_show_count
 * @property bool $is_verified
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $anonymized_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read bool $is_anonymized
 * @property-read Tenant $tenant
 * @property-read Collection<int, Booking> $bookings
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
        'notes',
        'marketing_consent_at',
        'no_show_count',
        'is_verified',
        'metadata',
        'anonymized_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'notes' => 'string',
            'marketing_consent_at' => 'datetime',
            'no_show_count' => 'integer',
            'is_verified' => 'boolean',
            'metadata' => 'array',
            'anonymized_at' => 'datetime',
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
        $trimmed = trim($phone);
        $hasPlus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D/', '', $trimmed) ?? '';

        if ($digits === '') {
            return '';
        }

        if ($hasPlus) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '08')) {
            return '+62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '628')) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '8') && strlen($digits) >= 9 && strlen($digits) <= 13) {
            return '+62'.$digits;
        }

        return '+'.$digits;
    }

    /**
     * Determine if customer has explicit marketing consent (PRD 40).
     */
    public function hasMarketingConsent(): bool
    {
        return $this->marketing_consent_at !== null && $this->anonymized_at === null;
    }

    /**
     * Check if customer is eligible to receive marketing broadcasts/messages (PRD 40).
     */
    public function canReceiveMarketing(): bool
    {
        return $this->hasMarketingConsent();
    }

    /**
     * Check if customer record has been anonymized (PRD 54).
     */
    public function getIsAnonymizedAttribute(): bool
    {
        return $this->anonymized_at !== null;
    }

    /**
     * Scope customers by CRM segment (PRD 39).
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeWithSegment(Builder $query, string $segment): Builder
    {
        return match (strtoupper($segment)) {
            'VIP' => $query->whereJsonContains('tags', 'vip'),
            'REPEAT' => $query->has('bookings', '>', 1),
            'NO_SHOW_RISK' => $query->where('no_show_count', '>', 0),
            'WITH_CONSENT' => $query->whereNotNull('marketing_consent_at')->whereNull('anonymized_at'),
            'WITHOUT_CONSENT' => $query->whereNull('marketing_consent_at'),
            'ANONYMIZED' => $query->whereNotNull('anonymized_at'),
            default => $query,
        };
    }

    /**
     * Update marketing consent status.
     */
    public function setMarketingConsent(bool $consent): self
    {
        $this->marketing_consent_at = $consent ? now() : null;

        return $this;
    }

    /**
     * Scope for fast searching across name, phone, email, and tags (PRD 39).
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $cleanedTerm = trim($term);
        if ($cleanedTerm === '') {
            return $query;
        }

        $digits = preg_replace('/\D/', '', $cleanedTerm) ?? '';
        $normalized = self::normalizePhone($cleanedTerm);
        $phoneAlternatives = array_values(array_filter([
            $digits !== '' ? $digits : null,
            $normalized !== '' ? $normalized : null,
            str_starts_with($digits, '0') && strlen($digits) > 1 ? substr($digits, 1) : null,
        ]));

        return $query->where(function (Builder $q) use ($cleanedTerm, $phoneAlternatives) {
            $q->where('name', 'like', "%{$cleanedTerm}%")
                ->orWhere('email', 'like', "%{$cleanedTerm}%")
                ->orWhere('phone_e164', 'like', "%{$cleanedTerm}%");

            foreach ($phoneAlternatives as $alt) {
                $q->orWhere('phone_e164', 'like', "%{$alt}%");
            }
        });
    }

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }
}
