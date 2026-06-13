<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@atlanticaterranova.com'],
            [
                'name' => 'Administrador',
                'password' => 'ellocoHelio1891!',
                'email_verified_at' => now(),
            ],
        );

        $adminRole = Role::findOrCreate('admin', 'web');
        $admin->syncRoles([$adminRole]);
    }
}
