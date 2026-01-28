<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Users
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.ban',
            
            // Drivers
            'drivers.view',
            'drivers.approve',
            'drivers.reject',
            'drivers.ban',
            
            // Rides
            'rides.view',
            'rides.manage',
            'rides.cancel',
            
            // Bookings
            'bookings.view',
            'bookings.manage',
            'bookings.cancel',
            'bookings.refund',
            
            // Payouts
            'payouts.view',
            'payouts.approve',
            'payouts.reject',
            'payouts.mark_paid',
            
            // Settings
            'settings.manage',
            
            // Cities
            'cities.manage',
            
            // Support
            'support.view',
            'support.reply',
            'support.change_status',
            'tickets.manage',
            'tickets.assign',
            
            // Reports
            'reports.view',
            'reports.export',
            
            // Audit logs
            'audit_logs.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $cityAdmin = Role::firstOrCreate(['name' => 'city_admin']);
        $supportStaff = Role::firstOrCreate(['name' => 'support_staff']);

        // Assign all permissions to super admin
        $superAdmin->givePermissionTo(Permission::all());

        // Assign permissions to city admin
        $cityAdmin->givePermissionTo([
            'users.view',
            'drivers.view',
            'drivers.approve',
            'drivers.reject',
            'rides.view',
            'bookings.view',
            'bookings.manage',
            'payouts.view',
            'payouts.approve',
            'payouts.mark_paid',
            'support.view',
            'support.reply',
            'support.change_status',
            'tickets.manage',
            'tickets.assign',
            'reports.view',
        ]);

        // Assign permissions to support staff
        $supportStaff->givePermissionTo([
            'users.view',
            'drivers.view',
            'rides.view',
            'bookings.view',
            'tickets.manage',
            'tickets.assign',
        ]);
    }
}
