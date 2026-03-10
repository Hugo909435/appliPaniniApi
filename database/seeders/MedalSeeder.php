<?php

namespace Database\Seeders;

use App\Models\Medal;
use Illuminate\Database\Seeder;

class MedalSeeder extends Seeder
{
    public function run(): void
    {
        $packTargets = [
            1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 12, 14, 16, 18, 20, 22, 24, 26, 28, 30,
            35, 40, 45, 50, 55, 60, 70, 80, 90, 100, 120, 140, 160, 180, 200,
            230, 260, 300, 350, 400, 450, 500, 600,
        ];

        $tradeTargets = [
            1, 2, 3, 4, 5, 6, 8, 10, 12, 15, 18, 20, 25, 30, 35, 40, 50, 60,
            70, 80, 90, 100, 125, 150, 200,
        ];

        $loginTargets = [3, 5, 7, 14, 30, 60, 90, 120, 180, 365];
        $packMedals = [25, 50, 100, 150, 200, 300, 400, 600, 800, 1000];
        $tradeMedals = [10, 25, 50, 75, 100, 150, 200, 300, 400, 500];

        $rarityForLogin = function (int $target) {
            return match (true) {
                $target >= 365 => 'legendary',
                $target >= 180 => 'legendary',
                $target >= 90 => 'epic',
                $target >= 30 => 'rare',
                $target >= 7 => 'uncommon',
                default => 'common',
            };
        };

        $rarityForPack = function (int $target) {
            return match (true) {
                $target >= 400 => 'legendary',
                $target >= 200 => 'epic',
                $target >= 80 => 'rare',
                $target >= 30 => 'uncommon',
                default => 'common',
            };
        };

        $rarityForTrade = function (int $target) {
            return match (true) {
                $target >= 200 => 'legendary',
                $target >= 100 => 'epic',
                $target >= 40 => 'rare',
                $target >= 15 => 'uncommon',
                default => 'common',
            };
        };

        $iconForLogin = function (int $target) {
            return match (true) {
                $target >= 365 => '/assets/medals/icons/medal-icon-crown.svg',
                $target >= 180 => '/assets/medals/icons/medal-icon-crown.svg',
                $target >= 90 => '/assets/medals/icons/medal-icon-trophy.svg',
                $target >= 30 => '/assets/medals/icons/medal-icon-target.svg',
                $target >= 7 => '/assets/medals/icons/medal-icon-flame.svg',
                default => '/assets/medals/icons/medal-icon-calendar.svg',
            };
        };

        $iconForPack = function (int $target) {
            return match (true) {
                $target >= 400 => '/assets/medals/icons/medal-icon-crown.svg',
                $target >= 200 => '/assets/medals/icons/medal-icon-diamond.svg',
                $target >= 80 => '/assets/medals/icons/medal-icon-spark.svg',
                $target >= 30 => '/assets/medals/icons/medal-icon-stack.svg',
                default => '/assets/medals/icons/medal-icon-box.svg',
            };
        };

        $iconForTrade = function (int $target) {
            return match (true) {
                $target >= 200 => '/assets/medals/icons/medal-icon-trophy.svg',
                $target >= 100 => '/assets/medals/icons/medal-icon-briefcase.svg',
                $target >= 40 => '/assets/medals/icons/medal-icon-scale.svg',
                $target >= 15 => '/assets/medals/icons/medal-icon-arrows.svg',
                default => '/assets/medals/icons/medal-icon-handshake.svg',
            };
        };

        $medals = [];

        foreach ($loginTargets as $target) {
            $medals[] = [
                'slug' => "medal-login-{$target}",
                'name' => "Assiduité {$target} jours",
                'description' => "Connectez-vous {$target} jours d’affilée.",
                'rarity' => $rarityForLogin($target),
                'icon' => $iconForLogin($target),
            ];
        }

        foreach ($packTargets as $target) {
            $medals[] = [
                'slug' => "medal-packs-open-{$target}",
                'name' => "Packs {$target}",
                'description' => "Ouvrez {$target} packs au total.",
                'rarity' => $rarityForPack($target),
                'icon' => $iconForPack($target),
            ];
        }

        foreach ($tradeTargets as $target) {
            $medals[] = [
                'slug' => "medal-trades-complete-{$target}",
                'name' => "Échanges {$target}",
                'description' => "Validez {$target} échanges au total.",
                'rarity' => $rarityForTrade($target),
                'icon' => $iconForTrade($target),
            ];
        }

        foreach ($packMedals as $target) {
            $medals[] = [
                'slug' => "medal-packs-{$target}",
                'name' => "Prestige Packs {$target}",
                'description' => "Ouvrez {$target} packs. (Prestige)",
                'rarity' => $rarityForPack($target),
                'icon' => $iconForPack($target),
            ];
        }

        foreach ($tradeMedals as $target) {
            $medals[] = [
                'slug' => "medal-trades-{$target}",
                'name' => "Prestige Échanges {$target}",
                'description' => "Validez {$target} échanges. (Prestige)",
                'rarity' => $rarityForTrade($target),
                'icon' => $iconForTrade($target),
            ];
        }

        foreach ($medals as $medal) {
            $medal['image'] = '/assets/medals/medal-' . $medal['rarity'] . '.svg';
            Medal::updateOrCreate(
                ['slug' => $medal['slug']],
                $medal
            );
        }
    }
}
