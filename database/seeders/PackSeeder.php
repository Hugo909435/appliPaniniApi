<?php

namespace Database\Seeders;

use App\Models\Pack;
use Illuminate\Database\Seeder;

class PackSeeder extends Seeder
{
    public function run(): void
    {
        // Keep only one pack
        Pack::query()->delete();

        Pack::create([
            'name' => 'Pack Unique',
            'slug' => 'unique',
            'description' => 'Le pack officiel du club.',
            'price' => 50,
            'money_price' => 5,
            'card_count' => 5,
            'image' => null,
            'is_active' => true,
            'rarity_boosts' => [
                'common' => 55.0,
                'uncommon' => 25.0,
                'rare' => 12.0,
                'epic' => 6.0,
                'legendary' => 1.5,
                'icone' => 0.5,
            ],
        ]);
    }
}
