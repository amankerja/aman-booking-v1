<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Booking\Enums\AllocationStatus;
use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingAllocation;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Customer\Models\Customer;
use App\Domain\Identity\Models\User;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\TimeBlock;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Audit;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * Resolve date range filter with default 30 days.
     *
     * @param  array<string, mixed>  $filters
     * @return array{startDate: Carbon, endDate: Carbon, dateFrom: string, dateTo: string, preset: string}
     */
    public function resolveDateRange(array $filters): array
    {
        $preset = isset($filters['preset']) ? (string) $filters['preset'] : '';

        $now = Carbon::now();
        if ($preset === '7d') {
            $startDate = $now->copy()->subDays(6)->startOfDay();
            $endDate = $now->copy()->endOfDay();
        } elseif ($preset === 'this_month') {
            $startDate = $now->copy()->startOfMonth()->startOfDay();
            $endDate = $now->copy()->endOfDay();
        } elseif ($preset === '30d' || empty($filters['date_from']) || empty($filters['date_to'])) {
            $preset = '30d';
            $startDate = $now->copy()->subDays(29)->startOfDay();
            $endDate = $now->copy()->endOfDay();
        } else {
            $preset = 'custom';
            $startDate = Carbon::parse((string) $filters['date_from'])->startOfDay();
            $endDate = Carbon::parse((string) $filters['date_to'])->endOfDay();
        }

        // Ensure startDate is before or equal to endDate
        if ($startDate->gt($endDate)) {
            $temp = $startDate->copy();
            $startDate = $endDate->copy()->startOfDay();
            $endDate = $temp->endOfDay();
        }

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dateFrom' => $startDate->toDateString(),
            'dateTo' => $endDate->toDateString(),
            'preset' => $preset,
        ];
    }

    /**
     * Get summary KPIs for tenant within filtered date range (PRD 44).
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, mixed>>|null  $resourceUtilization
     * @return array<string, mixed>
     */
    public function getSummary(Tenant $tenant, array $filters, ?array $resourceUtilization = null): array
    {
        $range = $this->resolveDateRange($filters);
        $startDate = $range['startDate'];
        $endDate = $range['endDate'];

        $bookingQuery = $this->buildFilteredBookingQuery($tenant, $startDate, $endDate, $filters);

        /** @var Collection<int, Booking> $bookings */
        $bookings = $bookingQuery->get();

        $totalBookings = $bookings->count();

        $completedCount = $bookings->where('status_category', BookingStatusCategory::COMPLETED)->count();
        $confirmedCount = $bookings->where('status_category', BookingStatusCategory::CONFIRMED)->count();
        $pendingCount = $bookings->where('status_category', BookingStatusCategory::PENDING)->count();
        $checkedInCount = $bookings->where('status_category', BookingStatusCategory::CHECKED_IN)->count();
        $inProgressCount = $bookings->where('status_category', BookingStatusCategory::IN_PROGRESS)->count();
        $cancelledCount = $bookings->where('status_category', BookingStatusCategory::CANCELLED)->count();
        $noShowCount = $bookings->where('status_category', BookingStatusCategory::NO_SHOW)->count();
        $expiredCount = $bookings->where('status_category', BookingStatusCategory::EXPIRED)->count();

        // Revenue: paid or completed bookings (PRD 44)
        $totalRevenue = (int) $bookings->filter(function (Booking $b) {
            return $b->payment_status === 'PAID' || $b->status_category === BookingStatusCategory::COMPLETED;
        })->sum('total_idr');

        // Customer Metrics (New vs Repeat)
        $customerIdsInPeriod = $bookings->pluck('customer_id')->unique()->filter()->values()->all();

        $newCustomersCount = 0;
        $repeatCustomersCount = 0;

        if (! empty($customerIdsInPeriod)) {
            // Find earliest booking date per customer in this tenant
            $firstBookings = Booking::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->whereIn('customer_id', $customerIdsInPeriod)
                ->selectRaw('customer_id, MIN(start_at) as first_booking_at')
                ->groupBy('customer_id')
                ->pluck('first_booking_at', 'customer_id');

            foreach ($customerIdsInPeriod as $custId) {
                $firstAt = isset($firstBookings[$custId]) ? Carbon::parse($firstBookings[$custId]) : null;
                if ($firstAt && $firstAt->gte($startDate) && $firstAt->lte($endDate)) {
                    $newCustomersCount++;
                } else {
                    $repeatCustomersCount++;
                }
            }
        }

        // Calculate average utilization
        if ($resourceUtilization === null) {
            $resourceUtilization = $this->getResourceUtilization($tenant, $filters);
        }

        $avgUtilization = 0.0;
        if (! empty($resourceUtilization)) {
            $rates = array_column($resourceUtilization, 'utilization_rate');
            $avgUtilization = round(array_sum($rates) / count($rates), 1);
        }

        return [
            'total_bookings' => $totalBookings,
            'completed_bookings' => $completedCount,
            'confirmed_bookings' => $confirmedCount,
            'pending_bookings' => $pendingCount,
            'checked_in_bookings' => $checkedInCount,
            'in_progress_bookings' => $inProgressCount,
            'cancelled_bookings' => $cancelledCount,
            'no_show_bookings' => $noShowCount,
            'expired_bookings' => $expiredCount,
            'total_revenue' => $totalRevenue,
            'new_customers_count' => $newCustomersCount,
            'repeat_customers_count' => $repeatCustomersCount,
            'average_utilization' => $avgUtilization,
            'date_from' => $range['dateFrom'],
            'date_to' => $range['dateTo'],
            'preset' => $range['preset'],
        ];
    }

    /**
     * Get resource utilization breakdown (PRD 162).
     * Formula: (Booked Resource Time / Available Resource Time) * 100%
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getResourceUtilization(Tenant $tenant, array $filters): array
    {
        $range = $this->resolveDateRange($filters);
        $startDate = $range['startDate'];
        $endDate = $range['endDate'];

        $resourceQuery = Resource::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->whereNull('archived_at')
            ->with(['resourceType', 'schedules']);

        if (! empty($filters['resource_id'])) {
            $resourceQuery->where('id', (int) $filters['resource_id']);
        }

        /** @var Collection<int, Resource> $resources */
        $resources = $resourceQuery->orderBy('name')->get();

        if ($resources->isEmpty()) {
            return [];
        }

        // Tenant Business Hours fallback
        /** @var Collection<int, BusinessHour> $businessHours */
        $businessHours = BusinessHour::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('day_of_week');

        // Tenant Time Blocks (Leave / Maintenance)
        /** @var Collection<int, TimeBlock> $timeBlocks */
        $timeBlocks = TimeBlock::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('start_at', '<', $endDate)
            ->where('end_at', '>', $startDate)
            ->get();

        // Allocations in date range
        $allocQuery = BookingAllocation::withoutGlobalScopes()
            ->where('booking_allocations.tenant_id', $tenant->id)
            ->where('booking_allocations.status', '!=', AllocationStatus::RELEASED)
            ->where('booking_allocations.start_at', '<', $endDate)
            ->where('booking_allocations.end_at', '>', $startDate)
            ->whereHas('booking', function (Builder $bq) use ($filters) {
                $bq->withoutGlobalScopes()
                    ->whereNotIn('status_category', [
                        BookingStatusCategory::CANCELLED,
                        BookingStatusCategory::EXPIRED,
                    ]);

                if (! empty($filters['service_id'])) {
                    $bq->where('service_id', (int) $filters['service_id']);
                }
            });

        /** @var Collection<int, BookingAllocation> $allocations */
        $allocations = $allocQuery->get();

        $results = [];

        // Build list of days in the period
        $period = CarbonPeriod::create($startDate->copy()->startOfDay(), '1 day', $endDate->copy()->startOfDay());

        foreach ($resources as $resource) {
            $resId = (int) $resource->id;
            $resSchedules = $resource->schedules->keyBy('day_of_week');

            // 1. Calculate Available Minutes across all days in range
            $availableMinutes = 0;

            foreach ($period as $day) {
                /** @var Carbon $day */
                $dayOfWeek = $day->dayOfWeek; // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
                $dayString = $day->toDateString();

                $dayAvailableMinutes = 0;
                $workStart = null;
                $workEnd = null;

                if ($resSchedules->has($dayOfWeek)) {
                    $schedule = $resSchedules->get($dayOfWeek);
                    if ($schedule && $schedule->is_available) {
                        $workStart = Carbon::parse("{$dayString} {$schedule->start_time}");
                        $workEnd = Carbon::parse("{$dayString} {$schedule->end_time}");
                        $shiftMinutes = max(0, $workStart->diffInMinutes($workEnd));

                        // Deduct breaks
                        if (! empty($schedule->breaks)) {
                            foreach ($schedule->breaks as $break) {
                                $bStart = Carbon::parse("{$dayString} {$break['start']}");
                                $bEnd = Carbon::parse("{$dayString} {$break['end']}");
                                $breakMinutes = max(0, $bStart->diffInMinutes($bEnd));
                                $shiftMinutes = max(0, $shiftMinutes - $breakMinutes);
                            }
                        }
                        $dayAvailableMinutes = $shiftMinutes;
                    }
                } elseif ($businessHours->has($dayOfWeek)) {
                    $bh = $businessHours->get($dayOfWeek);
                    if ($bh && $bh->is_open) {
                        $workStart = Carbon::parse("{$dayString} {$bh->open_time}");
                        $workEnd = Carbon::parse("{$dayString} {$bh->close_time}");
                        $shiftMinutes = max(0, $workStart->diffInMinutes($workEnd));

                        // Deduct breaks
                        if (! empty($bh->breaks)) {
                            foreach ($bh->breaks as $break) {
                                if (isset($break['start'], $break['end'])) {
                                    $bStart = Carbon::parse("{$dayString} {$break['start']}");
                                    $bEnd = Carbon::parse("{$dayString} {$break['end']}");
                                    $breakMinutes = max(0, $bStart->diffInMinutes($bEnd));
                                    $shiftMinutes = max(0, $shiftMinutes - $breakMinutes);
                                }
                            }
                        }
                        $dayAvailableMinutes = $shiftMinutes;
                    }
                }

                // Deduct Time Blocks (leave/cuti/blackout) overlapping this day's work window
                if ($dayAvailableMinutes > 0) {
                    $relevantBlocks = $timeBlocks->filter(function (TimeBlock $tb) use ($resId, $workStart, $workEnd) {
                        $isResourceMatch = $tb->is_all_resources || $tb->resource_id === null || (int) $tb->resource_id === $resId;
                        if (! $isResourceMatch) {
                            return false;
                        }

                        return $tb->start_at->lt($workEnd) && $tb->end_at->gt($workStart);
                    });

                    foreach ($relevantBlocks as $tb) {
                        $overlapStart = $tb->start_at->max($workStart);
                        $overlapEnd = $tb->end_at->min($workEnd);
                        $overlapMinutes = max(0, $overlapStart->diffInMinutes($overlapEnd));
                        $dayAvailableMinutes = max(0, $dayAvailableMinutes - $overlapMinutes);
                    }
                }

                $availableMinutes += $dayAvailableMinutes;
            }

            // 2. Calculate Booked Minutes
            $resAllocations = $allocations->where('resource_id', $resId);
            $bookedMinutes = 0;

            foreach ($resAllocations as $alloc) {
                // Clamped within filter date range
                $allocStart = $alloc->start_at->max($startDate);
                $allocEnd = $alloc->end_at->min($endDate);
                $minutes = max(0, $allocStart->diffInMinutes($allocEnd));
                $bookedMinutes += $minutes;
            }

            // 3. Calculate Utilization % (PRD 162)
            $utilizationRate = 0.0;
            if ($availableMinutes > 0) {
                $utilizationRate = round(($bookedMinutes / $availableMinutes) * 100, 1);
            }

            $results[] = [
                'id' => $resource->id,
                'name' => $resource->name,
                'code' => $resource->code,
                'role' => $resource->resourceType !== null ? $resource->resourceType->name : 'Staf / Resource',
                'booked_minutes' => (int) $bookedMinutes,
                'booked_hours' => round($bookedMinutes / 60, 1),
                'available_minutes' => (int) $availableMinutes,
                'available_hours' => round($availableMinutes / 60, 1),
                'utilization_rate' => $utilizationRate,
            ];
        }

        // Sort by utilization rate DESC
        usort($results, fn ($a, $b) => $b['utilization_rate'] <=> $a['utilization_rate']);

        return $results;
    }

    /**
     * Get service performance metrics (PRD 163).
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getServiceMetrics(Tenant $tenant, array $filters): array
    {
        $range = $this->resolveDateRange($filters);
        $startDate = $range['startDate'];
        $endDate = $range['endDate'];

        $bookingQuery = $this->buildFilteredBookingQuery($tenant, $startDate, $endDate, $filters);

        /** @var Collection<int, Booking> $bookings */
        $bookings = $bookingQuery->with('service.category')->get();

        $servicesQuery = Service::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->with('category');

        if (! empty($filters['service_id'])) {
            $servicesQuery->where('id', (int) $filters['service_id']);
        }

        /** @var Collection<int, Service> $services */
        $services = $servicesQuery->get();

        $results = [];

        foreach ($services as $service) {
            $svcBookings = $bookings->where('service_id', $service->id);
            $totalCount = $svcBookings->count();
            $completedCount = $svcBookings->where('status_category', BookingStatusCategory::COMPLETED)->count();
            $cancelledCount = $svcBookings->where('status_category', BookingStatusCategory::CANCELLED)->count();

            // Total revenue from paid or completed bookings
            $revenue = (int) $svcBookings->filter(function (Booking $b) {
                return $b->payment_status === 'PAID' || $b->status_category === BookingStatusCategory::COMPLETED;
            })->sum('total_idr');

            // Total active duration
            $totalMinutes = 0;
            foreach ($svcBookings as $b) {
                if ($b->status_category !== BookingStatusCategory::CANCELLED && $b->status_category !== BookingStatusCategory::EXPIRED) {
                    $totalMinutes += max(0, $b->start_at->diffInMinutes($b->end_at));
                }
            }

            $cancellationRate = 0.0;
            if ($totalCount > 0) {
                $cancellationRate = round(($cancelledCount / $totalCount) * 100, 1);
            }

            $results[] = [
                'id' => $service->id,
                'name' => $service->name,
                'category_name' => $service->category !== null ? $service->category->name : 'Umum',
                'total_bookings' => $totalCount,
                'completed_bookings' => $completedCount,
                'cancelled_bookings' => $cancelledCount,
                'cancellation_rate' => $cancellationRate,
                'total_revenue' => $revenue,
                'total_duration_minutes' => $totalMinutes,
                'total_duration_hours' => round($totalMinutes / 60, 1),
            ];
        }

        // Sort by total bookings DESC, then revenue DESC
        usort($results, function ($a, $b) {
            if ($b['total_bookings'] === $a['total_bookings']) {
                return $b['total_revenue'] <=> $a['total_revenue'];
            }

            return $b['total_bookings'] <=> $a['total_bookings'];
        });

        return $results;
    }

    /**
     * Get daily trends for charts and trend cards.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getDailyTrends(Tenant $tenant, array $filters): array
    {
        $range = $this->resolveDateRange($filters);
        $startDate = $range['startDate'];
        $endDate = $range['endDate'];

        $bookingQuery = $this->buildFilteredBookingQuery($tenant, $startDate, $endDate, $filters);

        /** @var Collection<int, Booking> $bookings */
        $bookings = $bookingQuery->get();

        $period = CarbonPeriod::create($startDate->copy()->startOfDay(), '1 day', $endDate->copy()->startOfDay());

        $daysMap = [
            0 => 'Min',
            1 => 'Sen',
            2 => 'Sel',
            3 => 'Rab',
            4 => 'Kam',
            5 => 'Jum',
            6 => 'Sab',
        ];

        $results = [];

        foreach ($period as $day) {
            /** @var Carbon $day */
            $dateStr = $day->toDateString();
            $dayStart = $day->copy()->startOfDay();
            $dayEnd = $day->copy()->endOfDay();

            $dayBookings = $bookings->filter(function (Booking $b) use ($dayStart, $dayEnd) {
                return $b->start_at->gte($dayStart) && $b->start_at->lte($dayEnd);
            });

            $totalCount = $dayBookings->count();
            $completedCount = $dayBookings->where('status_category', BookingStatusCategory::COMPLETED)->count();
            $cancelledCount = $dayBookings->where('status_category', BookingStatusCategory::CANCELLED)->count();

            $revenue = (int) $dayBookings->filter(function (Booking $b) {
                return $b->payment_status === 'PAID' || $b->status_category === BookingStatusCategory::COMPLETED;
            })->sum('total_idr');

            $results[] = [
                'date' => $dateStr,
                'day_name' => $daysMap[$day->dayOfWeek] ?? '',
                'formatted_date' => $day->translatedFormat('d M'),
                'total_bookings' => $totalCount,
                'completed_bookings' => $completedCount,
                'cancelled_bookings' => $cancelledCount,
                'revenue' => $revenue,
            ];
        }

        return $results;
    }

    /**
     * Stream CSV report output with strict permission check (PRD 162, 163, 212).
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws AuthorizationException
     */
    public function streamCsv(Tenant $tenant, array $filters, User $actor): void
    {
        $this->authorizeExport($tenant, $actor);

        $range = $this->resolveDateRange($filters);
        $summary = $this->getSummary($tenant, $filters);
        $services = $this->getServiceMetrics($tenant, $filters);
        $resources = $this->getResourceUtilization($tenant, $filters);

        // Fetch detailed bookings for section
        $bookingQuery = $this->buildFilteredBookingQuery($tenant, $range['startDate'], $range['endDate'], $filters)
            ->with(['customer', 'service', 'allocations.resource'])
            ->orderBy('start_at');

        /** @var Collection<int, Booking> $bookings */
        $bookings = $bookingQuery->get();

        $handle = fopen('php://output', 'w');
        if ($handle === false) {
            return;
        }

        // UTF-8 BOM for Microsoft Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");

        // 1. Report Title & Period
        fputcsv($handle, ['LAPORAN BISNIS & ANALITIK AMAN BOOKING']);
        fputcsv($handle, ['Tenant', $tenant->name]);
        fputcsv($handle, ['Periode', "{$range['dateFrom']} s/d {$range['dateTo']}"]);
        fputcsv($handle, ['Tanggal Dibuat', now()->format('Y-m-d H:i:s')]);
        fputcsv($handle, ['Pengguna', $actor->name." ({$actor->email})"]);
        fputcsv($handle, []);

        // 2. Summary KPIs
        fputcsv($handle, ['--- RINGKASAN METRIK UTAMA ---']);
        fputcsv($handle, ['Metrik', 'Nilai']);
        fputcsv($handle, ['Total Pemesanan', $summary['total_bookings']]);
        fputcsv($handle, ['Pemesanan Selesai (COMPLETED)', $summary['completed_bookings']]);
        fputcsv($handle, ['Pemesanan Dikonfirmasi (CONFIRMED)', $summary['confirmed_bookings']]);
        fputcsv($handle, ['Pemesanan Menunggu (PENDING)', $summary['pending_bookings']]);
        fputcsv($handle, ['Pemesanan Dibatalkan (CANCELLED)', $summary['cancelled_bookings']]);
        fputcsv($handle, ['Pemesanan Tidak Hadir (NO_SHOW)', $summary['no_show_bookings']]);
        fputcsv($handle, ['Total Pendapatan (IDR)', number_format($summary['total_revenue'], 0, ',', '.')]);
        fputcsv($handle, ['Pelanggan Baru', $summary['new_customers_count']]);
        fputcsv($handle, ['Pelanggan Repeat', $summary['repeat_customers_count']]);
        fputcsv($handle, ['Rata-rata Utilisasi Resource', $summary['average_utilization'].'%']);
        fputcsv($handle, []);

        // 3. Service Performance
        fputcsv($handle, ['--- PERFORMA LAYANAN ---']);
        fputcsv($handle, [
            'ID Layanan',
            'Nama Layanan',
            'Kategori',
            'Total Booking',
            'Selesai',
            'Dibatalkan',
            'Tingkat Pembatalan (%)',
            'Total Durasi (Jam)',
            'Total Pendapatan (IDR)',
        ]);
        foreach ($services as $svc) {
            fputcsv($handle, [
                $svc['id'],
                $svc['name'],
                $svc['category_name'],
                $svc['total_bookings'],
                $svc['completed_bookings'],
                $svc['cancelled_bookings'],
                $svc['cancellation_rate'].'%',
                $svc['total_duration_hours'],
                $svc['total_revenue'],
            ]);
        }
        fputcsv($handle, []);

        // 4. Resource Utilization
        fputcsv($handle, ['--- UTILISASI RESOURCE & STAF ---']);
        fputcsv($handle, [
            'ID Resource',
            'Nama Resource',
            'Peran / Tipe',
            'Waktu Terpakai (Menit)',
            'Waktu Terpakai (Jam)',
            'Waktu Tersedia (Menit)',
            'Waktu Tersedia (Jam)',
            'Tingkat Utilisasi (%)',
        ]);
        foreach ($resources as $res) {
            fputcsv($handle, [
                $res['id'],
                $res['name'],
                $res['role'],
                $res['booked_minutes'],
                $res['booked_hours'],
                $res['available_minutes'],
                $res['available_hours'],
                $res['utilization_rate'].'%',
            ]);
        }
        fputcsv($handle, []);

        // 5. Booking Details
        fputcsv($handle, ['--- DETAIL DAFTAR PEMESANAN ---']);
        fputcsv($handle, [
            'Kode Booking',
            'Tanggal',
            'Waktu Mulai',
            'Waktu Selesai',
            'Pelanggan',
            'Telepon Pelanggan',
            'Layanan',
            'Resource / Staf',
            'Status Pemesanan',
            'Status Pembayaran',
            'Total (IDR)',
        ]);
        foreach ($bookings as $b) {
            $resNames = $b->allocations->map(fn ($a) => $a->resource?->name)->filter()->implode(', ');
            fputcsv($handle, [
                $b->code,
                $b->start_at->format('Y-m-d'),
                $b->start_at->format('H:i'),
                $b->end_at->format('H:i'),
                $b->customer->name,
                $b->customer->phone_e164 ?? '-',
                $b->service->name,
                $resNames ?: '-',
                $b->status_category->value,
                $b->payment_status,
                $b->total_idr,
            ]);
        }

        fclose($handle);

        // Audit Log
        Audit::record([
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'actor_type' => 'user',
            'action' => 'report.export',
            'entity_type' => 'report',
            'entity_id' => $tenant->id,
            'after' => [
                'filters' => $filters,
                'bookings_count' => $bookings->count(),
            ],
            'source' => 'web',
        ]);
    }

    /**
     * Authorize report export permission.
     *
     * @throws AuthorizationException
     */
    public function authorizeExport(Tenant $tenant, User $actor): void
    {
        $isTenantOwner = (int) $actor->id === (int) $tenant->owner_user_id;
        $isSuperAdmin = (bool) ($actor->is_super_admin ?? false);
        $hasPermission = $actor->can('report.export') || $actor->can('reports.export');

        if (! $isTenantOwner && ! $isSuperAdmin && ! $hasPermission) {
            throw new AuthorizationException('Anda tidak memiliki izin untuk mengekspor data laporan (membutuhkan permission report.export).');
        }
    }

    /**
     * Check if actor has permission to export reports.
     */
    public function canExport(Tenant $tenant, User $actor): bool
    {
        try {
            $this->authorizeExport($tenant, $actor);

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }

    /**
     * Build base filtered booking query for tenant and date range.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Booking>
     */
    protected function buildFilteredBookingQuery(Tenant $tenant, Carbon $startDate, Carbon $endDate, array $filters): Builder
    {
        /** @var Builder<Booking> $query */
        $query = Booking::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('start_at', '>=', $startDate)
            ->where('start_at', '<=', $endDate);

        if (! empty($filters['service_id'])) {
            $query->where('service_id', (int) $filters['service_id']);
        }

        if (! empty($filters['resource_id'])) {
            $resourceId = (int) $filters['resource_id'];
            $query->whereHas('allocations', function (Builder $aq) use ($resourceId) {
                $aq->withoutGlobalScopes()->where('resource_id', $resourceId);
            });
        }

        return $query;
    }
}
