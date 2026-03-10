<?php

namespace Database\Seeders;

use App\Models\Challenge;
use App\Models\Medal;
use Illuminate\Database\Seeder;

class ChallengeSeeder extends Seeder
{
    public function run(): void
    {
        $medals = Medal::query()->get()->keyBy('slug');

        $medalFor = function (string $challengeSlug) use ($medals) {
            $medalSlug = str_starts_with($challengeSlug, 'medal-')
                ? $challengeSlug
                : 'medal-' . $challengeSlug;
            return $medals->get($medalSlug)?->id;
        };

        $packTargets = [
            1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 12, 14, 16, 18, 20, 22, 24, 26, 28, 30,
            35, 40, 45, 50, 55, 60, 70, 80, 90, 100, 120, 140, 160, 180, 200,
            230, 260, 300, 350, 400, 450, 500, 600,
        ];

        $tradeTargets = [
            1, 2, 3, 4, 5, 6, 8, 10, 12, 15, 18, 20, 25, 30, 35, 40, 50, 60,
            70, 80, 90, 100, 125, 150, 200,
        ];

        $loginMedals = [3, 5, 7, 14, 30, 60, 90, 120, 180, 365];
        $packMedals = [25, 50, 100, 150, 200, 300, 400, 600, 800, 1000];
        $tradeMedals = [10, 25, 50, 75, 100, 150, 200, 300, 400, 500];

        $xpForPack = fn(int $target) => 50 + (int) round($target * 6);
        $coinsForPack = fn(int $target) => 10 + (int) round($target * 3);
        $xpForTrade = fn(int $target) => 60 + (int) round($target * 7);
        $coinsForTrade = fn(int $target) => 12 + (int) round($target * 4);
        $xpForLoginMedal = fn(int $target) => 80 + (int) round($target * 20);
        $coinsForLoginMedal = fn(int $target) => 20 + (int) round($target * 6);
        $xpForPackMedal = fn(int $target) => 120 + (int) round($target * 9);
        $coinsForPackMedal = fn(int $target) => 30 + (int) round($target * 4);
        $xpForTradeMedal = fn(int $target) => 140 + (int) round($target * 10);
        $coinsForTradeMedal = fn(int $target) => 35 + (int) round($target * 4);

        $challenges = [
            // Daily (3)
            [
                'slug' => 'daily-login',
                'name' => 'Connexion du jour',
                'description' => 'Connectez-vous aujourd’hui.',
                'type' => Challenge::TYPE_DAILY,
                'metric' => Challenge::METRIC_LOGIN_STREAK,
                'target' => 1,
                'reward_xp' => 60,
                'reward_coins' => 8,
                'reward_medal_id' => null,
                'reward_card_id' => null,
                'reward_card_quantity' => 1,
            ],
            [
                'slug' => 'daily-open-pack',
                'name' => 'Pack du jour',
                'description' => 'Ouvrez 1 pack aujourd’hui.',
                'type' => Challenge::TYPE_DAILY,
                'metric' => Challenge::METRIC_PACKS_OPENED,
                'target' => 1,
                'reward_xp' => 90,
                'reward_coins' => 12,
                'reward_medal_id' => null,
                'reward_card_id' => null,
                'reward_card_quantity' => 1,
            ],
            [
                'slug' => 'daily-trade',
                'name' => 'Échange du jour',
                'description' => 'Réalisez 1 échange aujourd’hui.',
                'type' => Challenge::TYPE_DAILY,
                'metric' => Challenge::METRIC_TRADES_COMPLETED,
                'target' => 1,
                'reward_xp' => 110,
                'reward_coins' => 14,
                'reward_medal_id' => null,
                'reward_card_id' => null,
                'reward_card_quantity' => 1,
            ],
        ];

        // Challenges packs (now in medals)
        $maxPackTarget = max($packTargets);
        foreach ($packTargets as $target) {
            $isUltimate = $target === $maxPackTarget;
            $slug = "packs-open-{$target}";
            $challenges[] = [
                'slug' => $slug,
                'name' => $isUltimate ? "Ultime packs" : "Ouvrir {$target} packs",
                'description' => $isUltimate ? "Ouvrez {$target} packs au total. (Ultime)" : "Ouvrez {$target} packs au total.",
                'type' => Challenge::TYPE_PERMANENT,
                'metric' => Challenge::METRIC_PACKS_OPENED,
                'target' => $target,
                'reward_xp' => $xpForPack($target),
                'reward_coins' => $coinsForPack($target),
                'reward_medal_id' => $medalFor($slug),
                'reward_card_id' => null,
                'reward_card_quantity' => 1,
            ];
        }

        // Challenges échanges (now in medals)
        $maxTradeTarget = max($tradeTargets);
        foreach ($tradeTargets as $target) {
            $isUltimate = $target === $maxTradeTarget;
            $slug = "trades-complete-{$target}";
            $challenges[] = [
                'slug' => $slug,
                'name' => $isUltimate ? "Ultime échanges" : "Faire {$target} échanges",
                'description' => $isUltimate ? "Validez {$target} échanges au total. (Ultime)" : "Validez {$target} échanges au total.",
                'type' => Challenge::TYPE_PERMANENT,
                'metric' => Challenge::METRIC_TRADES_COMPLETED,
                'target' => $target,
                'reward_xp' => $xpForTrade($target),
                'reward_coins' => $coinsForTrade($target),
                'reward_medal_id' => $medalFor($slug),
                'reward_card_id' => null,
                'reward_card_quantity' => 1,
            ];
        }

        // Médailles assiduité
        foreach ($loginMedals as $target) {
            $slug = "medal-login-{$target}";
            $challenges[] = [
                'slug' => $slug,
                'name' => "Assiduité {$target} jours",
                'description' => "Connectez-vous {$target} jours d’affilée.",
                'type' => Challenge::TYPE_PERMANENT,
                'metric' => Challenge::METRIC_LOGIN_STREAK,
                'target' => $target,
                'reward_xp' => $xpForLoginMedal($target),
                'reward_coins' => $coinsForLoginMedal($target),
                'reward_medal_id' => $medalFor($slug),
                'reward_card_id' => null,
                'reward_card_quantity' => 1,
            ];
        }

        // Médailles packs
        foreach ($packMedals as $target) {
            $slug = "medal-packs-{$target}";
            $challenges[] = [
                'slug' => $slug,
                'name' => "Prestige Packs {$target}",
                'description' => "Ouvrez {$target} packs. (Prestige)",
                'type' => Challenge::TYPE_PERMANENT,
                'metric' => Challenge::METRIC_PACKS_OPENED,
                'target' => $target,
                'reward_xp' => $xpForPackMedal($target),
                'reward_coins' => $coinsForPackMedal($target),
                'reward_medal_id' => $medalFor($slug),
                'reward_card_id' => null,
                'reward_card_quantity' => 1,
            ];
        }

        // Médailles échanges
        foreach ($tradeMedals as $target) {
            $slug = "medal-trades-{$target}";
            $challenges[] = [
                'slug' => $slug,
                'name' => "Prestige Échanges {$target}",
                'description' => "Validez {$target} échanges. (Prestige)",
                'type' => Challenge::TYPE_PERMANENT,
                'metric' => Challenge::METRIC_TRADES_COMPLETED,
                'target' => $target,
                'reward_xp' => $xpForTradeMedal($target),
                'reward_coins' => $coinsForTradeMedal($target),
                'reward_medal_id' => $medalFor($slug),
                'reward_card_id' => null,
                'reward_card_quantity' => 1,
            ];
        }

        foreach ($challenges as $challenge) {
            Challenge::updateOrCreate(
                ['slug' => $challenge['slug']],
                $challenge
            );
        }
    }
}
