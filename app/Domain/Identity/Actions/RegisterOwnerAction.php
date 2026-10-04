<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Business\Models\Business;
use App\Domain\Identity\Models\User;
use App\Domain\Subscription\Models\Plan;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Tenant\Models\BusinessMember;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterOwnerAction
{
    /**
     * @param  array{name: string, email: string, password: string, business_name: string}  $data
     * @return array{user: User, tenant: Tenant, business: Business, subscription: Subscription}
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // 1. Create User
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower(trim($data['email'])),
                'password' => Hash::make($data['password']),
                'is_super_admin' => false,
            ]);

            // 2. Create Tenant
            $tenant = Tenant::create([
                'name' => trim($data['business_name']),
                'status' => 'ACTIVE',
                'owner_user_id' => $user->id,
            ]);

            // 3. Generate unique business slug
            $baseSlug = Str::slug($data['business_name']);
            if (empty($baseSlug)) {
                $baseSlug = 'bisnis';
            }

            $slug = $baseSlug;
            $counter = 1;
            while (Business::withoutGlobalScopes()->where('slug', $slug)->exists()) {
                $counter++;
                $slug = "{$baseSlug}-{$counter}";
            }

            // 4. Create initial Business
            $business = Business::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'slug' => $slug,
                'name' => trim($data['business_name']),
                'timezone' => 'Asia/Jakarta',
                'published_at' => now(),
            ]);

            // 5. Create default TRIAL subscription (14 days)
            $plan = Plan::where('code', 'BASIC')->first() ?? Plan::first();
            $subscription = Subscription::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan ? $plan->id : 1,
                'status' => 'TRIAL',
                'trial_ends_at' => now()->addDays(14),
                'current_period_start' => now(),
                'current_period_end' => now()->addDays(14),
            ]);

            // 6. Assign Owner Role in Spatie Teams
            setPermissionsTeamId($tenant->id);
            $user->assignRole('Owner');

            // 7. Create BusinessMember record
            BusinessMember::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'preset' => 'OWNER',
                'permissions' => null,
            ]);

            return [
                'user' => $user,
                'tenant' => $tenant,
                'business' => $business,
                'subscription' => $subscription,
            ];
        });
    }
}
