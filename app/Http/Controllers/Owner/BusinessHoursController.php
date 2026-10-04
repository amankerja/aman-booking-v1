<?php

namespace App\Http\Controllers\Owner;

use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Business\Services\BusinessCalendarService;
use App\Domain\Tenant\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessHoursController extends Controller
{
    public function __construct(
        protected BusinessCalendarService $calendarService
    ) {}

    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        // Ensure 7 days are initialized
        if ($business->hours()->count() < 7) {
            $this->calendarService->initializeDefaultHours($business);
        }

        $hours = $business->hours()
            ->get()
            ->map(fn (BusinessHour $h) => [
                'id' => $h->id,
                'day_of_week' => $h->day_of_week,
                'is_open' => (bool) $h->is_open,
                'open_time' => substr($h->open_time, 0, 5), // '09:00'
                'close_time' => substr($h->close_time, 0, 5), // '17:00'
                'breaks' => array_map(function (array $b): array {
                    return [
                        'name' => (string) ($b['name'] ?? 'Istirahat'),
                        'start_time' => substr((string) ($b['start_time'] ?? ''), 0, 5),
                        'end_time' => substr((string) ($b['end_time'] ?? ''), 0, 5),
                    ];
                }, is_array($h->breaks) ? $h->breaks : []),
            ]);

        return Inertia::render('Owner/Settings/OperatingHours', [
            'hours' => $hours,
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'timezone' => $business->timezone,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $validated = $request->validate([
            'hours' => ['required', 'array', 'size:7'],
            'hours.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'hours.*.is_open' => ['required', 'boolean'],
            'hours.*.open_time' => ['required', 'string'],
            'hours.*.close_time' => ['required', 'string'],
            'hours.*.breaks' => ['nullable', 'array'],
            'hours.*.breaks.*.name' => ['required', 'string', 'max:100'],
            'hours.*.breaks.*.start_time' => ['required', 'string'],
            'hours.*.breaks.*.end_time' => ['required', 'string'],
        ]);

        $beforeState = $business->hours()->get()->toArray();

        foreach ($validated['hours'] as $dayData) {
            BusinessHour::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'business_id' => $business->id,
                    'day_of_week' => $dayData['day_of_week'],
                ],
                [
                    'is_open' => $dayData['is_open'],
                    'open_time' => strlen($dayData['open_time']) === 5 ? $dayData['open_time'].':00' : $dayData['open_time'],
                    'close_time' => strlen($dayData['close_time']) === 5 ? $dayData['close_time'].':00' : $dayData['close_time'],
                    'breaks' => array_map(fn ($b) => [
                        'name' => $b['name'],
                        'start_time' => strlen($b['start_time']) === 5 ? $b['start_time'].':00' : $b['start_time'],
                        'end_time' => strlen($b['end_time']) === 5 ? $b['end_time'].':00' : $b['end_time'],
                    ], $dayData['breaks'] ?? []),
                ]
            );
        }

        Audit::record([
            'action' => 'business.hours_updated',
            'entity_type' => 'Business',
            'entity_id' => $business->id,
            'before' => ['hours' => $beforeState],
            'after' => ['hours' => $validated['hours']],
            'tenant_id' => $tenant->id,
        ]);

        return redirect()->back()->with('success', 'Jadwal jam operasional berhasil diperbarui.');
    }
}
