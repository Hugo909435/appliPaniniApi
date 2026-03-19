<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\ClubTeam;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Super admin (email: admin@example.com, mot de passe: password)
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'coins' => 1000,
                'is_super_admin' => true,
                'status' => 'active',
                'password' => Hash::make('password'),
            ]
        );
        $admin->assignRole(Role::ADMIN);

        $clubs = ClubTeam::where('is_main_club', true)->pluck('id')->values();

        // Utilisateur standard (email: test@example.com, mot de passe: password)
        $user = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Utilisateur Test',
                'coins' => 500,
                'password' => Hash::make('password'),
                'status' => 'active',
                'club_team_id' => $clubs->get(1) ?? $clubs->first(),
            ]
        );
        $user->assignRole(Role::USER);

        // Admin de club 1
        if ($clubs->isNotEmpty()) {
            $clubAdmin = User::updateOrCreate(
                ['email' => 'clubadmin@example.com'],
                [
                    'name' => 'Admin Club',
                    'coins' => 500,
                    'password' => Hash::make('password'),
                    'status' => 'active',
                    'club_team_id' => $clubs->first(),
                ]
            );
            $clubAdmin->assignRole(Role::ADMIN);
        }
    }
}
