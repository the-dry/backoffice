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

        // Define Roles
        Role::create(['name' => 'Coordinación de servicio', 'guard_name' => 'web']);
        Role::create(['name' => 'Jefes de capacitación', 'guard_name' => 'web']);
        Role::create(['name' => 'Administrador BackOffice', 'guard_name' => 'web']);

        // Define Permissions (example)
        // Permission::create(['name' => 'manage users', 'guard_name' => 'web']);
        // Permission::create(['name' => 'view reports', 'guard_name' => 'web']);
        // Permission::create(['name' => 'manage moodle settings', 'guard_name' => 'web']);

        // Assign permissions to roles (example)
        // $adminRole = Role::findByName('Administrador BackOffice', 'web');
        // $adminRole->givePermissionTo(Permission::all());

        // $coordinacionRole = Role::findByName('Coordinación de servicio', 'web');
        // $coordinacionRole->givePermissionTo('view reports');

        // $jefesRole = Role::findByName('Jefes de capacitación', 'web');
        // $jefesRole->givePermissionTo('view reports');
        // $jefesRole->givePermissionTo('manage users'); // Example: if they manage Moodle users through BackOffice
    }
}
