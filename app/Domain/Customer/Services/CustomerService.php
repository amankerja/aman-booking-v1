<?php

namespace App\Domain\Customer\Services;

use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Models\Booking;
use App\Domain\Customer\Models\Customer;
use App\Domain\Identity\Models\User;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    /**
     * Resolve customer with deduplication per tenant (PRD 210 point 14).
     * Primary dedup: phone_e164. Secondary dedup: email.
     *
     * @param  array{
     *     name: string,
     *     phone: string,
     *     email?: string|null,
     *     tags?: array<string>,
     *     notes?: string|null,
     *     marketing_consent?: bool|null,
     *     is_verified?: bool|null,
     *     metadata?: array<string, mixed>|null
     * }  $data
     */
    public function resolveCustomer(Tenant $tenant, array $data): Customer
    {
        $phone = Customer::normalizePhone($data['phone']);

        // 1. Primary Deduplication by phone_e164 within tenant
        /** @var Customer|null $existing */
        $existing = null;
        if ($phone !== '') {
            $existing = Customer::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('phone_e164', $phone)
                ->first();
        }

        // 2. Secondary Deduplication by email within tenant if phone not matched
        if (! $existing && ! empty($data['email'])) {
            $existing = Customer::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('email', $data['email'])
                ->first();
        }

        if ($existing) {
            // Update email if previously empty
            if (empty($existing->email) && ! empty($data['email'])) {
                $existing->email = $data['email'];
            }

            // Update marketing consent ONLY IF explicitly passed as true (PRD 40)
            if (isset($data['marketing_consent']) && $data['marketing_consent'] === true) {
                $existing->setMarketingConsent(true);
            }

            // Append tags if passed
            if (! empty($data['tags'])) {
                $currentTags = is_array($existing->tags) ? $existing->tags : [];
                $existing->tags = array_values(array_unique(array_merge($currentTags, $data['tags'])));
            }

            $existing->save();

            return $existing;
        }

        // 3. Create new customer
        $hasConsent = isset($data['marketing_consent']) && $data['marketing_consent'] === true;

        /** @var Customer $customer */
        $customer = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'phone_e164' => $phone,
            'email' => $data['email'] ?? null,
            'tags' => $data['tags'] ?? ['regular'],
            'notes' => $data['notes'] ?? null,
            'marketing_consent_at' => $hasConsent ? now() : null,
            'no_show_count' => 0,
            'is_verified' => $data['is_verified'] ?? false,
            'metadata' => $data['metadata'] ?? null,
        ]);

        return $customer;
    }

    /**
     * Fast search across customer name, phone, email, tags, and booking code (PRD 39 & 41).
     *
     * @return Collection<int, Customer>
     */
    public function search(Tenant $tenant, string $term, int $limit = 20): Collection
    {
        $trimmed = trim($term);
        if ($trimmed === '') {
            /** @var Collection<int, Customer> $all */
            $all = Customer::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->withCount('bookings')
                ->orderBy('name')
                ->limit($limit)
                ->get();

            return $all;
        }

        $digits = preg_replace('/\D/', '', $trimmed) ?? '';
        $normalized = Customer::normalizePhone($trimmed);
        /** @var array<string> $phoneAlternatives */
        $phoneAlternatives = array_values(array_filter([
            $digits !== '' ? $digits : null,
            $normalized !== '' ? $normalized : null,
            str_starts_with($digits, '0') && strlen($digits) > 1 ? substr($digits, 1) : null,
        ]));

        // Check if query matches any booking code in this tenant
        /** @var array<int> $bookingCustomerIds */
        $bookingCustomerIds = Booking::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('code', 'like', "%{$trimmed}%")
            ->pluck('customer_id')
            ->all();

        /** @var Collection<int, Customer> $results */
        $results = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where(function (Builder $q) use ($trimmed, $phoneAlternatives, $bookingCustomerIds) {
                $q->where('name', 'like', "%{$trimmed}%")
                    ->orWhere('email', 'like', "%{$trimmed}%")
                    ->orWhere('phone_e164', 'like', "%{$trimmed}%");

                foreach ($phoneAlternatives as $alt) {
                    $q->orWhere('phone_e164', 'like', "%{$alt}%");
                }

                if (! empty($bookingCustomerIds)) {
                    $q->orWhereIn('id', $bookingCustomerIds);
                }
            })
            ->withCount('bookings')
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return $results;
    }

    /**
     * Merge two customer records (PRD 210 point 14).
     * Reassigns all bookings, merges tags, notes, and no-shows into target.
     *
     * @throws \InvalidArgumentException
     */
    public function merge(Customer $target, Customer $source, ?User $actor = null): Customer
    {
        if ($target->id === $source->id) {
            throw new \InvalidArgumentException('Target and source customer cannot be the same.');
        }

        if ($target->tenant_id !== $source->tenant_id) {
            throw new \InvalidArgumentException('Cannot merge customers from different tenants.');
        }

        return DB::transaction(function () use ($target, $source, $actor) {
            // 1. Reassign bookings to target customer
            DB::table('bookings')
                ->where('customer_id', $source->id)
                ->update(['customer_id' => $target->id]);

            // 2. Merge tags without duplicates
            $targetTags = is_array($target->tags) ? $target->tags : [];
            $sourceTags = is_array($source->tags) ? $source->tags : [];
            $target->tags = array_values(array_unique(array_merge($targetTags, $sourceTags)));

            // 3. Merge notes with attribution
            if (! empty($source->notes)) {
                $sourceNotes = trim($source->notes);
                $append = "[Merged from {$source->name} (ID: {$source->id}) on ".now()->toDateTimeString()."]: {$sourceNotes}";
                $target->notes = empty($target->notes) ? $append : "{$target->notes}\n\n{$append}";
            }

            // 4. Combine no-show counts
            $target->no_show_count += $source->no_show_count;

            // 5. Preserve earliest marketing consent
            if ($source->marketing_consent_at !== null) {
                if ($target->marketing_consent_at === null || $source->marketing_consent_at->lt($target->marketing_consent_at)) {
                    $target->marketing_consent_at = $source->marketing_consent_at;
                }
            }

            $target->save();

            // 6. Delete source customer
            $source->delete();

            // 7. Audit Log
            Audit::record([
                'tenant_id' => $target->tenant_id,
                'actor_id' => $actor?->id,
                'actor_type' => $actor ? 'user' : 'system',
                'action' => 'customer.merge',
                'entity_type' => 'customer',
                'entity_id' => $target->id,
                'before' => [
                    'source_id' => $source->id,
                    'source_name' => $source->name,
                    'source_phone' => $source->phone_e164,
                ],
                'after' => [
                    'target_id' => $target->id,
                    'target_name' => $target->name,
                    'no_show_count' => $target->no_show_count,
                ],
                'source' => 'web',
            ]);

            /** @var Customer $fresh */
            $fresh = $target->fresh(['bookings']);

            return $fresh;
        });
    }

    /**
     * Export customers data with strict permission check (PRD 212).
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws AuthorizationException
     */
    public function export(Tenant $tenant, User $actor): array
    {
        // Permission check: PRD 212 requires customer.export
        $isTenantOwner = (int) $actor->id === (int) $tenant->owner_user_id;
        $isSuperAdmin = (bool) $actor->is_super_admin;
        $hasExportPermission = $actor->can('customer.export');

        if (! $isTenantOwner && ! $isSuperAdmin && ! $hasExportPermission) {
            throw new AuthorizationException('Anda tidak memiliki izin untuk mengekspor data customer (membutuhkan permission customer.export).');
        }

        /** @var Collection<int, Customer> $customers */
        $customers = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->with(['bookings'])
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($customers as $c) {
            $totalSpent = $c->bookings
                ->filter(fn (Booking $b) => $b->status_category === BookingStatusCategory::COMPLETED)
                ->sum('total_idr');

            $rows[] = [
                'id' => $c->id,
                'name' => $c->name,
                'phone_e164' => $c->phone_e164,
                'email' => $c->email ?? '',
                'tags' => implode(', ', is_array($c->tags) ? $c->tags : []),
                'notes' => $c->notes ?? '',
                'marketing_consent' => $c->hasMarketingConsent() ? 'Yes' : 'No',
                'marketing_consent_at' => $c->marketing_consent_at?->toIso8601String() ?? '',
                'no_show_count' => $c->no_show_count,
                'total_bookings' => $c->bookings->count(),
                'total_spent_idr' => $totalSpent,
                'created_at' => $c->created_at->toIso8601String(),
            ];
        }

        // Record Audit Log (PRD 204.1)
        Audit::record([
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'actor_type' => 'user',
            'action' => 'customer.export',
            'entity_type' => 'customer',
            'entity_id' => $tenant->id,
            'after' => [
                'exported_count' => count($rows),
            ],
            'source' => 'web',
        ]);

        return $rows;
    }
}
