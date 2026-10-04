<?php

namespace Tests\Feature\Tenant;

use App\Domain\Business\Models\Business;
use App\Domain\Identity\Models\User;
use App\Domain\Subscription\Models\Plan;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Tenant\Models\BusinessMember;
use App\Domain\Tenant\Models\Tenant;
use App\Support\TenantContext;
use App\Support\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;

trait TenantIsolationTestHelper
{
    /**
     * Create a complete tenant test environment with owner and initial business.
     *
     * @return array{user: User, tenant: Tenant, business: Business, subscription: Subscription}
     */
    protected function createTenantEnvironment(string $tenantName = 'Tenant Test'): array
    {
        $user = User::factory()->create([
            'name' => $tenantName.' Owner',
            'email' => strtolower(str_replace(' ', '', $tenantName)).'@example.com',
        ]);

        $tenant = Tenant::factory()->create([
            'name' => $tenantName,
            'owner_user_id' => $user->id,
            'status' => 'ACTIVE',
        ]);

        $business = Business::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'uuid' => (string) Str::uuid(),
            'slug' => Str::slug($tenantName),
            'name' => $tenantName.' Business',
            'timezone' => 'Asia/Jakarta',
            'published_at' => now(),
        ]);

        $plan = Plan::where('code', 'BASIC')->first() ?? Plan::factory()->create(['code' => 'BASIC']);

        $subscription = Subscription::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'TRIAL',
            'trial_ends_at' => now()->addDays(14),
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(14),
        ]);

        BusinessMember::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'preset' => 'OWNER',
        ]);

        return [
            'user' => $user,
            'tenant' => $tenant,
            'business' => $business,
            'subscription' => $subscription,
        ];
    }

    /**
     * Set active tenant context for the current test run.
     */
    protected function actAsTenant(Tenant $tenant, ?User $user = null): static
    {
        TenantContext::setTenant($tenant);

        if ($user) {
            $this->actingAs($user);
        }

        return $this;
    }

    /**
     * Assert that a given model class registers TenantScope globally.
     */
    protected function assertModelHasTenantScope(string $modelClass): void
    {
        $model = new $modelClass;
        $scopes = $model->getGlobalScopes();

        $hasScope = false;
        foreach ($scopes as $scope) {
            if ($scope instanceof TenantScope || (is_string($scope) && $scope === TenantScope::class)) {
                $hasScope = true;
                break;
            }
        }

        $this->assertTrue(
            $hasScope,
            "Failed asserting that model [{$modelClass}] has [App\\Support\\TenantScope] registered globally."
        );
    }

    /**
     * Assert that all models under app/Domain that have a tenant_id column
     * explicitly register the TenantScope, unless whitelisted.
     *
     * @param  array<class-string<Model>>  $whitelist
     */
    protected function assertAllTenantModelsHaveScope(array $whitelist = []): void
    {
        $domainPath = app_path('Domain');
        $modelFiles = File::allFiles($domainPath);

        foreach ($modelFiles as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace(['/', '\\'], '\\', $file->getRelativePathname());
            $class = 'App\\Domain\\'.substr($relativePath, 0, -4);

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if (! $reflection->isSubclassOf(Model::class) || $reflection->isAbstract()) {
                continue;
            }

            if (in_array($class, $whitelist, true)) {
                continue;
            }

            /** @var Model $instance */
            $instance = new $class;
            $table = $instance->getTable();

            // Check if table has tenant_id column
            $hasTenantColumn = Schema::hasColumn($table, 'tenant_id');

            if ($hasTenantColumn) {
                $this->assertModelHasTenantScope($class);
            }
        }
    }
}
