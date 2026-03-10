<?php

namespace Database\Seeders;

use App\Models\Pack;
use Illuminate\Database\Seeder;

class PackSeeder extends Seeder
{
    public function run(): void
    {
        $packs = [
            [
                'name' => 'Pack Bronze',
                'slug' => 'bronze',
                'description' => 'Un pack basique avec 5 cartes. Idéal pour débuter !',
                'price' => 50,
                'money_price' => 0, // Gratuit avec les packs gratuits ou coins uniquement
                'card_count' => 5,
                'image' => 'bronze-pack.png',
                'rarity_boosts' => [
                    'common' => 70,
                    'uncommon' => 20,
                    'rare' => 7,
                    'epic' => 2.5,
                    'legendary' => 0.5,
                ],
            ],
            [
                'name' => 'Pack Argent',
                'slug' => 'silver',
                'description' => 'Un pack amélioré avec 9 cartes et de meilleures chances de raretés !',
                'price' => 100,
                'money_price' => 10, // ✅ 10 money
                'card_count' => 9,
                'image' => 'silver-pack.png',
                'rarity_boosts' => [
                    'common' => 50,
                    'uncommon' => 30,
                    'rare' => 13,
                    'epic' => 5,
                    'legendary' => 2,
                ],
            ],
            [
                'name' => 'Pack Or',
                'slug' => 'gold',
                'description' => 'Le pack premium ! 12 cartes avec des chances exceptionnelles de cartes rares !',
                'price' => 200,
                'money_price' => 20, // ✅ 20 money
                'card_count' => 12,
                'image' => 'gold-pack.png',
                'rarity_boosts' => [
                    'common' => 30,
                    'uncommon' => 35,
                    'rare' => 20,
                    'epic' => 10,
                    'legendary' => 5,
                ],
            ],
            [
                'name' => 'Pack Diamant',
                'slug' => 'diamond',
                'description' => 'Le pack ultime ! 15 cartes avec une carte Epic ou Legendary garantie !',
                'price' => 500,
                'money_price' => 50, // ✅ 50 money
                'card_count' => 15,
                'image' => 'diamond-pack.png',
                'rarity_boosts' => [
                    'common' => 20,
                    'uncommon' => 30,
                    'rare' => 25,
                    'epic' => 15,
                    'legendary' => 10,
                ],
            ],
        ];

        foreach ($packs as $pack) {
            Pack::create($pack);
        }
    }
}
