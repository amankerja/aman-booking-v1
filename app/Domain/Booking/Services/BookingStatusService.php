<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Exceptions\BookingException;
use App\Domain\Booking\Models\BookingStatus;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingStatusService
{
    /**
     * Get all active custom statuses for a tenant, automatically seeding defaults if none exist.
     *
     * @return Collection<int, BookingStatus>
     */
    public function getStatusesForTenant(Tenant|int $tenant): Collection
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        $statuses = BookingStatus::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();

        if ($statuses->isEmpty()) {
            $tenantModel = $tenant instanceof Tenant ? $tenant : Tenant::findOrFail($tenantId);

            return $this->seedDefaultStatuses($tenantModel);
        }

        return $statuses;
    }

    /**
     * Seed default custom statuses for a tenant based on standard workflow.
     *
     * @return Collection<int, BookingStatus>
     */
    public function seedDefaultStatuses(Tenant $tenant, ?string $preset = null): Collection
    {
        $definitions = [
            [
                'name' => 'Menunggu Konfirmasi',
                'slug' => 'menunggu-konfirmasi',
                'category' => BookingStatusCategory::PENDING,
                'color' => '#64748b',
                'badge_bg' => 'bg-slate-100 text-slate-700',
                'icon' => 'clock',
                'sort_order' => 1,
                'is_default' => true,
                'is_active' => true,
                'description' => 'Booking baru masuk menunggu pembayaran atau konfirmasi.',
            ],
            [
                'name' => 'Terkonfirmasi',
                'slug' => 'terkonfirmasi',
                'category' => BookingStatusCategory::CONFIRMED,
                'color' => '#2563eb',
                'badge_bg' => 'bg-blue-100 text-blue-700',
                'icon' => 'check-circle',
                'sort_order' => 2,
                'is_default' => true,
                'is_active' => true,
                'description' => 'Jadwal telah dikonfirmasi dan slot dialokasikan.',
            ],
            [
                'name' => 'Customer Hadir',
                'slug' => 'customer-hadir',
                'category' => BookingStatusCategory::CHECKED_IN,
                'color' => '#0284c7',
                'badge_bg' => 'bg-sky-100 text-sky-700',
                'icon' => 'user-check',
                'sort_order' => 3,
                'is_default' => true,
                'is_active' => true,
                'description' => 'Customer telah tiba di lokasi usaha (check-in).',
            ],
            [
                'name' => 'Sedang Dilayani',
                'slug' => 'sedang-dilayani',
                'category' => BookingStatusCategory::IN_PROGRESS,
                'color' => '#d97706',
                'badge_bg' => 'bg-amber-100 text-amber-800',
                'icon' => 'play',
                'sort_order' => 4,
                'is_default' => true,
                'is_active' => true,
                'description' => 'Layanan sedang berlangsung.',
            ],
            [
                'name' => 'Selesai',
                'slug' => 'selesai',
                'category' => BookingStatusCategory::COMPLETED,
                'color' => '#059669',
                'badge_bg' => 'bg-emerald-100 text-emerald-800',
                'icon' => 'check-check',
                'sort_order' => 5,
                'is_default' => true,
                'is_active' => true,
                'description' => 'Layanan telah selesai dikerjakan.',
            ],
            [
                'name' => 'Dibatalkan',
                'slug' => 'dibatalkan',
                'category' => BookingStatusCategory::CANCELLED,
                'color' => '#dc2626',
                'badge_bg' => 'bg-rose-100 text-rose-800',
                'icon' => 'x-circle',
                'sort_order' => 6,
                'is_default' => true,
                'is_active' => true,
                'description' => 'Booking dibatalkan oleh customer atau pihak bisnis.',
            ],
            [
                'name' => 'No-Show',
                'slug' => 'no-show',
                'category' => BookingStatusCategory::NO_SHOW,
                'color' => '#e11d48',
                'badge_bg' => 'bg-red-100 text-red-800',
                'icon' => 'user-x',
                'sort_order' => 7,
                'is_default' => true,
                'is_active' => true,
                'description' => 'Customer tidak hadir tanpa pemberitahuan.',
            ],
        ];

        DB::transaction(function () use ($tenant, $definitions) {
            foreach ($definitions as $def) {
                BookingStatus::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'slug' => $def['slug'],
                    ],
                    array_merge($def, ['tenant_id' => $tenant->id])
                );
            }
        });

        return BookingStatus::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();
    }

    /**
     * Create a new custom status for a tenant.
     *
     * @param  array{
     *     name: string,
     *     slug?: string|null,
     *     category: BookingStatusCategory|string,
     *     color?: string|null,
     *     badge_bg?: string|null,
     *     icon?: string|null,
     *     sort_order?: int|null,
     *     is_default?: bool|null,
     *     is_active?: bool|null,
     *     description?: string|null
     * }  $data
     */
    public function createStatus(Tenant $tenant, array $data): BookingStatus
    {
        $category = is_string($data['category'])
            ? BookingStatusCategory::from($data['category'])
            : $data['category'];

        $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name']);

        // Ensure unique slug within tenant
        $originalSlug = $slug;
        $counter = 1;
        while (BookingStatus::where('tenant_id', $tenant->id)->where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        $sortOrder = $data['sort_order'] ?? (BookingStatus::where('tenant_id', $tenant->id)->max('sort_order') + 1);

        return BookingStatus::create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'slug' => $slug,
            'category' => $category,
            'color' => $data['color'] ?? '#2563eb',
            'badge_bg' => $data['badge_bg'] ?? null,
            'icon' => $data['icon'] ?? 'circle',
            'sort_order' => $sortOrder,
            'is_default' => $data['is_default'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'description' => $data['description'] ?? null,
        ]);
    }

    /**
     * Update an existing custom status.
     *
     * @param  array{
     *     name?: string,
     *     slug?: string,
     *     category?: BookingStatusCategory|string,
     *     color?: string,
     *     badge_bg?: string|null,
     *     icon?: string,
     *     sort_order?: int,
     *     is_default?: bool,
     *     is_active?: bool,
     *     description?: string|null
     * }  $data
     */
    public function updateStatus(BookingStatus $status, array $data): BookingStatus
    {
        if (isset($data['category'])) {
            $data['category'] = is_string($data['category'])
                ? BookingStatusCategory::from($data['category'])
                : $data['category'];
        }

        if (isset($data['slug'])) {
            $data['slug'] = Str::slug($data['slug']);
        }

        $status->update($data);

        return $status->fresh();
    }

    /**
     * Delete a custom status with safeguards.
     *
     * @throws BookingException
     */
    public function deleteStatus(BookingStatus $status): bool
    {
        // Guard: Check if any booking is currently referencing this status
        if ($status->bookings()->exists()) {
            throw new BookingException(
                'STATUS_IN_USE',
                "Status '{$status->name}' sedang digunakan oleh booking aktif dan tidak dapat dihapus. Anda dapat menonaktifkannya.",
                422
            );
        }

        return (bool) $status->delete();
    }

    /**
     * Reorder statuses for a tenant.
     *
     * @param  array<int, int>  $orderedIds
     */
    public function reorderStatuses(Tenant $tenant, array $orderedIds): void
    {
        DB::transaction(function () use ($tenant, $orderedIds) {
            foreach ($orderedIds as $index => $id) {
                BookingStatus::where('tenant_id', $tenant->id)
                    ->where('id', $id)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }
}
