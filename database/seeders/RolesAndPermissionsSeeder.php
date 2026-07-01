<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'manage customers',
            'manage products',
            'manage orders',
            'manage invoices',
            'manage stock',
            'print invoices',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $adminRole = Role::findOrCreate('admin', 'web');
        $salesRole = Role::findOrCreate('sales', 'web');
        $warehouseRole = Role::findOrCreate('warehouse', 'web');
        $accountantRole = Role::findOrCreate('accountant', 'web');

        $adminRole->syncPermissions($permissions);
        $salesRole->syncPermissions([
            'manage customers',
            'manage orders',
            'manage invoices',
        ]);
        $warehouseRole->syncPermissions([
            'manage products',
            'manage stock',
        ]);
        $accountantRole->syncPermissions([
            'print invoices',
        ]);
    }
}
