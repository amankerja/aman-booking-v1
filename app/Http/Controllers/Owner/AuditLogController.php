<?php

namespace App\Http\Controllers\Owner;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Tenant\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Display a listing of audit logs for the current tenant.
     */
    public function index(Request $request): Response
    {
        $tenant = TenantContext::getTenant();
        abort_unless($tenant instanceof Tenant, 404, 'Tenant tidak ditemukan.');

        $query = AuditLog::where('tenant_id', $tenant->id);

        if ($action = $request->input('action')) {
            $query->where('action', (string) $action);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->where('created_at', '>=', (string) $dateFrom.' 00:00:00');
        }

        if ($dateTo = $request->input('date_to')) {
            $query->where('created_at', '<=', (string) $dateTo.' 23:59:59');
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Owner/AuditLogs', [
            'logs' => $logs,
            'filters' => [
                'action' => (string) $request->input('action', ''),
                'date_from' => (string) $request->input('date_from', ''),
                'date_to' => (string) $request->input('date_to', ''),
            ],
            'availableActions' => [
                'user.login' => 'User Login',
                'user.logout' => 'User Logout',
                'business_member.created' => 'Member Ditambahkan',
                'business_member.updated' => 'Member Diperbarui',
                'business_member.deleted' => 'Member Dihapus',
                'subscription.created' => 'Subscription Dibuat',
                'subscription.updated' => 'Subscription Berubah',
            ],
        ]);
    }
}
