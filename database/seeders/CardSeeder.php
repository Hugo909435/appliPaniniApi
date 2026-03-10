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

    // Cache pour les IDs
    private array $positionIds = [];
    private array $rarityIds = [];

    public function run(): void
    {
        // Charger les IDs
        $this->loadIds();

        $teams = [
            'La Roche VF' => $this->getLaRocheVFPlayers(),
            'Les Herbiers VF' => $this->getLesHerbiersPlayers(),
            'Vendée Fontenay Foot' => $this->getVendeeFontenayPlayers(),
            'Challans FC' => $this->getChallansPlayers(),
            'Les Sables d\'Olonne FC' => $this->getLesSablesPlayers(),
            'Luçon VF' => $this->getLuconPlayers(),
            'Chantonnay FC' => $this->getChantonnayPlayers(),
            'Montaigu Vendée' => $this->getMontaiguPlayers(),
            'Aizenay FC' => $this->getAizenayPlayers(),
            'Saint-Hilaire FC' => $this->getSaintHilairePlayers(),
            'Château d\'Olonne' => $this->getChateauOlonnePlayers(),
            'Mareuil FC' => $this->getmareuilPlayers(),
        ];

        foreach ($teams as $teamName => $players) {
            foreach ($players as $player) {
                $randomImage = $this->playerImages[array_rand($this->playerImages)];

                Card::create([
                    'name' => $player['name'],
                    'positions_id' => $this->positionIds[$player['position']],
                    'rarities_id' => $this->rarityIds[$player['rarity']],
                    'number' => $player['number'],
                    'attack' => $player['attack'],
                    'defense' => $player['defense'],
                    'speed' => $player['speed'],
                    'stamina' => $player['stamina'],
                    'image' => $randomImage,
                ]);
            }
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
        // Charger les IDs des positions
        $positions = DB::table('positions')->get();
        foreach ($positions as $position) {
            $this->positionIds[$position->name] = $position->id;
        }

        // Charger les IDs des raretés (slug)
        $rarities = DB::table('rarities')->get();
        foreach ($rarities as $rarity) {
            $this->rarityIds[$rarity->slug] = $rarity->id;
        }
    }

    protected function getLaRocheVFPlayers(): array
    {
        return [
            // Gardiens
            ['name' => 'Lucas Martin', 'position' => 'Gardien', 'rarity' => 'legendary', 'number' => 1, 'attack' => 25, 'defense' => 92, 'speed' => 45, 'stamina' => 78],
            ['name' => 'Samuel Dubois', 'position' => 'Gardien', 'rarity' => 'uncommon', 'number' => 16, 'attack' => 20, 'defense' => 75, 'speed' => 40, 'stamina' => 72],

            // Défenseurs
            ['name' => 'Thomas Dupont', 'position' => 'Défenseur', 'rarity' => 'rare', 'number' => 2, 'attack' => 35, 'defense' => 82, 'speed' => 68, 'stamina' => 80],
            ['name' => 'Antoine Moreau', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 3, 'attack' => 30, 'defense' => 72, 'speed' => 65, 'stamina' => 75],
            ['name' => 'Pierre Bernard', 'position' => 'Défenseur', 'rarity' => 'uncommon', 'number' => 4, 'attack' => 32, 'defense' => 78, 'speed' => 62, 'stamina' => 82],
            ['name' => 'Hugo Petit', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 5, 'attack' => 28, 'defense' => 70, 'speed' => 60, 'stamina' => 78],
            ['name' => 'Loïc Roussel', 'position' => 'Défenseur', 'rarity' => 'uncommon', 'number' => 12, 'attack' => 33, 'defense' => 76, 'speed' => 64, 'stamina' => 77],
            ['name' => 'Florian Marchand', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 15, 'attack' => 29, 'defense' => 71, 'speed' => 61, 'stamina' => 74],

            // Milieux
            ['name' => 'Maxime Robert', 'position' => 'Milieu', 'rarity' => 'epic', 'number' => 6, 'attack' => 72, 'defense' => 68, 'speed' => 80, 'stamina' => 88],
            ['name' => 'Alexandre Richard', 'position' => 'Milieu', 'rarity' => 'uncommon', 'number' => 7, 'attack' => 65, 'defense' => 55, 'speed' => 75, 'stamina' => 80],
            ['name' => 'Julien Durand', 'position' => 'Milieu', 'rarity' => 'rare', 'number' => 8, 'attack' => 70, 'defense' => 62, 'speed' => 78, 'stamina' => 85],
            ['name' => 'Nicolas Leroy', 'position' => 'Milieu', 'rarity' => 'common', 'number' => 10, 'attack' => 60, 'defense' => 50, 'speed' => 72, 'stamina' => 75],
            ['name' => 'Adrien Leclerc', 'position' => 'Milieu', 'rarity' => 'uncommon', 'number' => 14, 'attack' => 63, 'defense' => 57, 'speed' => 73, 'stamina' => 79],
            ['name' => 'Quentin Morin', 'position' => 'Milieu', 'rarity' => 'common', 'number' => 18, 'attack' => 58, 'defense' => 53, 'speed' => 70, 'stamina' => 76],

            // Attaquants
            ['name' => 'Kevin Simon', 'position' => 'Attaquant', 'rarity' => 'epic', 'number' => 9, 'attack' => 88, 'defense' => 32, 'speed' => 85, 'stamina' => 78],
            ['name' => 'David Laurent', 'position' => 'Attaquant', 'rarity' => 'rare', 'number' => 11, 'attack' => 80, 'defense' => 28, 'speed' => 82, 'stamina' => 72],
            ['name' => 'Ludovic Perrin', 'position' => 'Attaquant', 'rarity' => 'uncommon', 'number' => 13, 'attack' => 74, 'defense' => 30, 'speed' => 79, 'stamina' => 70],
            ['name' => 'Bryan Aubert', 'position' => 'Attaquant', 'rarity' => 'common', 'number' => 17, 'attack' => 68, 'defense' => 26, 'speed' => 76, 'stamina' => 68],

            // Staff
            ['name' => 'Philippe Gautier', 'position' => 'Entraîneur', 'rarity' => 'legendary', 'number' => null, 'attack' => 85, 'defense' => 85, 'speed' => 50, 'stamina' => 90],
        ];
    }

    protected function getLesHerbiersPlayers(): array
    {
        return [
            // Gardiens
            ['name' => 'Mathieu Girard', 'position' => 'Gardien', 'rarity' => 'legendary', 'number' => 1, 'attack' => 22, 'defense' => 90, 'speed' => 42, 'stamina' => 80],
            ['name' => 'Jordan Blanchard', 'position' => 'Gardien', 'rarity' => 'uncommon', 'number' => 16, 'attack' => 18, 'defense' => 73, 'speed' => 38, 'stamina' => 70],

            // Défenseurs
            ['name' => 'Christophe Bonnet', 'position' => 'Défenseur', 'rarity' => 'uncommon', 'number' => 2, 'attack' => 38, 'defense' => 76, 'speed' => 64, 'stamina' => 78],
            ['name' => 'François Dubois', 'position' => 'Défenseur', 'rarity' => 'rare', 'number' => 3, 'attack' => 35, 'defense' => 84, 'speed' => 66, 'stamina' => 82],
            ['name' => 'Sébastien Fournier', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 4, 'attack' => 30, 'defense' => 68, 'speed' => 58, 'stamina' => 76],
            ['name' => 'Romain Mercier', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 5, 'attack' => 32, 'defense' => 70, 'speed' => 62, 'stamina' => 74],
            ['name' => 'Mathis Dupuis', 'position' => 'Défenseur', 'rarity' => 'uncommon', 'number' => 12, 'attack' => 34, 'defense' => 77, 'speed' => 63, 'stamina' => 79],
            ['name' => 'Dylan Renaud', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 15, 'attack' => 31, 'defense' => 69, 'speed' => 59, 'stamina' => 75],

            // Milieux
            ['name' => 'Vincent Blanc', 'position' => 'Milieu', 'rarity' => 'rare', 'number' => 6, 'attack' => 68, 'defense' => 65, 'speed' => 76, 'stamina' => 84],
            ['name' => 'Laurent Guerin', 'position' => 'Milieu', 'rarity' => 'uncommon', 'number' => 7, 'attack' => 62, 'defense' => 58, 'speed' => 74, 'stamina' => 78],
            ['name' => 'Guillaume Faure', 'position' => 'Milieu', 'rarity' => 'epic', 'number' => 8, 'attack' => 75, 'defense' => 60, 'speed' => 82, 'stamina' => 86],
            ['name' => 'Arnaud Rousseau', 'position' => 'Milieu', 'rarity' => 'common', 'number' => 10, 'attack' => 58, 'defense' => 52, 'speed' => 70, 'stamina' => 72],
            ['name' => 'Morgan Denis', 'position' => 'Milieu', 'rarity' => 'uncommon', 'number' => 14, 'attack' => 64, 'defense' => 56, 'speed' => 72, 'stamina' => 77],
            ['name' => 'Kévin Boucher', 'position' => 'Milieu', 'rarity' => 'common', 'number' => 18, 'attack' => 59, 'defense' => 54, 'speed' => 71, 'stamina' => 73],

            // Attaquants
            ['name' => 'Fabien Henry', 'position' => 'Attaquant', 'rarity' => 'rare', 'number' => 9, 'attack' => 82, 'defense' => 30, 'speed' => 80, 'stamina' => 74],
            ['name' => 'Emmanuel Morel', 'position' => 'Attaquant', 'rarity' => 'epic', 'number' => 11, 'attack' => 85, 'defense' => 25, 'speed' => 88, 'stamina' => 76],
            ['name' => 'Damien Giraud', 'position' => 'Attaquant', 'rarity' => 'uncommon', 'number' => 13, 'attack' => 75, 'defense' => 29, 'speed' => 77, 'stamina' => 71],
            ['name' => 'Tom Vasseur', 'position' => 'Attaquant', 'rarity' => 'common', 'number' => 17, 'attack' => 69, 'defense' => 27, 'speed' => 75, 'stamina' => 69],

            // Staff
            ['name' => 'Jean-Marc Perrin', 'position' => 'Entraîneur', 'rarity' => 'legendary', 'number' => null, 'attack' => 82, 'defense' => 88, 'speed' => 48, 'stamina' => 92],
        ];
    }

    protected function getVendeeFontenayPlayers(): array
    {
        return [
            // Gardiens
            ['name' => 'Damien Caron', 'position' => 'Gardien', 'rarity' => 'legendary', 'number' => 1, 'attack' => 20, 'defense' => 88, 'speed' => 40, 'stamina' => 82],
            ['name' => 'Lucas Vidal', 'position' => 'Gardien', 'rarity' => 'uncommon', 'number' => 16, 'attack' => 19, 'defense' => 74, 'speed' => 39, 'stamina' => 71],

            // Défenseurs
            ['name' => 'Cédric Gauthier', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 2, 'attack' => 34, 'defense' => 72, 'speed' => 66, 'stamina' => 76],
            ['name' => 'Yannick Chevalier', 'position' => 'Défenseur', 'rarity' => 'uncommon', 'number' => 3, 'attack' => 36, 'defense' => 78, 'speed' => 68, 'stamina' => 80],
            ['name' => 'Frédéric Lemaire', 'position' => 'Défenseur', 'rarity' => 'rare', 'number' => 4, 'attack' => 38, 'defense' => 85, 'speed' => 64, 'stamina' => 84],
            ['name' => 'Jérôme Garcia', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 5, 'attack' => 28, 'defense' => 74, 'speed' => 60, 'stamina' => 78],
            ['name' => 'Benjamin Colin', 'position' => 'Défenseur', 'rarity' => 'uncommon', 'number' => 12, 'attack' => 35, 'defense' => 79, 'speed' => 65, 'stamina' => 81],
            ['name' => 'Alexis Boyer', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 15, 'attack' => 30, 'defense' => 73, 'speed' => 61, 'stamina' => 77],

            // Milieux
            ['name' => 'Olivier Roux', 'position' => 'Milieu', 'rarity' => 'uncommon', 'number' => 6, 'attack' => 66, 'defense' => 64, 'speed' => 74, 'stamina' => 82],
            ['name' => 'Stéphane Fontaine', 'position' => 'Milieu', 'rarity' => 'rare', 'number' => 7, 'attack' => 72, 'defense' => 58, 'speed' => 78, 'stamina' => 80],
            ['name' => 'Mickaël Barbier', 'position' => 'Milieu', 'rarity' => 'common', 'number' => 8, 'attack' => 58, 'defense' => 54, 'speed' => 68, 'stamina' => 74],
            ['name' => 'Benoit Arnaud', 'position' => 'Milieu', 'rarity' => 'epic', 'number' => 10, 'attack' => 78, 'defense' => 56, 'speed' => 84, 'stamina' => 86],
            ['name' => 'Raphaël Lemoine', 'position' => 'Milieu', 'rarity' => 'uncommon', 'number' => 14, 'attack' => 65, 'defense' => 59, 'speed' => 73, 'stamina' => 78],
            ['name' => 'Anthony Bertin', 'position' => 'Milieu', 'rarity' => 'common', 'number' => 18, 'attack' => 60, 'defense' => 55, 'speed' => 69, 'stamina' => 75],

            // Attaquants
            ['name' => 'Sylvain Marchand', 'position' => 'Attaquant', 'rarity' => 'epic', 'number' => 9, 'attack' => 86, 'defense' => 28, 'speed' => 84, 'stamina' => 76],
            ['name' => 'Thierry Picard', 'position' => 'Attaquant', 'rarity' => 'uncommon', 'number' => 11, 'attack' => 76, 'defense' => 32, 'speed' => 78, 'stamina' => 72],
            ['name' => 'Sébastien Masson', 'position' => 'Attaquant', 'rarity' => 'rare', 'number' => 13, 'attack' => 81, 'defense' => 31, 'speed' => 81, 'stamina' => 73],
            ['name' => 'Grégory Brun', 'position' => 'Attaquant', 'rarity' => 'common', 'number' => 17, 'attack' => 70, 'defense' => 28, 'speed' => 77, 'stamina' => 70],

            // Staff
            ['name' => 'Marc Lefebvre', 'position' => 'Entraîneur', 'rarity' => 'legendary', 'number' => null, 'attack' => 80, 'defense' => 84, 'speed' => 52, 'stamina' => 88],
        ];
    }

    protected function getChallansPlayers(): array
    {
        return [
            // Gardiens
            ['name' => 'Julien Mercier', 'position' => 'Gardien', 'rarity' => 'legendary', 'number' => 1, 'attack' => 24, 'defense' => 91, 'speed' => 44, 'stamina' => 79],
            ['name' => 'Maxence Hubert', 'position' => 'Gardien', 'rarity' => 'uncommon', 'number' => 16, 'attack' => 21, 'defense' => 76, 'speed' => 41, 'stamina' => 73],

            // Défenseurs
            ['name' => 'Éric Clement', 'position' => 'Défenseur', 'rarity' => 'rare', 'number' => 2, 'attack' => 36, 'defense' => 83, 'speed' => 67, 'stamina' => 81],
            ['name' => 'Fabrice Lambert', 'position' => 'Défenseur', 'rarity' => 'uncommon', 'number' => 3, 'attack' => 33, 'defense' => 75, 'speed' => 63, 'stamina' => 77],
            ['name' => 'Didier Guillot', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 4, 'attack' => 29, 'defense' => 71, 'speed' => 61, 'stamina' => 76],
            ['name' => 'Patrice Meyer', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 5, 'attack' => 27, 'defense' => 69, 'speed' => 59, 'stamina' => 75],
            ['name' => 'Jérémy Maillard', 'position' => 'Défenseur', 'rarity' => 'uncommon', 'number' => 12, 'attack' => 34, 'defense' => 77, 'speed' => 64, 'stamina' => 78],
            ['name' => 'Valentin Pons', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 15, 'attack' => 28, 'defense' => 70, 'speed' => 60, 'stamina' => 74],

            // Milieux
            ['name' => 'Lionel Martinez', 'position' => 'Milieu', 'rarity' => 'epic', 'number' => 6, 'attack' => 74, 'defense' => 67, 'speed' => 81, 'stamina' => 87],
            ['name' => 'Pascal Renard', 'position' => 'Milieu', 'rarity' => 'rare', 'number' => 7, 'attack' => 69, 'defense' => 63, 'speed' => 77, 'stamina' => 83],
            ['name' => 'Aurélien Millet', 'position' => 'Milieu', 'rarity' => 'uncommon', 'number' => 8, 'attack' => 64, 'defense' => 57, 'speed' => 74, 'stamina' => 79],
            ['name' => 'Christophe Lopez', 'position' => 'Milieu', 'rarity' => 'common', 'number' => 10, 'attack' => 59, 'defense' => 51, 'speed' => 71, 'stamina' => 74],
            ['name' => 'Yoann Mathieu', 'position' => 'Milieu', 'rarity' => 'uncommon', 'number' => 14, 'attack' => 62, 'defense' => 56, 'speed' => 72, 'stamina' => 78],
            ['name' => 'Florent Adam', 'position' => 'Milieu', 'rarity' => 'common', 'number' => 18, 'attack' => 57, 'defense' => 52, 'speed' => 70, 'stamina' => 75],

            // Attaquants
            ['name' => 'Grégory Sanchez', 'position' => 'Attaquant', 'rarity' => 'epic', 'number' => 9, 'attack' => 87, 'defense' => 31, 'speed' => 86, 'stamina' => 77],
            ['name' => 'Franck Benoit', 'position' => 'Attaquant', 'rarity' => 'rare', 'number' => 11, 'attack' => 79, 'defense' => 29, 'speed' => 81, 'stamina' => 73],
            ['name' => 'Jonathan Petit', 'position' => 'Attaquant', 'rarity' => 'uncommon', 'number' => 13, 'attack' => 73, 'defense' => 28, 'speed' => 78, 'stamina' => 71],
            ['name' => 'Mickael Roy', 'position' => 'Attaquant', 'rarity' => 'common', 'number' => 17, 'attack' => 67, 'defense' => 26, 'speed' => 74, 'stamina' => 68],

            // Staff
            ['name' => 'Daniel Bertrand', 'position' => 'Entraîneur', 'rarity' => 'legendary', 'number' => null, 'attack' => 83, 'defense' => 86, 'speed' => 49, 'stamina' => 89],
        ];
    }

    protected function getLesSablesPlayers(): array
    {
        return [
            // Gardiens
            ['name' => 'Anthony Legrand', 'position' => 'Gardien', 'rarity' => 'legendary', 'number' => 1, 'attack' => 23, 'defense' => 89, 'speed' => 43, 'stamina' => 81],
            ['name' => 'Fabien Moulin', 'position' => 'Gardien', 'rarity' => 'uncommon', 'number' => 16, 'attack' => 20, 'defense' => 75, 'speed' => 40, 'stamina' => 72],

            // Défenseurs
            ['name' => 'Philippe Lacroix', 'position' => 'Défenseur', 'rarity' => 'uncommon', 'number' => 2, 'attack' => 37, 'defense' => 77, 'speed' => 65, 'stamina' => 79],
            ['name' => 'Alain Leroux', 'position' => 'Défenseur', 'rarity' => 'rare', 'number' => 3, 'attack' => 36, 'defense' => 84, 'speed' => 67, 'stamina' => 83],
            ['name' => 'Christian Bouchet', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 4, 'attack' => 31, 'defense' => 73, 'speed' => 62, 'stamina' => 77],
            ['name' => 'Ludovic Philippe', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 5, 'attack' => 29, 'defense' => 71, 'speed' => 60, 'stamina' => 76],
            ['name' => 'Xavier Maury', 'position' => 'Défenseur', 'rarity' => 'uncommon', 'number' => 12, 'attack' => 35, 'defense' => 78, 'speed' => 66, 'stamina' => 80],
            ['name' => 'Nicolas Renault', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 15, 'attack' => 30, 'defense' => 72, 'speed' => 61, 'stamina' => 75],

            // Milieux
            ['name' => 'Dominique Fleury', 'position' => 'Milieu', 'rarity' => 'rare', 'number' => 6, 'attack' => 71, 'defense' => 66, 'speed' => 79, 'stamina' => 85],
            ['name' => 'Sylvain André', 'position' => 'Milieu', 'rarity' => 'epic', 'number' => 7, 'attack' => 76, 'defense' => 61, 'speed' => 83, 'stamina' => 87],
            ['name' => 'Rémy Delaunay', 'position' => 'Milieu', 'rarity' => 'uncommon', 'number' => 8, 'attack' => 63, 'defense' => 58, 'speed' => 75, 'stamina' => 80],
            ['name' => 'Bruno Girard', 'position' => 'Milieu', 'rarity' => 'common', 'number' => 10, 'attack' => 61, 'defense' => 53, 'speed' => 73, 'stamina' => 76],
            ['name' => 'Cédric Lefevre', 'position' => 'Milieu', 'rarity' => 'uncommon', 'number' => 14, 'attack' => 66, 'defense' => 60, 'speed' => 76, 'stamina' => 81],
            ['name' => 'Jérôme Tessier', 'position' => 'Milieu', 'rarity' => 'common', 'number' => 18, 'attack' => 59, 'defense' => 54, 'speed' => 71, 'stamina' => 74],

            // Attaquants
            ['name' => 'Sébastien Voisin', 'position' => 'Attaquant', 'rarity' => 'rare', 'number' => 9, 'attack' => 83, 'defense' => 30, 'speed' => 82, 'stamina' => 75],
            ['name' => 'Hervé Guichard', 'position' => 'Attaquant', 'rarity' => 'epic', 'number' => 11, 'attack' => 84, 'defense' => 27, 'speed' => 87, 'stamina' => 75],
            ['name' => 'Yannick Colin', 'position' => 'Attaquant', 'rarity' => 'uncommon', 'number' => 13, 'attack' => 72, 'defense' => 29, 'speed' => 79, 'stamina' => 72],
            ['name' => 'Patrick Dumas', 'position' => 'Attaquant', 'rarity' => 'common', 'number' => 17, 'attack' => 68, 'defense' => 27, 'speed' => 76, 'stamina' => 69],

            // Staff
            ['name' => 'Pierre Cousin', 'position' => 'Entraîneur', 'rarity' => 'legendary', 'number' => null, 'attack' => 84, 'defense' => 87, 'speed' => 51, 'stamina' => 91],
        ];
    }

    protected function getLuconPlayers(): array
    {
        return [
            // Gardiens
            ['name' => 'Romain Aubert', 'position' => 'Gardien', 'rarity' => 'legendary', 'number' => 1, 'attack' => 21, 'defense' => 87, 'speed' => 41, 'stamina' => 80],
            ['name' => 'Thomas Giraud', 'position' => 'Gardien', 'rarity' => 'uncommon', 'number' => 16, 'attack' => 19, 'defense' => 72, 'speed' => 38, 'stamina' => 71],

            // Défenseurs
            ['name' => 'Laurent Dupuy', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 2, 'attack' => 32, 'defense' => 74, 'speed' => 64, 'stamina' => 78],
            ['name' => 'Georges Schmitt', 'position' => 'Défenseur', 'rarity' => 'uncommon', 'number' => 3, 'attack' => 34, 'defense' => 76, 'speed' => 65, 'stamina' => 79],
            ['name' => 'Claude Schneider', 'position' => 'Défenseur', 'rarity' => 'rare', 'number' => 4, 'attack' => 37, 'defense' => 82, 'speed' => 66, 'stamina' => 82],
            ['name' => 'André Perez', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 5, 'attack' => 30, 'defense' => 70, 'speed' => 61, 'stamina' => 77],
            ['name' => 'Julien Olivier', 'position' => 'Défenseur', 'rarity' => 'uncommon', 'number' => 12, 'attack' => 33, 'defense' => 75, 'speed' => 63, 'stamina' => 78],
            ['name' => 'Simon Rolland', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 15, 'attack' => 29, 'defense' => 68, 'speed' => 60, 'stamina' => 75],

            // Milieux
            ['name' => 'Damien Lebrun', 'position' => 'Milieu', 'rarity' => 'epic', 'number' => 6, 'attack' => 73, 'defense' => 69, 'speed' => 80, 'stamina' => 86],
            ['name' => 'Bertrand Reynaud', 'position' => 'Milieu', 'rarity' => 'uncommon', 'number' => 7, 'attack' => 61, 'defense' => 56, 'speed' => 73, 'stamina' => 77],
            ['name' => 'Guillaume Charpentier', 'position' => 'Milieu', 'rarity' => 'rare', 'number' => 8, 'attack' => 68, 'defense' => 64, 'speed' => 77, 'stamina' => 82],
            ['name' => 'Éric Leclerc', 'position' => 'Milieu', 'rarity' => 'common', 'number' => 10, 'attack' => 60, 'defense' => 52, 'speed' => 72, 'stamina' => 75],
            ['name' => 'Régis Jacquet', 'position' => 'Milieu', 'rarity' => 'uncommon', 'number' => 14, 'attack' => 63, 'defense' => 57, 'speed' => 74, 'stamina' => 79],
            ['name' => 'Arnaud Brunet', 'position' => 'Milieu', 'rarity' => 'common', 'number' => 18, 'attack' => 58, 'defense' => 53, 'speed' => 70, 'stamina' => 74],

            // Attaquants
            ['name' => 'Vincent Fabre', 'position' => 'Attaquant', 'rarity' => 'epic', 'number' => 9, 'attack' => 85, 'defense' => 29, 'speed' => 85, 'stamina' => 76],
            ['name' => 'Thierry Menard', 'position' => 'Attaquant', 'rarity' => 'rare', 'number' => 11, 'attack' => 78, 'defense' => 28, 'speed' => 80, 'stamina' => 72],
            ['name' => 'Michaël Baron', 'position' => 'Attaquant', 'rarity' => 'uncommon', 'number' => 13, 'attack' => 71, 'defense' => 30, 'speed' => 77, 'stamina' => 70],
            ['name' => 'Frédéric Julien', 'position' => 'Attaquant', 'rarity' => 'common', 'number' => 17, 'attack' => 66, 'defense' => 25, 'speed' => 73, 'stamina' => 67],

            // Staff
            ['name' => 'François Humbert', 'position' => 'Entraîneur', 'rarity' => 'legendary', 'number' => null, 'attack' => 81, 'defense' => 83, 'speed' => 50, 'stamina' => 87],
        ];
    }

    protected function getChantonnayPlayers(): array
    {
        return [
            // Gardiens
            ['name' => 'Morgan Poirier', 'position' => 'Gardien', 'rarity' => 'legendary', 'number' => 1, 'attack' => 24, 'defense' => 90, 'speed' => 44, 'stamina' => 79],
            ['name' => 'Florian Michaud', 'position' => 'Gardien', 'rarity' => 'uncommon', 'number' => 16, 'attack' => 21, 'defense' => 74, 'speed' => 41, 'stamina' => 72],

            // Défenseurs
            ['name' => 'Stéphane Gillet', 'position' => 'Défenseur', 'rarity' => 'rare', 'number' => 2, 'attack' => 37, 'defense' => 81, 'speed' => 68, 'stamina' => 80],
            ['name' => 'Gilles Morin', 'position' => 'Défenseur', 'rarity' => 'uncommon', 'number' => 3, 'attack' => 35, 'defense' => 78, 'speed' => 66, 'stamina' => 79],
            ['name' => 'Régis Hamon', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 4, 'attack' => 31, 'defense' => 72, 'speed' => 62, 'stamina' => 76],
            ['name' => 'Marc Gilbert', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 5, 'attack' => 28, 'defense' => 69, 'speed' => 59, 'stamina' => 74],
            ['name' => 'Baptiste Corre', 'position' => 'Défenseur', 'rarity' => 'uncommon', 'number' => 12, 'attack' => 33, 'defense' => 76, 'speed' => 64, 'stamina' => 78],
            ['name' => 'Matthieu Cousin', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 15, 'attack' => 29, 'defense' => 70, 'speed' => 60, 'stamina' => 75],

            // Milieux
            ['name' => 'Fabrice Guillou', 'position' => 'Milieu', 'rarity' => 'rare', 'number' => 6, 'attack' => 70, 'defense' => 65, 'speed' => 78, 'stamina' => 84],
            ['name' => 'Patrick Lamy', 'position' => 'Milieu', 'rarity' => 'epic', 'number' => 7, 'attack' => 77, 'defense' => 62, 'speed' => 84, 'stamina' => 88],
            ['name' => 'Loïc Foucher', 'position' => 'Milieu', 'rarity' => 'uncommon', 'number' => 8, 'attack' => 64, 'defense' => 59, 'speed' => 75, 'stamina' => 80],
            ['name' => 'Yves Roche', 'position' => 'Milieu', 'rarity' => 'common', 'number' => 10, 'attack' => 59, 'defense' => 52, 'speed' => 71, 'stamina' => 73],
            ['name' => 'Christophe Neveu', 'position' => 'Milieu', 'rarity' => 'uncommon', 'number' => 14, 'attack' => 62, 'defense' => 58, 'speed' => 73, 'stamina' => 77],
            ['name' => 'Daniel Martel', 'position' => 'Milieu', 'rarity' => 'common', 'number' => 18, 'attack' => 57, 'defense' => 51, 'speed' => 69, 'stamina' => 72],

            // Attaquants
            ['name' => 'Alain Leduc', 'position' => 'Attaquant', 'rarity' => 'epic', 'number' => 9, 'attack' => 86, 'defense' => 30, 'speed' => 83, 'stamina' => 75],
            ['name' => 'Jean-Pierre Collet', 'position' => 'Attaquant', 'rarity' => 'rare', 'number' => 11, 'attack' => 80, 'defense' => 29, 'speed' => 79, 'stamina' => 71],
            ['name' => 'René Boutin', 'position' => 'Attaquant', 'rarity' => 'uncommon', 'number' => 13, 'attack' => 74, 'defense' => 31, 'speed' => 78, 'stamina' => 72],
            ['name' => 'Bernard Vaillant', 'position' => 'Attaquant', 'rarity' => 'common', 'number' => 17, 'attack' => 69, 'defense' => 28, 'speed' => 75, 'stamina' => 69],

            // Staff
            ['name' => 'Alain Besnard', 'position' => 'Entraîneur', 'rarity' => 'legendary', 'number' => null, 'attack' => 82, 'defense' => 85, 'speed' => 49, 'stamina' => 88],
        ];
    }

    protected function getMontaiguPlayers(): array
    {
        return [
            // Gardiens
            ['name' => 'Baptiste Leconte', 'position' => 'Gardien', 'rarity' => 'legendary', 'number' => 1, 'attack' => 23, 'defense' => 91, 'speed' => 43, 'stamina' => 81],
            ['name' => 'Kévin Tanguy', 'position' => 'Gardien', 'rarity' => 'uncommon', 'number' => 16, 'attack' => 20, 'defense' => 73, 'speed' => 39, 'stamina' => 70],

            // Défenseurs
            ['name' => 'William Berger', 'position' => 'Défenseur', 'rarity' => 'uncommon', 'number' => 2, 'attack' => 36, 'defense' => 79, 'speed' => 67, 'stamina' => 80],
            ['name' => 'Joël Girard', 'position' => 'Défenseur', 'rarity' => 'rare', 'number' => 3, 'attack' => 38, 'defense' => 83, 'speed' => 68, 'stamina' => 82],
            ['name' => 'Maxime Charrier', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 4, 'attack' => 30, 'defense' => 71, 'speed' => 61, 'stamina' => 76],
            ['name' => 'Alexandre Aubry', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 5, 'attack' => 27, 'defense' => 68, 'speed' => 58, 'stamina' => 73],
            ['name' => 'Fabien Boulay', 'position' => 'Défenseur', 'rarity' => 'uncommon', 'number' => 12, 'attack' => 34, 'defense' => 77, 'speed' => 65, 'stamina' => 79],
            ['name' => 'Julien Chartier', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 15, 'attack' => 28, 'defense' => 69, 'speed' => 59, 'stamina' => 74],

            // Milieux
            ['name' => 'Raphaël Paris', 'position' => 'Milieu', 'rarity' => 'epic', 'number' => 6, 'attack' => 75, 'defense' => 68, 'speed' => 82, 'stamina' => 89],
            ['name' => 'Sébastien Monnier', 'position' => 'Milieu', 'rarity' => 'rare', 'number' => 7, 'attack' => 71, 'defense' => 64, 'speed' => 79, 'stamina' => 85],
            ['name' => 'Anthony Coulon', 'position' => 'Milieu', 'rarity' => 'uncommon', 'number' => 8, 'attack' => 65, 'defense' => 60, 'speed' => 76, 'stamina' => 81],
            ['name' => 'David Etienne', 'position' => 'Milieu', 'rarity' => 'common', 'number' => 10, 'attack' => 61, 'defense' => 54, 'speed' => 74, 'stamina' => 77],
            ['name' => 'Éric Guyon', 'position' => 'Milieu', 'rarity' => 'uncommon', 'number' => 14, 'attack' => 66, 'defense' => 61, 'speed' => 77, 'stamina' => 82],
            ['name' => 'Frédéric Ledoux', 'position' => 'Milieu', 'rarity' => 'common', 'number' => 18, 'attack' => 60, 'defense' => 55, 'speed' => 72, 'stamina' => 76],

            // Attaquants
            ['name' => 'Olivier Leroux', 'position' => 'Attaquant', 'rarity' => 'rare', 'number' => 9, 'attack' => 84, 'defense' => 32, 'speed' => 83, 'stamina' => 76],
            ['name' => 'Jérôme Bouvier', 'position' => 'Attaquant', 'rarity' => 'epic', 'number' => 11, 'attack' => 88, 'defense' => 28, 'speed' => 89, 'stamina' => 78],
            ['name' => 'Michaël Lecomte', 'position' => 'Attaquant', 'rarity' => 'uncommon', 'number' => 13, 'attack' => 75, 'defense' => 30, 'speed' => 80, 'stamina' => 73],
            ['name' => 'Laurent Jourdain', 'position' => 'Attaquant', 'rarity' => 'common', 'number' => 17, 'attack' => 70, 'defense' => 29, 'speed' => 77, 'stamina' => 70],

            // Staff
            ['name' => 'Michel Gros', 'position' => 'Entraîneur', 'rarity' => 'legendary', 'number' => null, 'attack' => 86, 'defense' => 89, 'speed' => 53, 'stamina' => 93],
        ];
    }

    protected function getAizenayPlayers(): array
    {
        return [
            // Gardiens
            ['name' => 'Ludovic Marty', 'position' => 'Gardien', 'rarity' => 'legendary', 'number' => 1, 'attack' => 22, 'defense' => 88, 'speed' => 42, 'stamina' => 80],
            ['name' => 'Dylan Perrot', 'position' => 'Gardien', 'rarity' => 'uncommon', 'number' => 16, 'attack' => 19, 'defense' => 71, 'speed' => 37, 'stamina' => 69],

            // Défenseurs
            ['name' => 'Samuel Bonnet', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 2, 'attack' => 33, 'defense' => 73, 'speed' => 65, 'stamina' => 77],
            ['name' => 'Michaël Picard', 'position' => 'Défenseur', 'rarity' => 'uncommon', 'number' => 3, 'attack' => 35, 'defense' => 77, 'speed' => 67, 'stamina' => 79],
            ['name' => 'Jérôme Jacquot', 'position' => 'Défenseur', 'rarity' => 'rare', 'number' => 4, 'attack' => 36, 'defense' => 80, 'speed' => 65, 'stamina' => 81],
            ['name' => 'Franck Joubert', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 5, 'attack' => 29, 'defense' => 70, 'speed' => 60, 'stamina' => 75],
            ['name' => 'Nicolas Baron', 'position' => 'Défenseur', 'rarity' => 'uncommon', 'number' => 12, 'attack' => 32, 'defense' => 74, 'speed' => 62, 'stamina' => 77],
            ['name' => 'Thomas Dubois', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 15, 'attack' => 27, 'defense' => 67, 'speed' => 57, 'stamina' => 72],

            // Milieux
            ['name' => 'Benoit Rivière', 'position' => 'Milieu', 'rarity' => 'uncommon', 'number' => 6, 'attack' => 63, 'defense' => 61, 'speed' => 74, 'stamina' => 80],
            ['name' => 'Kévin Marchal', 'position' => 'Milieu', 'rarity' => 'rare', 'number' => 7, 'attack' => 69, 'defense' => 63, 'speed' => 78, 'stamina' => 83],
            ['name' => 'Damien Lemaire', 'position' => 'Milieu', 'rarity' => 'epic', 'number' => 8, 'attack' => 74, 'defense' => 66, 'speed' => 81, 'stamina' => 85],
            ['name' => 'Jonathan Leroy', 'position' => 'Milieu', 'rarity' => 'common', 'number' => 10, 'attack' => 58, 'defense' => 50, 'speed' => 70, 'stamina' => 73],
            ['name' => 'Pierre Guyot', 'position' => 'Milieu', 'rarity' => 'uncommon', 'number' => 14, 'attack' => 61, 'defense' => 55, 'speed' => 72, 'stamina' => 76],
            ['name' => 'Guillaume Roy', 'position' => 'Milieu', 'rarity' => 'common', 'number' => 18, 'attack' => 56, 'defense' => 51, 'speed' => 68, 'stamina' => 71],

            // Attaquants
            ['name' => 'Fabrice Morvan', 'position' => 'Attaquant', 'rarity' => 'epic', 'number' => 9, 'attack' => 87, 'defense' => 31, 'speed' => 86, 'stamina' => 77],
            ['name' => 'Vincent Weber', 'position' => 'Attaquant', 'rarity' => 'rare', 'number' => 11, 'attack' => 81, 'defense' => 30, 'speed' => 82, 'stamina' => 74],
            ['name' => 'Yannick Marechal', 'position' => 'Attaquant', 'rarity' => 'uncommon', 'number' => 13, 'attack' => 73, 'defense' => 29, 'speed' => 78, 'stamina' => 71],
            ['name' => 'Stéphane Devaux', 'position' => 'Attaquant', 'rarity' => 'common', 'number' => 17, 'attack' => 67, 'defense' => 26, 'speed' => 74, 'stamina' => 68],

            // Staff
            ['name' => 'Christophe Riou', 'position' => 'Entraîneur', 'rarity' => 'legendary', 'number' => null, 'attack' => 80, 'defense' => 82, 'speed' => 48, 'stamina' => 86],
        ];
    }

    protected function getSaintHilairePlayers(): array
    {
        return [
            // Gardiens
            ['name' => 'Maxime Guillot', 'position' => 'Gardien', 'rarity' => 'legendary', 'number' => 1, 'attack' => 24, 'defense' => 89, 'speed' => 44, 'stamina' => 78],
            ['name' => 'Julien Boutin', 'position' => 'Gardien', 'rarity' => 'uncommon', 'number' => 16, 'attack' => 21, 'defense' => 74, 'speed' => 40, 'stamina' => 71],

            // Défenseurs
            ['name' => 'Florian Menard', 'position' => 'Défenseur', 'rarity' => 'rare', 'number' => 2, 'attack' => 37, 'defense' => 82, 'speed' => 69, 'stamina' => 81],
            ['name' => 'Adrien Berthier', 'position' => 'Défenseur', 'rarity' => 'uncommon', 'number' => 3, 'attack' => 34, 'defense' => 76, 'speed' => 64, 'stamina' => 78],
            ['name' => 'Quentin Dupré', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 4, 'attack' => 29, 'defense' => 70, 'speed' => 60, 'stamina' => 75],
            ['name' => 'Bryan Perrier', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 5, 'attack' => 26, 'defense' => 67, 'speed' => 57, 'stamina' => 72],
            ['name' => 'Tom Valentin', 'position' => 'Défenseur', 'rarity' => 'uncommon', 'number' => 12, 'attack' => 31, 'defense' => 73, 'speed' => 61, 'stamina' => 76],
            ['name' => 'Lucas Camus', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 15, 'attack' => 27, 'defense' => 68, 'speed' => 58, 'stamina' => 73],

            // Milieux
            ['name' => 'Morgan Joubert', 'position' => 'Milieu', 'rarity' => 'epic', 'number' => 6, 'attack' => 76, 'defense' => 70, 'speed' => 83, 'stamina' => 90],
            ['name' => 'Dylan Gaillard', 'position' => 'Milieu', 'rarity' => 'rare', 'number' => 7, 'attack' => 70, 'defense' => 66, 'speed' => 80, 'stamina' => 86],
            ['name' => 'Valentin Leduc', 'position' => 'Milieu', 'rarity' => 'uncommon', 'number' => 8, 'attack' => 66, 'defense' => 62, 'speed' => 77, 'stamina' => 82],
            ['name' => 'Mathis Blanchard', 'position' => 'Milieu', 'rarity' => 'common', 'number' => 10, 'attack' => 62, 'defense' => 56, 'speed' => 75, 'stamina' => 78],
            ['name' => 'Romain Vallet', 'position' => 'Milieu', 'rarity' => 'uncommon', 'number' => 14, 'attack' => 67, 'defense' => 63, 'speed' => 78, 'stamina' => 83],
            ['name' => 'Anthony Guillet', 'position' => 'Milieu', 'rarity' => 'common', 'number' => 18, 'attack' => 61, 'defense' => 57, 'speed' => 73, 'stamina' => 77],

            // Attaquants
            ['name' => 'Kévin Jousse', 'position' => 'Attaquant', 'rarity' => 'rare', 'number' => 9, 'attack' => 82, 'defense' => 33, 'speed' => 84, 'stamina' => 77],
            ['name' => 'Jérémy Cailleau', 'position' => 'Attaquant', 'rarity' => 'epic', 'number' => 11, 'attack' => 85, 'defense' => 29, 'speed' => 88, 'stamina' => 79],
            ['name' => 'Ludovic Pichon', 'position' => 'Attaquant', 'rarity' => 'uncommon', 'number' => 13, 'attack' => 76, 'defense' => 32, 'speed' => 81, 'stamina' => 74],
            ['name' => 'Sébastien Lenoir', 'position' => 'Attaquant', 'rarity' => 'common', 'number' => 17, 'attack' => 71, 'defense' => 30, 'speed' => 78, 'stamina' => 71],

            // Staff
            ['name' => 'Gérard Poulain', 'position' => 'Entraîneur', 'rarity' => 'legendary', 'number' => null, 'attack' => 85, 'defense' => 88, 'speed' => 52, 'stamina' => 92],
        ];
    }

    protected function getChateauOlonnePlayers(): array
    {
        return [
            // Gardiens
            ['name' => 'Alexandre Payet', 'position' => 'Gardien', 'rarity' => 'legendary', 'number' => 1, 'attack' => 23, 'defense' => 90, 'speed' => 43, 'stamina' => 79],
            ['name' => 'Fabien Maillet', 'position' => 'Gardien', 'rarity' => 'uncommon', 'number' => 16, 'attack' => 20, 'defense' => 72, 'speed' => 39, 'stamina' => 70],

            // Défenseurs
            ['name' => 'Cyril Lelong', 'position' => 'Défenseur', 'rarity' => 'uncommon', 'number' => 2, 'attack' => 35, 'defense' => 78, 'speed' => 66, 'stamina' => 79],
            ['name' => 'Loïc Berthelot', 'position' => 'Défenseur', 'rarity' => 'rare', 'number' => 3, 'attack' => 38, 'defense' => 84, 'speed' => 69, 'stamina' => 83],
            ['name' => 'Grégory Ledoux', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 4, 'attack' => 30, 'defense' => 71, 'speed' => 61, 'stamina' => 76],
            ['name' => 'Mickael Genty', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 5, 'attack' => 28, 'defense' => 69, 'speed' => 59, 'stamina' => 74],
            ['name' => 'Yoann Leroux', 'position' => 'Défenseur', 'rarity' => 'uncommon', 'number' => 12, 'attack' => 33, 'defense' => 75, 'speed' => 63, 'stamina' => 77],
            ['name' => 'Florent Besnard', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 15, 'attack' => 29, 'defense' => 70, 'speed' => 60, 'stamina' => 75],

            // Milieux
            ['name' => 'Raphaël Huet', 'position' => 'Milieu', 'rarity' => 'rare', 'number' => 6, 'attack' => 72, 'defense' => 67, 'speed' => 80, 'stamina' => 86],
            ['name' => 'Anthony Corre', 'position' => 'Milieu', 'rarity' => 'epic', 'number' => 7, 'attack' => 78, 'defense' => 64, 'speed' => 85, 'stamina' => 90],
            ['name' => 'Sébastien Bonneau', 'position' => 'Milieu', 'rarity' => 'uncommon', 'number' => 8, 'attack' => 67, 'defense' => 61, 'speed' => 77, 'stamina' => 83],
            ['name' => 'Damien Salmon', 'position' => 'Milieu', 'rarity' => 'common', 'number' => 10, 'attack' => 63, 'defense' => 57, 'speed' => 74, 'stamina' => 79],
            ['name' => 'Vincent Loiseau', 'position' => 'Milieu', 'rarity' => 'uncommon', 'number' => 14, 'attack' => 68, 'defense' => 62, 'speed' => 78, 'stamina' => 84],
            ['name' => 'Laurent Gregoire', 'position' => 'Milieu', 'rarity' => 'common', 'number' => 18, 'attack' => 62, 'defense' => 58, 'speed' => 73, 'stamina' => 78],

            // Attaquants
            ['name' => 'Thierry Besson', 'position' => 'Attaquant', 'rarity' => 'epic', 'number' => 9, 'attack' => 89, 'defense' => 32, 'speed' => 87, 'stamina' => 78],
            ['name' => 'Franck Baron', 'position' => 'Attaquant', 'rarity' => 'rare', 'number' => 11, 'attack' => 83, 'defense' => 31, 'speed' => 83, 'stamina' => 75],
            ['name' => 'Jonathan Hébert', 'position' => 'Attaquant', 'rarity' => 'uncommon', 'number' => 13, 'attack' => 77, 'defense' => 33, 'speed' => 80, 'stamina' => 73],
            ['name' => 'Mickaël Millet', 'position' => 'Attaquant', 'rarity' => 'common', 'number' => 17, 'attack' => 72, 'defense' => 31, 'speed' => 79, 'stamina' => 72],

            // Staff
            ['name' => 'Patrick Leblanc', 'position' => 'Entraîneur', 'rarity' => 'legendary', 'number' => null, 'attack' => 87, 'defense' => 90, 'speed' => 54, 'stamina' => 94],
        ];
    }

    protected function getmareuilPlayers(): array
    {
        return [
            // Gardiens
            ['name' => 'Nicolas Fouquet', 'position' => 'Gardien', 'rarity' => 'legendary', 'number' => 1, 'attack' => 22, 'defense' => 87, 'speed' => 41, 'stamina' => 77],
            ['name' => 'Jordan Raynaud', 'position' => 'Gardien', 'rarity' => 'uncommon', 'number' => 16, 'attack' => 18, 'defense' => 70, 'speed' => 36, 'stamina' => 68],

            // Défenseurs
            ['name' => 'Christophe Hubert', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 2, 'attack' => 31, 'defense' => 72, 'speed' => 63, 'stamina' => 76],
            ['name' => 'Laurent Martin', 'position' => 'Défenseur', 'rarity' => 'uncommon', 'number' => 3, 'attack' => 33, 'defense' => 75, 'speed' => 64, 'stamina' => 78],
            ['name' => 'Patrick Rousseau', 'position' => 'Défenseur', 'rarity' => 'rare', 'number' => 4, 'attack' => 35, 'defense' => 80, 'speed' => 63, 'stamina' => 80],
            ['name' => 'Daniel Vincent', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 5, 'attack' => 26, 'defense' => 68, 'speed' => 58, 'stamina' => 74],
            ['name' => 'Michel Lemoine', 'position' => 'Défenseur', 'rarity' => 'uncommon', 'number' => 12, 'attack' => 30, 'defense' => 72, 'speed' => 60, 'stamina' => 75],
            ['name' => 'Olivier Deschamps', 'position' => 'Défenseur', 'rarity' => 'common', 'number' => 15, 'attack' => 26, 'defense' => 66, 'speed' => 56, 'stamina' => 71],

            // Milieux
            ['name' => 'Thierry Garnier', 'position' => 'Milieu', 'rarity' => 'uncommon', 'number' => 6, 'attack' => 60, 'defense' => 59, 'speed' => 72, 'stamina' => 79],
            ['name' => 'Frédéric Boyer', 'position' => 'Milieu', 'rarity' => 'rare', 'number' => 7, 'attack' => 67, 'defense' => 62, 'speed' => 76, 'stamina' => 81],
            ['name' => 'Alain Petit', 'position' => 'Milieu', 'rarity' => 'epic', 'number' => 8, 'attack' => 72, 'defense' => 65, 'speed' => 79, 'stamina' => 84],
            ['name' => 'Jacques Lambert', 'position' => 'Milieu', 'rarity' => 'common', 'number' => 10, 'attack' => 57, 'defense' => 49, 'speed' => 69, 'stamina' => 72],
            ['name' => 'Philippe Durand', 'position' => 'Milieu', 'rarity' => 'uncommon', 'number' => 14, 'attack' => 60, 'defense' => 54, 'speed' => 71, 'stamina' => 75],
            ['name' => 'Bernard Simon', 'position' => 'Milieu', 'rarity' => 'common', 'number' => 18, 'attack' => 55, 'defense' => 50, 'speed' => 67, 'stamina' => 70],

            // Attaquants
            ['name' => 'Gérard Michel', 'position' => 'Attaquant', 'rarity' => 'epic', 'number' => 9, 'attack' => 84, 'defense' => 30, 'speed' => 84, 'stamina' => 75],
            ['name' => 'Jean Leroy', 'position' => 'Attaquant', 'rarity' => 'rare', 'number' => 11, 'attack' => 77, 'defense' => 27, 'speed' => 79, 'stamina' => 71],
            ['name' => 'Claude Roux', 'position' => 'Attaquant', 'rarity' => 'uncommon', 'number' => 13, 'attack' => 70, 'defense' => 28, 'speed' => 76, 'stamina' => 69],
            ['name' => 'André Blanc', 'position' => 'Attaquant', 'rarity' => 'common', 'number' => 17, 'attack' => 65, 'defense' => 24, 'speed' => 72, 'stamina' => 66],

            // Staff
            ['name' => 'Robert Moreau', 'position' => 'Entraîneur', 'rarity' => 'legendary', 'number' => null, 'attack' => 79, 'defense' => 81, 'speed' => 47, 'stamina' => 85],
        ];
    }
}








