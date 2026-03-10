<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    public function run(): void
    {
// Créer un utilisateur admin
        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'coins' => 1000,
        ]);
        $admin->assignRole(Role::ADMIN);

        // Créer un utilisateur normal
        $user = User::factory()->create([
            'name' => 'Utilisateur Test',
            'email' => 'test@example.com',
            'coins' => 500,
        ]);
        $user->assignRole(Role::USER);
    }
}
