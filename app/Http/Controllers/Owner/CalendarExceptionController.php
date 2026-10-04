<?php

namespace App\Http\Controllers\Owner;

use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\CalendarException;
use App\Domain\Tenant\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CalendarExceptionController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $exceptions = $business->calendarExceptions()
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn (CalendarException $e) => [
                'id' => $e->id,
                'type' => $e->type,
                'date' => $e->date instanceof CarbonInterface ? $e->date->format('Y-m-d') : (string) $e->date,
                'title' => $e->title,
                'is_closed' => (bool) $e->is_closed,
                'open_time' => $e->open_time ? substr($e->open_time, 0, 5) : null,
                'close_time' => $e->close_time ? substr($e->close_time, 0, 5) : null,
                'note' => $e->note,
            ]);

        return Inertia::render('Owner/Settings/CalendarExceptions', [
            'exceptions' => $exceptions,
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'timezone' => $business->timezone,
            ],
            'types' => [
                ['value' => 'HOLIDAY', 'label' => 'Hari Libur Nasional / Toko Tutup'],
                ['value' => 'BLACKOUT', 'label' => 'Blackout Date (Slot Diblokir)'],
                ['value' => 'SPECIAL_OPEN', 'label' => 'Jam Buka Khusus (Event / Lembur)'],
                ['value' => 'SPECIAL_CLOSE', 'label' => 'Tutup Lebih Awal (Maintenance / Acara)'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(['HOLIDAY', 'BLACKOUT', 'SPECIAL_OPEN', 'SPECIAL_CLOSE'])],
            'date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'is_closed' => ['required', 'boolean'],
            'open_time' => ['nullable', 'string', 'required_if:is_closed,false'],
            'close_time' => ['nullable', 'string', 'required_if:is_closed,false'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $exception = CalendarException::create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'type' => $validated['type'],
            'date' => $validated['date'],
            'title' => $validated['title'],
            'is_closed' => $validated['is_closed'],
            'open_time' => ! empty($validated['open_time'])
                ? (strlen($validated['open_time']) === 5 ? $validated['open_time'].':00' : $validated['open_time'])
                : null,
            'close_time' => ! empty($validated['close_time'])
                ? (strlen($validated['close_time']) === 5 ? $validated['close_time'].':00' : $validated['close_time'])
                : null,
            'note' => $validated['note'] ?? null,
        ]);

        Audit::record([
            'action' => 'calendar_exception.created',
            'entity_type' => 'CalendarException',
            'entity_id' => $exception->id,
            'before' => null,
            'after' => $exception->toArray(),
            'tenant_id' => $tenant->id,
        ]);

        return redirect()->back()->with('success', 'Pengecualian kalender berhasil ditambahkan.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        /** @var CalendarException $exception */
        $exception = CalendarException::where('tenant_id', $tenant->id)
            ->where('business_id', $business->id)
            ->where('id', $id)
            ->firstOrFail();

        $beforeState = $exception->toArray();
        $exception->delete();

        Audit::record([
            'action' => 'calendar_exception.deleted',
            'entity_type' => 'CalendarException',
            'entity_id' => $exception->id,
            'before' => $beforeState,
            'after' => null,
            'tenant_id' => $tenant->id,
        ]);

        return redirect()->back()->with('success', 'Pengecualian kalender berhasil dihapus.');
    }
}
