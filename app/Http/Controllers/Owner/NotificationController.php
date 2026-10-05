<?php

namespace App\Http\Controllers\Owner;

use App\Domain\Notification\Enums\NotificationStatus;
use App\Domain\Notification\Models\NotificationLog;
use App\Domain\Notification\Models\NotificationTemplate;
use App\Domain\Notification\Services\NotificationService;
use App\Domain\Tenant\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Display notification templates and delivery logs (PRD 37, 61, 214, 216).
     */
    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        // Ensure all default templates exist
        $this->notificationService->ensureTenantTemplates($tenant->id);

        $templates = NotificationTemplate::where('tenant_id', $tenant->id)
            ->orderBy('event')
            ->orderBy('channel')
            ->get();

        $statusFilter = $request->query('status');
        $channelFilter = $request->query('channel');
        $search = $request->query('search');

        $logsQuery = NotificationLog::where('tenant_id', $tenant->id)
            ->with(['booking:id,code,start_at'])
            ->latest('id');

        if ($statusFilter && in_array($statusFilter, ['PENDING', 'SENT', 'FAILED', 'DEAD_LETTER'], true)) {
            $logsQuery->where('status', $statusFilter);
        }

        if ($channelFilter && in_array($channelFilter, ['EMAIL', 'WHATSAPP'], true)) {
            $logsQuery->where('channel', $channelFilter);
        }

        if ($search) {
            $logsQuery->where(function ($q) use ($search) {
                $q->where('recipient', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhereHas('booking', function ($bq) use ($search) {
                        $bq->where('code', 'like', "%{$search}%");
                    });
            });
        }

        $logs = $logsQuery->paginate(20)->withQueryString();

        $counts = [
            'total' => NotificationLog::where('tenant_id', $tenant->id)->count(),
            'sent' => NotificationLog::where('tenant_id', $tenant->id)->where('status', NotificationStatus::SENT->value)->count(),
            'failed' => NotificationLog::where('tenant_id', $tenant->id)->where('status', NotificationStatus::FAILED->value)->count(),
            'dead_letter' => NotificationLog::where('tenant_id', $tenant->id)->where('status', NotificationStatus::DEAD_LETTER->value)->count(),
        ];

        $availableVariables = [
            ['name' => '{{customer.name}}', 'description' => 'Nama lengkap pelanggan'],
            ['name' => '{{customer.phone}}', 'description' => 'Nomor HP/WhatsApp pelanggan'],
            ['name' => '{{customer.email}}', 'description' => 'Email pelanggan'],
            ['name' => '{{business.name}}', 'description' => 'Nama unit usaha / bisnis'],
            ['name' => '{{business.address}}', 'description' => 'Alamat bisnis'],
            ['name' => '{{business.phone}}', 'description' => 'Nomor telepon/WhatsApp bisnis'],
            ['name' => '{{booking.code}}', 'description' => 'Kode booking reservasi'],
            ['name' => '{{service.name}}', 'description' => 'Nama layanan'],
            ['name' => '{{service.price}}', 'description' => 'Harga tarif layanan (Rp)'],
            ['name' => '{{booking.date}}', 'description' => 'Hari dan tanggal reservasi (Bahasa Indonesia)'],
            ['name' => '{{booking.time}}', 'description' => 'Rentang jam reservasi'],
            ['name' => '{{staff.name}}', 'description' => 'Nama staf/petugas teralokasi'],
            ['name' => '{{resource.name}}', 'description' => 'Nama ruangan/alat teralokasi'],
            ['name' => '{{booking.total}}', 'description' => 'Total biaya reservasi (Rp)'],
            ['name' => '{{payment.status}}', 'description' => 'Status pembayaran (Lunas, Belum Bayar)'],
            ['name' => '{{manage_booking_url}}', 'description' => 'Tautan langsung kelola/reschedule jadwal'],
        ];

        return Inertia::render('Owner/Settings/Notifications', [
            'templates' => $templates,
            'logs' => $logs,
            'counts' => $counts,
            'filters' => [
                'status' => $statusFilter,
                'channel' => $channelFilter,
                'search' => $search,
            ],
            'availableVariables' => $availableVariables,
        ]);
    }

    /**
     * Update an existing notification template.
     */
    public function updateTemplate(Request $request, int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var NotificationTemplate $template */
        $template = NotificationTemplate::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);

        $template->update($validated);

        return back()->with('success', "Template '{$template->name}' berhasil diperbarui.");
    }

    /**
     * Reset a template to its system default definition.
     */
    public function resetTemplate(int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var NotificationTemplate $template */
        $template = NotificationTemplate::where('tenant_id', $tenant->id)->findOrFail($id);

        $this->notificationService->resetTemplateToDefault($template);

        return back()->with('success', "Template '{$template->name}' berhasil di-reset ke standar bawaan.");
    }

    /**
     * Manually retry a failed or dead-letter notification.
     */
    public function retryLog(int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var NotificationLog $log */
        $log = NotificationLog::where('tenant_id', $tenant->id)->findOrFail($id);

        $this->notificationService->retry($log);

        return back()->with('success', "Pengiriman notifikasi ID #{$log->id} dijadwalkan ulang ke antrean.");
    }
}
