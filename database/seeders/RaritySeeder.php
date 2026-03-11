<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RaritySeeder extends Seeder
{
    public function run(): void
    {
        $rarities = [
            [
                'name' => 'Common',
                'slug' => 'common',
                'color' => '#9CA3AF',
                'drop_rate' => 50.0,
            ],
            [
                'name' => 'Uncommon',
                'slug' => 'uncommon',
                'color' => '#10B981',
                'drop_rate' => 30.0,
            ],
            [
                'name' => 'Rare',
                'slug' => 'rare',
                'color' => '#3B82F6',
                'drop_rate' => 15.0,
            ],
            [
                'name' => 'Epic',
                'slug' => 'epic',
                'color' => '#8B5CF6',
                'drop_rate' => 4.0,
            ],
            [
                'name' => 'Legendary',
                'slug' => 'legendary',
                'color' => '#F59E0B',
                'drop_rate' => 0.8,
            ],
            [
                'name' => 'Icône du club',
                'slug' => 'icone',
                'color' => '#EC4899',
                'drop_rate' => 0.2,
            ],
        ];

        DB::table('rarities')->insert($rarities);

        $this->command->info('6 raretés créées avec succès !');
    }
}