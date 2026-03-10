<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
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
            RaritySeeder::class,
            CardSeeder::class,
            PackSeeder::class,
            MoneyPackageSeeder::class,
            MedalSeeder::class,
            UserSeeder::class,
            UserProfileSeeder::class,
            ChallengeSeeder::class,
        ]);




    }
}


