<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;


class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Ask for user confirmation before running the seeder in production
        if ($this->command->confirm('Do you wish to seed the database? This will create default roles and potentially an admin user.', true)) {

            $this->call(RolesAndPermissionsSeeder::class);

            // Create a default Admin user and assign the 'Administrador BackOffice' role
            $adminUser = User::factory()->create([
                'name' => 'Admin BackOffice',
                'email' => 'admin@backoffice.test', // Changed email to be more generic
                'password' => 'password' // It's better to use Hash::make('password') if not using factory's default hashing
            ]);

            // Ensure the User model's password mutator handles hashing if 'password' is passed directly.
            // If UserFactory already hashes, this direct password set is fine if User model's fillable includes password.
            // The User model already has a setPasswordAttribute mutator, so direct assignment is fine.

            $adminRole = \Spatie\Permission\Models\Role::findByName('Administrador BackOffice', 'web');
            if ($adminUser && $adminRole) {
                $adminUser->assignRole($adminRole);
                $this->command->info('Admin user created and assigned "Administrador BackOffice" role.');
            } else {
                $this->command->error('Admin user or "Administrador BackOffice" role not found. Could not assign role.');
            }

            $this->command->info('Database seeded successfully.');
        } else {
            $this->command->info('Database seeding cancelled.');
        }
    }
}
