<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            ['name' => 'Gardien'],
            ['name' => 'Défenseur'],
            ['name' => 'Milieu'],
            ['name' => 'Attaquant'],
            ['name' => 'Entraîneur'],
            ['name' => 'Président'],
            ['name' => 'Supporter'],
            ['name' => 'Bénévole'],
        ];

        DB::table('positions')->insert($positions);

        $this->command->info('8 positions créées avec succès !');
    }
}
