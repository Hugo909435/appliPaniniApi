<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PositionSeeder::class,
            ClubTeamSeeder::class,
            RaritySeeder::class,
            PackSeeder::class,
            CardSeeder::class,
            MedalSeeder::class,
            UserSeeder::class,
            UserProfileSeeder::class,
            ChallengeSeeder::class,
        ]);
    }
}