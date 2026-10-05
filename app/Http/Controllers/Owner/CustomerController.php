<?php

namespace App\Http\Controllers\Owner;

use App\Domain\Booking\Models\Booking;
use App\Domain\Customer\Models\Customer;
use App\Domain\Customer\Services\CustomerService;
use App\Domain\Tenant\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerService $customerService
    ) {}

    /**
     * Display a listing of customers with search, tag filter, segmentation, and pagination.
     */
    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $search = $request->string('search')->trim()->value();
        $tag = $request->string('tag')->trim()->value();
        $segment = $request->string('segment')->trim()->value();

        $query = Customer::where('tenant_id', $tenant->id)
            ->withCount('bookings')
            ->orderBy('name');

        if ($search !== '') {
            $digits = preg_replace('/\D/', '', $search) ?? '';
            $normalized = Customer::normalizePhone($search);
            /** @var array<string> $phoneAlternatives */
            $phoneAlternatives = array_values(array_filter([
                $digits !== '' ? $digits : null,
                $normalized !== '' ? $normalized : null,
                str_starts_with($digits, '0') && strlen($digits) > 1 ? substr($digits, 1) : null,
            ]));

            // Check if search matches booking code
            /** @var array<int> $bookingCustomerIds */
            $bookingCustomerIds = Booking::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('code', 'like', "%{$search}%")
                ->pluck('customer_id')
                ->all();

            $query->where(function ($q) use ($search, $phoneAlternatives, $bookingCustomerIds) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone_e164', 'like', "%{$search}%");

                foreach ($phoneAlternatives as $alt) {
                    $q->orWhere('phone_e164', 'like', "%{$alt}%");
                }

                if (! empty($bookingCustomerIds)) {
                    $q->orWhereIn('id', $bookingCustomerIds);
                }
            });
        }

        if ($tag !== '' && $tag !== 'ALL') {
            $query->whereJsonContains('tags', $tag);
        }

        if ($segment !== '' && $segment !== 'ALL') {
            $query->withSegment($segment);
        }

        $customers = $query->paginate(15)->withQueryString();

        // Distinct tags across all customers in this tenant
        /** @var array<string> $allTags */
        $allTags = Customer::where('tenant_id', $tenant->id)
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->filter()
            ->unique()
            ->values()
            ->all();

        $user = $request->user();
        $canExport = $user && (
            $user->is_super_admin ||
            (int) $user->id === (int) $tenant->owner_user_id ||
            $user->can('customer.export')
        );

        return Inertia::render('Owner/Customers/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $search,
                'tag' => $tag,
                'segment' => $segment,
            ],
            'tags' => $allTags,
            'canExport' => $canExport,
        ]);
    }

    /**
     * Display the specified customer detail with bookings history and lifetime stats.
     */
    public function show(int $id): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Customer $customer */
        $customer = Customer::where('tenant_id', $tenant->id)->findOrFail($id);

        $details = $this->customerService->getProfileDetails($customer);

        return response()->json($details);
    }

    /**
     * Append internal note to customer profile with staff attribution (PRD 39).
     */
    public function appendNote(Request $request, int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Customer $customer */
        $customer = Customer::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $this->customerService->appendNote($customer, (string) $validated['note'], $user);

        return back()->with('success', 'Catatan internal berhasil ditambahkan.');
    }

    /**
     * Anonymize customer data per deletion request / GDPR / UU PDP (PRD 54).
     */
    public function anonymize(Request $request, int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Customer $customer */
        $customer = Customer::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        abort_unless($user !== null, 401);

        $this->customerService->anonymize($customer, $user, $validated['reason'] ?? null);

        return back()->with('success', 'Data pribadi pelanggan berhasil dianonimkan (PRD 54).');
    }

    /**
     * Store a newly created customer.
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'marketing_consent' => ['nullable', 'boolean'],
        ]);

        $this->customerService->resolveCustomer($tenant, [
            'name' => (string) $validated['name'],
            'phone' => (string) $validated['phone'],
            'email' => $validated['email'] ?? null,
            'tags' => $validated['tags'] ?? ['regular'],
            'notes' => $validated['notes'] ?? null,
            'marketing_consent' => (bool) ($validated['marketing_consent'] ?? false),
        ]);

        return redirect()->route('owner.customers.index')
            ->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    /**
     * Update the specified customer.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Customer $customer */
        $customer = Customer::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'marketing_consent' => ['nullable', 'boolean'],
            'no_show_count' => ['nullable', 'integer', 'min:0'],
        ]);

        $normalizedPhone = Customer::normalizePhone((string) $validated['phone']);

        // Check if phone changed and conflicts with another customer in the same tenant
        if ($normalizedPhone !== $customer->phone_e164) {
            $conflict = Customer::where('tenant_id', $tenant->id)
                ->where('phone_e164', $normalizedPhone)
                ->where('id', '!=', $customer->id)
                ->exists();

            if ($conflict) {
                return back()->withErrors(['phone' => 'Nomor telepon sudah digunakan oleh pelanggan lain.']);
            }
            $customer->phone_e164 = $normalizedPhone;
        }

        $customer->name = (string) $validated['name'];
        $customer->email = $validated['email'] ?? null;
        if (isset($validated['tags'])) {
            $customer->tags = array_values(array_unique($validated['tags']));
        }
        $customer->notes = $validated['notes'] ?? null;

        if (array_key_exists('marketing_consent', $validated)) {
            $customer->setMarketingConsent((bool) $validated['marketing_consent']);
        }

        if (isset($validated['no_show_count'])) {
            $customer->no_show_count = (int) $validated['no_show_count'];
        }

        $customer->save();

        return redirect()->route('owner.customers.index')
            ->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    /**
     * Merge source customer into target customer (PRD 210 point 14).
     */
    public function merge(Request $request, int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Customer $target */
        $target = Customer::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'source_id' => ['required', 'integer', 'different:id'],
        ]);

        /** @var Customer $source */
        $source = Customer::where('tenant_id', $tenant->id)->findOrFail($validated['source_id']);

        $this->customerService->merge($target, $source, $request->user());

        return redirect()->route('owner.customers.index')
            ->with('success', "Pelanggan '{$source->name}' berhasil digabungkan ke '{$target->name}'.");
    }

    /**
     * Export customers to CSV with strict permission enforcement (PRD 212).
     */
    public function export(Request $request): StreamedResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();
        $user = $request->user();
        abort_unless($user !== null, 401);

        // Service checks permission and throws AuthorizationException if unauthorized
        $data = $this->customerService->export($tenant, $user);

        $filename = 'customers-export-'.now()->format('Ymd-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return ResponseFactory::stream(function () use ($data) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Excel
            fwrite($handle, "\xEF\xBB\xBF");

            // Header row
            fputcsv($handle, [
                'ID',
                'Nama',
                'Telepon (E.164)',
                'Email',
                'Tag',
                'Catatan',
                'Marketing Consent',
                'Marketing Consent Date',
                'No-Show Count',
                'Total Booking',
                'Total Transaksi (IDR)',
                'Tanggal Dibuat',
            ]);

            foreach ($data as $row) {
                fputcsv($handle, [
                    $row['id'],
                    $row['name'],
                    $row['phone_e164'],
                    $row['email'],
                    $row['tags'],
                    $row['notes'],
                    $row['marketing_consent'],
                    $row['marketing_consent_at'],
                    $row['no_show_count'],
                    $row['total_bookings'],
                    $row['total_spent_idr'],
                    $row['created_at'],
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
