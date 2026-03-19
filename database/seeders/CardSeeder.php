<?php

namespace Database\Seeders;

use App\Models\Card;
use App\Models\ClubTeam;
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
        $clubs = ClubTeam::where('is_main_club', true)->get();
        if ($clubs->count() < 2) {
            $this->command->warn('Pas assez de clubs principaux pour générer les cartes.');
            return;
        }

        $counts = [$clubs[0]->id => 50, $clubs[1]->id => 75];

        foreach ($counts as $clubId => $qty) {
            $this->seedForClub($clubId, $qty);
        }

        $this->command->info('Cartes créées et associées aux clubs.');
    }

    private function seedForClub(int $clubId, int $count): void
    {
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

        for ($i = 0; $i < $count; $i++) {
            $first = $firstNames[$i % count($firstNames)];
            $last = $lastNames[intdiv($i, count($firstNames)) % count($lastNames)];
            $position = $positions[$i % count($positions)];
            $rarity = $this->pickRarity($rarityWeights);
            $number = ($position === 'Entraineur') ? null : (($i % 98) + 1);
            $randomImage = $this->playerImages[array_rand($this->playerImages)];

            Card::create([
                'name' => "$first $last",
                'positions_id' => $this->positionIds[$this->normalizePosition($position)],
                'rarities_id' => $this->rarityIds[$rarity],
                'number' => $number,
                'attack' => rand(25, 95),
                'defense' => rand(25, 95),
                'speed' => rand(25, 95),
                'stamina' => rand(25, 95),
                'image' => $randomImage,
                'club_team_id' => $clubId,
            ]);
        }
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
}
