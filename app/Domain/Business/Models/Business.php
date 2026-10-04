<?php

namespace App\Domain\Business\Models;

use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToTenant;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
