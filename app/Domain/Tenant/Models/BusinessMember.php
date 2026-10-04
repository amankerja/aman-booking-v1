<?php

namespace App\Domain\Tenant\Models;

use App\Domain\Identity\Models\User;
use App\Support\Traits\BelongsToTenant;
use Database\Factories\BusinessMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessMember extends Model
{
    /** @use HasFactory<BusinessMemberFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'business_members';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'preset',
        'permissions',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
