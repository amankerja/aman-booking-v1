<?php

namespace App\Http\Controllers\Owner;

use App\Domain\Reporting\Services\ReportService;
use App\Domain\Resource\Models\Resource;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    /**
     * Display business analytics & reporting dashboard (PRD 44, 162, 163).
     */
    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $filters = [
            'preset' => $request->string('preset')->trim()->value(),
            'date_from' => $request->string('date_from')->trim()->value(),
            'date_to' => $request->string('date_to')->trim()->value(),
            'service_id' => $request->filled('service_id') ? (int) $request->input('service_id') : null,
            'resource_id' => $request->filled('resource_id') ? (int) $request->input('resource_id') : null,
        ];

        // Ensure range is resolved
        $range = $this->reportService->resolveDateRange($filters);
        $filters['date_from'] = $range['dateFrom'];
        $filters['date_to'] = $range['dateTo'];
        $filters['preset'] = $range['preset'];

        $resourceUtilization = $this->reportService->getResourceUtilization($tenant, $filters);
        $summary = $this->reportService->getSummary($tenant, $filters, $resourceUtilization);
        $serviceMetrics = $this->reportService->getServiceMetrics($tenant, $filters);
        $dailyTrends = $this->reportService->getDailyTrends($tenant, $filters);

        /** @var \App\Domain\Identity\Models\User $user */
        $user = $request->user();
        $canExport = $this->reportService->canExport($tenant, $user);

        $filterOptions = [
            'services' => Service::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->select(['id', 'name'])
                ->get(),
            'resources' => Resource::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->whereNull('deleted_at')
                ->whereNull('archived_at')
                ->orderBy('name')
                ->select(['id', 'name'])
                ->get(),
        ];

        return Inertia::render('Owner/Reports/Index', [
            'summary' => $summary,
            'resources' => $resourceUtilization,
            'services' => $serviceMetrics,
            'daily_trends' => $dailyTrends,
            'filters' => $filters,
            'filter_options' => $filterOptions,
            'can_export' => $canExport,
        ]);
    }

    /**
     * Export reports to CSV with strict permission check (PRD 162, 163, 212).
     */
    public function export(Request $request): StreamedResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $user = $request->user();
        if (! $user instanceof \App\Domain\Identity\Models\User) {
            abort(401);
        }

        $this->reportService->authorizeExport($tenant, $user);

        $filters = [
            'preset' => $request->string('preset')->trim()->value(),
            'date_from' => $request->string('date_from')->trim()->value(),
            'date_to' => $request->string('date_to')->trim()->value(),
            'service_id' => $request->filled('service_id') ? (int) $request->input('service_id') : null,
            'resource_id' => $request->filled('resource_id') ? (int) $request->input('resource_id') : null,
        ];

        $range = $this->reportService->resolveDateRange($filters);
        $filters['date_from'] = $range['dateFrom'];
        $filters['date_to'] = $range['dateTo'];

        $tenantSlug = Str::slug($tenant->name ?: 'tenant');
        $filename = "laporan-{$tenantSlug}-{$filters['date_from']}-{$filters['date_to']}.csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return ResponseFactory::stream(function () use ($tenant, $filters, $user) {
            $this->reportService->streamCsv($tenant, $filters, $user);
        }, 200, $headers);
    }
}
