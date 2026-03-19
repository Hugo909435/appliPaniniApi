<?php

namespace Database\Seeders;

use App\Models\ClubTeam;
use Illuminate\Database\Seeder;

class ClubTeamSeeder extends Seeder
{
    public function run(): void
    {
        $mainClubs = [
            [
                'name' => 'Club Alpha',
                'short_name' => 'Alpha',
                'logo' => 'https://placehold.co/200x200/FFD700/000000?text=Alpha',
                'primary_color' => '#FFD700',
            ],
            [
                'name' => 'Club Beta',
                'short_name' => 'Beta',
                'logo' => 'https://placehold.co/200x200/00BFFF/000000?text=Beta',
                'primary_color' => '#00BFFF',
            ],
        ];

        $teamNames = ['U9','U10','U11','U12','U13','U14','U15','U16','U17','U18','Senior'];

        foreach ($mainClubs as $clubData) {
            $main = ClubTeam::updateOrCreate(
                ['name' => $clubData['name']],
                [
                    'short_name' => $clubData['short_name'],
                    'logo' => $clubData['logo'],
                    'primary_color' => $clubData['primary_color'],
                    'theme_slug' => 'default',
                    'is_active' => true,
                    'is_main_club' => true,
                    'parent_id' => null,
                ]
            );

            foreach ($teamNames as $team) {
                ClubTeam::updateOrCreate(
                    ['name' => "{$team} - {$main->short_name}", 'parent_id' => $main->id],
                    [
                        'short_name' => $team,
                        'theme_slug' => 'default',
                        'is_active' => true,
                        'is_main_club' => false,
                    ]
                );
            }
        }

        $this->command->info('Clubs principaux et équipes U9 à Senior créés.');
    }
}
