<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Identity\Models\User;
use App\Domain\Template\Models\SystemTemplate;
use App\Domain\Template\Models\SystemTemplateVersion;
use App\Domain\Template\Services\TemplateCatalogService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    public function __construct(
        protected TemplateCatalogService $catalogService
    ) {}

    /**
     * Display listing of system templates (PRD 185).
     */
    public function index(Request $request): Response|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->is_super_admin, 403, 'Akses ditolak. Halaman ini hanya untuk Super Admin.');

        $this->catalogService->syncDefaultTemplates();

        $templates = $this->catalogService->getTemplates($request->query('kind'));

        if ($request->wantsJson()) {
            return response()->json([
                'templates' => $templates,
            ]);
        }

        return Inertia::render('Admin/Templates/Index', [
            'templates' => $templates,
            'currentKind' => $request->query('kind'),
        ]);
    }

    /**
     * Preview template, versions, and payload structure (PRD 185).
     */
    public function show(Request $request, int $id): Response|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->is_super_admin, 403, 'Akses ditolak. Halaman ini hanya untuk Super Admin.');

        /** @var SystemTemplate $template */
        $template = SystemTemplate::with([
            'latestVersion',
            'versions' => fn ($q) => $q->orderBy('id', 'desc'),
        ])->findOrFail($id);

        $tenantUsageCount = $template->kind === SystemTemplate::KIND_WORKFLOW
            ? \App\Domain\Workflow\Models\Workflow::where('source_template_id', $template->id)->count()
            : \App\Domain\Form\Models\BookingForm::where('source_template_id', $template->id)->count();

        if ($request->wantsJson()) {
            return response()->json([
                'template' => $template,
                'tenantUsageCount' => $tenantUsageCount,
            ]);
        }

        return Inertia::render('Admin/Templates/Show', [
            'template' => $template,
            'tenantUsageCount' => $tenantUsageCount,
        ]);
    }

    /**
     * Create a new draft version for a system template (PRD 186).
     */
    public function storeVersion(Request $request, int $id): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->is_super_admin, 403, 'Akses ditolak. Halaman ini hanya untuk Super Admin.');

        /** @var SystemTemplate $template */
        $template = SystemTemplate::findOrFail($id);

        $validated = $request->validate([
            'version' => ['required', 'string', 'max:20', 'regex:/^[0-9]+\.[0-9]+\.[0-9]+$/'],
            'changelog' => ['nullable', 'string', 'max:1000'],
            'payload' => ['required', 'array'],
        ]);

        // Check if version already exists
        $exists = SystemTemplateVersion::where('system_template_id', $template->id)
            ->where('version', $validated['version'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['version' => "Versi '{$validated['version']}' sudah ada untuk template ini."]);
        }

        $newVersion = SystemTemplateVersion::create([
            'system_template_id' => $template->id,
            'version' => $validated['version'],
            'status' => SystemTemplateVersion::STATUS_DRAFT,
            'changelog' => $validated['changelog'] ?? null,
            'payload' => $validated['payload'],
            'created_by' => $user->id,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Draf versi {$newVersion->version} berhasil dibuat.",
                'version' => $newVersion,
            ], 201);
        }

        return back()->with('success', "Draf versi {$newVersion->version} berhasil dibuat.");
    }

    /**
     * Publish a template version making it the latest active template (PRD 185, 186).
     */
    public function publishVersion(Request $request, int $versionId): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->is_super_admin, 403, 'Akses ditolak. Halaman ini hanya untuk Super Admin.');

        /** @var SystemTemplateVersion $version */
        $version = SystemTemplateVersion::with('template')->findOrFail($versionId);

        $published = $this->catalogService->publishTemplateVersion($version);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Versi {$published->version} berhasil dipublikasikan secara global.",
                'version' => $published,
            ]);
        }

        return back()->with('success', "Versi {$published->version} berhasil dipublikasikan secara global.");
    }

    /**
     * Sync default templates.
     */
    public function syncDefaults(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->is_super_admin, 403, 'Akses ditolak.');

        $this->catalogService->syncDefaultTemplates();

        return back()->with('success', 'Katalog template sistem berhasil disinkronkan.');
    }
}
