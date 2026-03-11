<?php

namespace Database\Seeders;

use App\Models\ClubTeam;
use Illuminate\Database\Seeder;

class ClubTeamSeeder extends Seeder
{
    public function run(): void
    {
        $teams = [
            ['name' => 'U9', 'short_name' => 'U9'],
            ['name' => 'U10', 'short_name' => 'U10'],
            ['name' => 'U11', 'short_name' => 'U11'],
            ['name' => 'U12', 'short_name' => 'U12'],
            ['name' => 'U13', 'short_name' => 'U13'],
            ['name' => 'U14', 'short_name' => 'U14'],
            ['name' => 'U15', 'short_name' => 'U15'],
            ['name' => 'U16', 'short_name' => 'U16'],
            ['name' => 'U17', 'short_name' => 'U17'],
            ['name' => 'U18', 'short_name' => 'U18'],
            ['name' => "Sénior", 'short_name' => 'Senior'],
        ];

        foreach ($teams as $team) {
            ClubTeam::firstOrCreate(
                ['name' => $team['name']],
                ['short_name' => $team['short_name'], 'is_active' => true]
            );
        }

        $this->command->info('Équipes du club créées avec succès !');
    }
}