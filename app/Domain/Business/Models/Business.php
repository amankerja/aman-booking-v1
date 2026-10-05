<?php

namespace App\Domain\Business\Models;

use App\Domain\Resource\Models\Resource;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $uuid
 * @property string $slug
 * @property string $name
 * @property string|null $logo_path
 * @property string|null $whatsapp
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property string|null $city
 * @property string|null $province
 * @property string|null $postal_code
 * @property string $timezone
 * @property string|null $description
 * @property array<string, mixed>|null $settings
 * @property array<string, mixed>|null $policies
 * @property array<string, mixed>|null $booking_rules
 * @property array<string, mixed>|null $social_links
 * @property Carbon|null $published_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant|null $tenant
 */
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'businesses';

    protected $fillable = [
        'tenant_id',
        'uuid',
        'slug',
        'name',
        'logo_path',
        'whatsapp',
        'phone',
        'email',
        'address',
        'city',
        'province',
        'postal_code',
        'timezone',
        'description',
        'settings',
        'policies',
        'booking_rules',
        'social_links',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'policies' => 'array',
            'booking_rules' => 'array',
            'social_links' => 'array',
            'published_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Business $business): void {
            if (empty($business->uuid)) {
                $business->uuid = (string) Str::uuid();
            }
            if (empty($business->slug)) {
                $business->slug = Str::slug($business->name);
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * @return HasMany<BusinessHour, $this>
     */
    public function hours(): HasMany
    {
        return $this->hasMany(BusinessHour::class, 'business_id')->orderBy('day_of_week');
    }

    /**
     * @return HasMany<CalendarException, $this>
     */
    public function calendarExceptions(): HasMany
    {
        return $this->hasMany(CalendarException::class, 'business_id')->orderBy('date');
    }

    /**
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'business_id');
    }

    /**
     * @return HasMany<Resource, $this>
     */
    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class, 'business_id');
    }

    /**
     * Get accessible logo URL.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }
}
