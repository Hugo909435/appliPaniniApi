<?php

namespace Database\Seeders;

use App\Models\Card;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CardSeeder extends Seeder
{
    private array $playerImages = [
        '/assets/players/player1.png',
        '/assets/players/player2.png',
        '/assets/players/player3.png',
    ];

    private array $positionIds = [];
    private array $rarityIds = [];

    public function run(): void
    {
        $this->loadIds();

        $firstNames = [
            'Lucas', 'Hugo', 'Maxime', 'Alexandre', 'Julien', 'Nicolas', 'Anthony', 'Kevin', 'David', 'Pierre',
            'Thomas', 'Antoine', 'Loic', 'Florian', 'Mickael', 'Sebastien', 'Gregory', 'Mathieu', 'Damien', 'Christophe',
        ];
        $lastNames = [
            'Martin', 'Petit', 'Robert', 'Richard', 'Durand', 'Leroy', 'Moreau', 'Simon', 'Laurent', 'Bernard',
            'Dupont', 'Roussel', 'Marchand', 'Millet', 'Fournier', 'Renaud', 'Giraud', 'Lemoine', 'Caron', 'Bonnet',
        ];

        $positions = ['Gardien', 'Defenseur', 'Milieu', 'Attaquant'];
        $rarityWeights = [
            'common' => 60,
            'uncommon' => 25,
            'rare' => 10,
            'epic' => 4,
            'legendary' => 1,
        ];

        $players = [];
        $total = 90;
        for ($i = 0; $i < $total; $i++) {
            $first = $firstNames[$i % count($firstNames)];
            $last = $lastNames[intdiv($i, count($firstNames)) % count($lastNames)];
            $position = $positions[$i % count($positions)];
            $rarity = $this->pickRarity($rarityWeights);
            $number = ($position === 'Entraineur') ? null : (($i % 98) + 1);

            $players[] = [
                'name' => "$first $last",
                'position' => $position,
                'rarity' => $rarity,
                'number' => $number,
                'attack' => rand(25, 95),
                'defense' => rand(25, 95),
                'speed' => rand(25, 95),
                'stamina' => rand(25, 95),
            ];
        }

        foreach ($players as $player) {
            $randomImage = $this->playerImages[array_rand($this->playerImages)];

            Card::create([
                'name' => $player['name'],
                'positions_id' => $this->positionIds[$this->normalizePosition($player['position'])],
                'rarities_id' => $this->rarityIds[$player['rarity']],
                'number' => $player['number'],
                'attack' => $player['attack'],
                'defense' => $player['defense'],
                'speed' => $player['speed'],
                'stamina' => $player['stamina'],
                'image' => $randomImage,
            ]);
        }

        foreach ($this->getClubIconCards() as $player) {
            $randomImage = $this->playerImages[array_rand($this->playerImages)];

            Card::create([
                'name' => $player['name'],
                'positions_id' => $this->positionIds[$this->normalizePosition($player['position'])],
                'rarities_id' => $this->rarityIds[$player['rarity']],
                'number' => $player['number'],
                'attack' => $player['attack'],
                'defense' => $player['defense'],
                'speed' => $player['speed'],
                'stamina' => $player['stamina'],
                'image' => $randomImage,
            ]);
        }

        $exclusiveIds = Card::whereHas('rarity', function ($query) {
            $query->where('slug', 'legendary');
        })->inRandomOrder()->take(3)->pluck('id');

        if ($exclusiveIds->isNotEmpty()) {
            Card::whereIn('id', $exclusiveIds)->update(['is_exclusive' => true]);
        }

        $this->command->info('Cartes créées avec succès.');
    }

    private function loadIds(): void
    {
        $positions = DB::table('positions')->get();
        foreach ($positions as $position) {
            $this->positionIds[$position->name] = $position->id;
        }

        $rarities = DB::table('rarities')->get();
        foreach ($rarities as $rarity) {
            $this->rarityIds[$rarity->slug] = $rarity->id;
        }
    }

    private function normalizePosition(string $position): string
    {
        $def = "D\u{00E9}fenseur";
        $ent = "Entra\u{00EE}neur";
        $map = [
            'Defenseur' => $def,
            'Entraineur' => $ent,
            'DÃ©fenseur' => $def,
            'D�fenseur' => $def,
            'Dï¿½fenseur' => $def,
            'EntraÃ®neur' => $ent,
            'Entra�neur' => $ent,
            'Entraï¿½neur' => $ent,
        ];

        return $map[$position] ?? $position;
    }

    private function pickRarity(array $weights): string
    {
        $total = array_sum($weights);
        $rand = rand(1, $total);
        $current = 0;
        foreach ($weights as $rarity => $weight) {
            $current += $weight;
            if ($rand <= $current) return $rarity;
        }
        return 'common';
    }

    private function getClubIconCards(): array
    {
        return [
            [
                'name' => "Ic\u{00F4}ne du club - Capitaine",
                'position' => 'Milieu',
                'rarity' => 'icone',
                'number' => 99,
                'attack' => 92,
                'defense' => 88,
                'speed' => 90,
                'stamina' => 95,
            ],
            [
                'name' => "Ic\u{00F4}ne du club - Buteur",
                'position' => 'Attaquant',
                'rarity' => 'icone',
                'number' => 100,
                'attack' => 96,
                'defense' => 78,
                'speed' => 94,
                'stamina' => 90,
            ],
            [
                'name' => "Ic\u{00F4}ne du club - Mur",
                'position' => "D\u{00E9}fenseur",
                'rarity' => 'icone',
                'number' => 98,
                'attack' => 80,
                'defense' => 96,
                'speed' => 84,
                'stamina' => 93,
            ],
        ];
    }
}