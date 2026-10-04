<?php

namespace App\Http\Controllers\Owner;

use App\Domain\Business\Models\Business;
use App\Domain\Tenant\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BusinessProfileController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        return Inertia::render('Owner/Settings/BusinessProfile', [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'logo_url' => $business->logo_url,
                'whatsapp' => $business->whatsapp ?? '',
                'phone' => $business->phone ?? '',
                'email' => $business->email ?? '',
                'address' => $business->address ?? '',
                'city' => $business->city ?? '',
                'province' => $business->province ?? '',
                'postal_code' => $business->postal_code ?? '',
                'timezone' => $business->timezone ?? 'Asia/Jakarta',
                'description' => $business->description ?? '',
                'policies' => $business->policies ?? [
                    'booking_policy' => '',
                    'cancellation_policy' => '',
                    'refund_policy' => '',
                    'reschedule_policy' => '',
                ],
                'booking_rules' => $business->booking_rules ?? [
                    'min_advance_minutes' => 120,
                    'max_advance_days' => 30,
                    'allow_same_day' => true,
                    'cancellation_deadline_hours' => 24,
                    'reschedule_deadline_hours' => 12,
                    'max_reschedule_times' => 2,
                ],
                'social_links' => $business->social_links ?? [
                    'instagram' => '',
                    'facebook' => '',
                    'website' => '',
                    'tiktok' => '',
                ],
            ],
            'timezones' => [
                ['value' => 'Asia/Jakarta', 'label' => 'WIB — Asia/Jakarta (UTC+7)'],
                ['value' => 'Asia/Makassar', 'label' => 'WITA — Asia/Makassar (UTC+8)'],
                ['value' => 'Asia/Jayapura', 'label' => 'WIT — Asia/Jayapura (UTC+9)'],
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('businesses', 'slug')->ignore($business->id),
            ],
            'timezone' => ['required', 'string', Rule::in(['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'])],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:2000'],
            'policies' => ['nullable', 'array'],
            'policies.booking_policy' => ['nullable', 'string', 'max:2000'],
            'policies.cancellation_policy' => ['nullable', 'string', 'max:2000'],
            'policies.refund_policy' => ['nullable', 'string', 'max:2000'],
            'policies.reschedule_policy' => ['nullable', 'string', 'max:2000'],
            'booking_rules' => ['nullable', 'array'],
            'booking_rules.min_advance_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'booking_rules.max_advance_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'booking_rules.allow_same_day' => ['nullable', 'boolean'],
            'booking_rules.cancellation_deadline_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'booking_rules.reschedule_deadline_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'booking_rules.max_reschedule_times' => ['nullable', 'integer', 'min:0', 'max:10'],
            'social_links' => ['nullable', 'array'],
            'social_links.instagram' => ['nullable', 'string', 'max:255'],
            'social_links.facebook' => ['nullable', 'string', 'max:255'],
            'social_links.website' => ['nullable', 'string', 'max:255'],
            'social_links.tiktok' => ['nullable', 'string', 'max:255'],
        ]);

        $beforeState = $business->only([
            'name', 'slug', 'timezone', 'whatsapp', 'phone', 'email',
            'address', 'city', 'province', 'postal_code', 'description',
            'policies', 'booking_rules', 'social_links',
        ]);

        $business->update($validated);

        Audit::record([
            'action' => 'business.updated',
            'entity_type' => 'Business',
            'entity_id' => $business->id,
            'before' => $beforeState,
            'after' => $business->only([
                'name', 'slug', 'timezone', 'whatsapp', 'phone', 'email',
                'address', 'city', 'province', 'postal_code', 'description',
                'policies', 'booking_rules', 'social_links',
            ]),
            'tenant_id' => $tenant->id,
        ]);

        return redirect()->back()->with('success', 'Profil dan pengaturan bisnis berhasil diperbarui.');
    }

    public function uploadLogo(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $request->validate([
            'logo' => ['required', 'file', 'image', 'mimes:jpeg,png,webp,jpg', 'max:2048'],
        ]);

        // Delete old logo file if present
        if ($business->logo_path && Storage::disk('public')->exists($business->logo_path)) {
            Storage::disk('public')->delete($business->logo_path);
        }

        $path = $request->file('logo')->store('logos', 'public');

        $before = ['logo_path' => $business->logo_path];
        $business->update(['logo_path' => $path]);

        Audit::record([
            'action' => 'business.logo_uploaded',
            'entity_type' => 'Business',
            'entity_id' => $business->id,
            'before' => $before,
            'after' => ['logo_path' => $path],
            'tenant_id' => $tenant->id,
        ]);

        return redirect()->back()->with('success', 'Logo bisnis berhasil diunggah.');
    }

    public function removeLogo(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        if ($business->logo_path && Storage::disk('public')->exists($business->logo_path)) {
            Storage::disk('public')->delete($business->logo_path);
        }

        $before = ['logo_path' => $business->logo_path];
        $business->update(['logo_path' => null]);

        Audit::record([
            'action' => 'business.logo_removed',
            'entity_type' => 'Business',
            'entity_id' => $business->id,
            'before' => $before,
            'after' => ['logo_path' => null],
            'tenant_id' => $tenant->id,
        ]);

        return redirect()->back()->with('success', 'Logo bisnis berhasil dihapus.');
    }
}
