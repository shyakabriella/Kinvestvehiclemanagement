<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',
            'dashboard.management',

            'users.view',
            'users.create',
            'users.update',
            'users.change_status',
            'users.assign_roles',

            'roles.view',
            'roles.manage',

            'departments.view',
            'departments.manage',

            'vehicles.view',
            'vehicles.create',
            'vehicles.update',
            'vehicles.deactivate',
            'vehicles.override_status',

            'bookings.view_own',
            'bookings.view_all',
            'bookings.create',
            'bookings.cancel_own',
            'bookings.manage',
            'bookings.approve',

            'trips.view_own',
            'trips.view_all',
            'trips.check_out',
            'trips.check_in',
            'trips.manage',
            'trips.correct',

            'maintenance.view',
            'maintenance.manage',

            'garages.view',
            'garages.manage',

            'mileage.view',
            'fuel.view',
            'fuel.manage',

            'documents.view',
            'documents.manage',

            'reports.view',
            'reports.export',

            'notifications.view',

            'audit.view',

            'settings.view',
            'settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $employee = Role::findOrCreate('employee', 'web');

        $employee->syncPermissions([
            'dashboard.view',
            'departments.view',
            'vehicles.view',
            'bookings.view_own',
            'bookings.create',
            'bookings.cancel_own',
            'trips.view_own',
            'trips.check_out',
            'trips.check_in',
            'notifications.view',
        ]);

        $fleetAdmin = Role::findOrCreate('fleet_admin', 'web');

        $fleetAdmin->syncPermissions([
            'dashboard.view',
            'users.view',
            'departments.view',
            'vehicles.view',
            'vehicles.create',
            'vehicles.update',
            'vehicles.deactivate',
            'vehicles.override_status',
            'bookings.view_own',
            'bookings.view_all',
            'bookings.create',
            'bookings.cancel_own',
            'bookings.manage',
            'bookings.approve',
            'trips.view_own',
            'trips.view_all',
            'trips.check_out',
            'trips.check_in',
            'trips.manage',
            'trips.correct',
            'maintenance.view',
            'maintenance.manage',
            'garages.view',
            'garages.manage',
            'mileage.view',
            'fuel.view',
            'fuel.manage',
            'documents.view',
            'documents.manage',
            'reports.view',
            'reports.export',
            'notifications.view',
        ]);

        $management = Role::findOrCreate('management', 'web');

        $management->syncPermissions([
            'dashboard.view',
            'dashboard.management',
            'departments.view',
            'vehicles.view',
            'bookings.view_all',
            'trips.view_all',
            'maintenance.view',
            'garages.view',
            'mileage.view',
            'fuel.view',
            'documents.view',
            'reports.view',
            'reports.export',
            'notifications.view',
        ]);

        $systemAdmin = Role::findOrCreate('system_admin', 'web');

        $systemAdmin->syncPermissions(
            Permission::where('guard_name', 'web')->get()
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(
            'KFMS roles and permissions seeded successfully.'
        );
    }
}
