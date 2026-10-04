<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'tenant.manage',
            'business.update',
            'service.manage',
            'service.view',
            'resource.manage',
            'resource.block_time',
            'booking.view',
            'booking.create',
            'booking.update',
            'booking.cancel',
            'booking.checkin',
            'customer.view',
            'customer.export',
            'payment.record',
            'payment.refund',
            'workflow.manage',
            'workflow.publish',
            'inventory.manage',
            'inventory.view',
            'report.view',
            'report.export',
            'team.manage',
            'audit.view',
            'integration.manage',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        // Global Super Admin role (tenant_id = null)
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web', 'tenant_id' => null]);
        $superAdminRole->syncPermissions(Permission::all());

        // Template roles for tenants (tenant_id = null as templates / base roles)
        $ownerRole = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web', 'tenant_id' => null]);
        $ownerRole->syncPermissions(Permission::all());

        $managerRole = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web', 'tenant_id' => null]);
        $managerRole->syncPermissions([
            'business.update',
            'service.manage',
            'service.view',
            'resource.manage',
            'resource.block_time',
            'booking.view',
            'booking.create',
            'booking.update',
            'booking.cancel',
            'booking.checkin',
            'customer.view',
            'customer.export',
            'payment.record',
            'workflow.manage',
            'inventory.manage',
            'inventory.view',
            'report.view',
            'report.export',
            'audit.view',
        ]);

        $frontDeskRole = Role::firstOrCreate(['name' => 'Front Desk', 'guard_name' => 'web', 'tenant_id' => null]);
        $frontDeskRole->syncPermissions([
            'service.view',
            'resource.block_time',
            'booking.view',
            'booking.create',
            'booking.update',
            'booking.cancel',
            'booking.checkin',
            'customer.view',
            'payment.record',
            'inventory.view',
            'report.view',
        ]);

        $staffRole = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web', 'tenant_id' => null]);
        $staffRole->syncPermissions([
            'service.view',
            'booking.view',
            'booking.checkin',
            'inventory.view',
        ]);

        $viewerRole = Role::firstOrCreate(['name' => 'Viewer', 'guard_name' => 'web', 'tenant_id' => null]);
        $viewerRole->syncPermissions([
            'service.view',
            'booking.view',
            'inventory.view',
            'report.view',
        ]);
    }
}
