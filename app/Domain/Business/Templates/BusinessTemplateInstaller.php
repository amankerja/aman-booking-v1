<?php

namespace App\Domain\Business\Templates;

use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Notification\Models\NotificationTemplate;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceType;
use App\Domain\Resource\Models\ServiceResourceRule;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceCategory;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BusinessTemplateInstaller
{
    /**
     * Install a business template into a tenant business.
     *
     * @param  array<string, mixed>  $customization
     * @return array{
     *     template_id: string,
     *     services_count: int,
     *     resources_count: int,
     *     hours_configured: int,
     *     origin: string,
     *     version: string
     * }
     */
    public function install(Tenant $tenant, Business $business, string $templateId, array $customization = []): array
    {
        $template = BusinessTemplateRegistry::get($templateId);

        if (! $template) {
            throw new InvalidArgumentException("Template dengan ID '{$templateId}' tidak ditemukan.");
        }

        return DB::transaction(function () use ($tenant, $business, $template, $templateId, $customization): array {
            // 1. Clean existing unreferenced draft template services & resources if re-installing
            $this->cleanPreviousDrafts($tenant, $business);

            // 2. Create or find Service Category
            /** @var ServiceCategory $category */
            $category = ServiceCategory::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'business_id' => $business->id,
                    'name' => $template['category_name'],
                ],
                [
                    'slug' => Str::slug((string) $template['category_name']),
                    'description' => "Kategori bawaan dari template {$template['name']}",
                    'sort_order' => 1,
                    'is_active' => true,
                ]
            );

            // 3. Generate Resources
            $createdResources = $this->generateResources($tenant, $business, $template, $customization);

            // 4. Generate Services and link ServiceResourceRules
            $createdServices = $this->generateServices($tenant, $business, $category, $template, $createdResources, $customization);

            // 5. Configure Operating Hours
            $hoursCount = $this->configureOperatingHours($tenant, $business, $template, $customization);

            // 6. Ensure Default Notification Templates exist
            $this->ensureNotificationTemplates($tenant);

            // 7. Update Business settings with DRAFT status (PRD 168)
            $settings = $business->settings ?? [];
            $settings['onboarding'] = [
                'completed' => false,
                'current_step' => 7, // Ready for Owner Review
                'template_id' => $templateId,
                'installed_at' => now()->toIso8601String(),
                'version' => $template['version'],
                'origin' => $template['origin'],
                'customization' => $customization,
            ];
            $settings['landing'] = $template['landing'];
            $settings['workflow'] = $template['workflow'];
            $settings['form_fields'] = $template['form'];

            $businessUpdates = [
                'settings' => $settings,
            ];

            if (empty($business->description)) {
                $businessUpdates['description'] = $template['landing']['about_text'] ?? '';
            }

            $business->update($businessUpdates);

            return [
                'template_id' => $templateId,
                'services_count' => count($createdServices),
                'resources_count' => count($createdResources),
                'hours_configured' => $hoursCount,
                'origin' => (string) $template['origin'],
                'version' => (string) $template['version'],
            ];
        });
    }

    /**
     * Clean unreferenced draft items previously created by SYSTEM_TEMPLATE.
     */
    protected function cleanPreviousDrafts(Tenant $tenant, Business $business): void
    {
        // Delete service resource rules
        ServiceResourceRule::where('tenant_id', $tenant->id)->delete();

        // Delete previous template services that have no bookings
        $templateServices = Service::where('tenant_id', $tenant->id)
            ->where('business_id', $business->id)
            ->get();

        foreach ($templateServices as $srv) {
            $rules = is_array($srv->rules) ? $srv->rules : [];
            if (($rules['origin'] ?? '') === 'SYSTEM_TEMPLATE') {
                $srv->delete();
            }
        }

        // Delete previous template resources
        $templateResources = Resource::where('tenant_id', $tenant->id)
            ->where('business_id', $business->id)
            ->get();

        foreach ($templateResources as $res) {
            $meta = is_array($res->metadata) ? $res->metadata : [];
            if (($meta['origin'] ?? '') === 'SYSTEM_TEMPLATE') {
                $res->delete();
            }
        }
    }

    /**
     * Generate resources based on template and customization.
     *
     * @param  array<string, mixed>  $template
     * @param  array<string, mixed>  $customization
     * @return array<string, resource>
     */
    protected function generateResources(Tenant $tenant, Business $business, array $template, array $customization): array
    {
        $resourcesMap = [];
        $defaultResources = $template['default_resources'] ?? [];

        // Handle specific count customizations (PRD 169)
        if ($template['id'] === 'barbershop' && isset($customization['barber_count'])) {
            $barberCount = max(1, min(10, (int) $customization['barber_count']));
            $chairCount = isset($customization['chair_count']) ? max(1, min(10, (int) $customization['chair_count'])) : $barberCount;

            $defaultResources = [];
            for ($i = 1; $i <= $barberCount; $i++) {
                $defaultResources[] = [
                    'name' => "Barber {$i}",
                    'type_code' => 'STAFF',
                    'capacity' => 1,
                ];
            }
            for ($i = 1; $i <= $chairCount; $i++) {
                $defaultResources[] = [
                    'name' => "Kursi {$i}",
                    'type_code' => 'CHAIR',
                    'capacity' => 1,
                ];
            }
        } elseif ($template['id'] === 'sports_court' && isset($customization['court_count'])) {
            $courtCount = max(1, min(10, (int) $customization['court_count']));
            $defaultResources = [];
            for ($i = 1; $i <= $courtCount; $i++) {
                $defaultResources[] = [
                    'name' => "Lapangan {$i}",
                    'type_code' => 'COURT',
                    'capacity' => 1,
                ];
            }
        }

        foreach ($defaultResources as $resDef) {
            $typeCode = $resDef['type_code'] ?? 'STAFF';
            /** @var ResourceType|null $resourceType */
            $resourceType = ResourceType::where('code', $typeCode)->first();

            if (! $resourceType) {
                $resourceType = ResourceType::firstOrCreate(
                    ['code' => $typeCode],
                    [
                        'name' => ucwords(strtolower($typeCode)),
                        'icon' => 'Box',
                        'is_staff' => $typeCode === 'STAFF',
                        'is_space' => in_array($typeCode, ['ROOM', 'CHAIR', 'COURT', 'BAY']),
                        'is_equipment' => $typeCode === 'VEHICLE',
                        'is_active' => true,
                    ]
                );
            }

            /** @var resource $resource */
            $resource = Resource::create([
                'tenant_id' => $tenant->id,
                'business_id' => $business->id,
                'resource_type_id' => $resourceType->id,
                'uuid' => (string) Str::uuid(),
                'name' => $resDef['name'],
                'capacity' => $resDef['capacity'] ?? 1,
                'visibility' => 'PUBLIC',
                'state' => 'AVAILABLE',
                'metadata' => [
                    'origin' => 'SYSTEM_TEMPLATE',
                    'template_id' => $template['id'],
                    'version' => $template['version'],
                ],
            ]);

            $resourcesMap[$resDef['name']] = $resource;
        }

        return $resourcesMap;
    }

    /**
     * Generate services based on template and customization.
     *
     * @param  array<string, mixed>  $template
     * @param  array<string, resource>  $resourcesMap
     * @param  array<string, mixed>  $customization
     * @return array<int, Service>
     */
    protected function generateServices(
        Tenant $tenant,
        Business $business,
        ServiceCategory $category,
        array $template,
        array $resourcesMap,
        array $customization
    ): array {
        $createdServices = [];
        $servicesDefs = $template['services'] ?? [];

        // Custom template override (PRD 192)
        if ($template['id'] === 'custom' && ! empty($customization['service_name'])) {
            $servicesDefs = [
                [
                    'name' => (string) $customization['service_name'],
                    'slug' => Str::slug((string) $customization['service_name']),
                    'description' => 'Layanan utama yang dikonfigurasi melalui Custom Business Wizard.',
                    'price_idr' => isset($customization['price_idr']) ? (float) $customization['price_idr'] : 100000,
                    'duration_minutes' => isset($customization['duration_minutes']) ? (int) $customization['duration_minutes'] : 60,
                    'buffer_after' => 15,
                    'capacity' => 1,
                    'is_featured' => true,
                    'resource_type' => 'STAFF',
                ],
            ];
        }

        $customerPicksStaff = $customization['customer_picks_staff']
            ?? $customization['customer_picks_court']
            ?? $customization['customer_picks_unit']
            ?? $customization['customer_picks_resource']
            ?? true;

        $assignmentMode = $customerPicksStaff
            ? ServiceResourceRule::MODE_CUSTOMER_CHOICE
            : ServiceResourceRule::MODE_AUTO_ASSIGN;

        foreach ($servicesDefs as $srvDef) {
            $baseSlug = Str::slug($srvDef['name']);
            $slug = $baseSlug;
            $counter = 1;
            while (Service::where('business_id', $business->id)->where('slug', $slug)->exists()) {
                $counter++;
                $slug = "{$baseSlug}-{$counter}";
            }

            /** @var Service $service */
            $service = Service::create([
                'tenant_id' => $tenant->id,
                'business_id' => $business->id,
                'category_id' => $category->id,
                'uuid' => (string) Str::uuid(),
                'name' => $srvDef['name'],
                'slug' => $slug,
                'description' => $srvDef['description'] ?? null,
                'price_idr' => $srvDef['price_idr'] ?? 0,
                'duration_type' => 'FIXED',
                'duration_minutes' => $srvDef['duration_minutes'] ?? 60,
                'buffer_before' => 0,
                'buffer_after' => $srvDef['buffer_after'] ?? 0,
                'capacity' => $srvDef['capacity'] ?? 1,
                'is_active' => true,
                'is_featured' => $srvDef['is_featured'] ?? false,
                'rules' => [
                    'origin' => 'SYSTEM_TEMPLATE',
                    'template_id' => $template['id'],
                    'version' => $template['version'],
                ],
            ]);

            // Link service to matching resources
            $targetType = $srvDef['resource_type'] ?? 'STAFF';
            foreach ($resourcesMap as $resource) {
                if ($resource->resourceType && $resource->resourceType->code === $targetType) {
                    ServiceResourceRule::create([
                        'tenant_id' => $tenant->id,
                        'service_id' => $service->id,
                        'resource_type_id' => $resource->resource_type_id,
                        'resource_id' => $resource->id,
                        'is_required' => true,
                        'assignment_mode' => $assignmentMode,
                        'quantity' => 1,
                    ]);
                }
            }

            $createdServices[] = $service;
        }

        return $createdServices;
    }

    /**
     * Configure operating hours for 7 days.
     *
     * @param  array<string, mixed>  $template
     * @param  array<string, mixed>  $customization
     */
    protected function configureOperatingHours(Tenant $tenant, Business $business, array $template, array $customization): int
    {
        $openTime = $customization['open_time'] ?? ($template['hours']['open_time'] ?? '09:00:00');
        $closeTime = $customization['close_time'] ?? ($template['hours']['close_time'] ?? '18:00:00');

        if (strlen($openTime) === 5) {
            $openTime .= ':00';
        }
        if (strlen($closeTime) === 5) {
            $closeTime .= ':00';
        }

        $activeDays = $template['hours']['days'] ?? [0, 1, 2, 3, 4, 5, 6];
        $breaks = $template['hours']['breaks'] ?? [];

        $configured = 0;
        for ($day = 0; $day <= 6; $day++) {
            $isOpen = in_array($day, $activeDays);

            BusinessHour::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'business_id' => $business->id,
                    'day_of_week' => $day,
                ],
                [
                    'is_open' => $isOpen,
                    'open_time' => $openTime,
                    'close_time' => $closeTime,
                    'breaks' => $isOpen ? $breaks : [],
                ]
            );
            $configured++;
        }

        return $configured;
    }

    /**
     * Ensure notification templates are seeded.
     */
    protected function ensureNotificationTemplates(Tenant $tenant): void
    {
        if (NotificationTemplate::where('tenant_id', $tenant->id)->count() === 0) {
            $defaults = NotificationTemplate::getDefaultTemplates();
            foreach ($defaults as $key => $tpl) {
                [$event, $channel] = explode('_', $key, 2);
                NotificationTemplate::create([
                    'tenant_id' => $tenant->id,
                    'event' => $event,
                    'channel' => $channel,
                    'name' => $tpl['name'],
                    'subject' => $tpl['subject'] ?? null,
                    'body' => $tpl['body'],
                    'is_active' => true,
                ]);
            }
        }
    }
}
