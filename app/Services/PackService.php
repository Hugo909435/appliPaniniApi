<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Challenge;
use App\Models\Pack;
use App\Models\User;
use App\Services\ChallengeService;
use Illuminate\Support\Collection;

class PackService
{
    const PACK_SIZE = 5;
    const PACK_COST = 500;

    const RARITIES = [
        'common'    => 50.0,
        'uncommon'  => 30.0,
        'rare'      => 12.0,
        'epic'      =>  5.0,
        'legendary' =>  1.0,
        'icone'     =>  0.5,
        'special'   =>  1.5,
    ];

    const SPECIAL_CHANCE = 1;

    public function openPack(User $user, bool $isFree = false, ?int $packId = null): array
    {
        $pack = $packId ? Pack::findOrFail($packId) : null;
        $packSize = $pack ? $pack->card_count : self::PACK_SIZE;
        $packCost = $pack ? $pack->price : self::PACK_COST;
        $clubTeamId = $user->club_team_id;

        if ($isFree) {
            if (!$user->useFreePack()) {
                throw new \Exception('Pas de pack gratuit disponible');
            }
        } else {
            if (!$user->removeCoins($packCost)) {
                throw new \Exception('Pas assez de pièces');
            }
        }

        $cards = $this->generatePackCards($packSize, $clubTeamId, $pack?->rarity_boosts, $pack?->id);

        foreach ($cards as $card) {
            $user->addCard($card);
        }

        if ($user->profile) {
            $user->profile->incrementPacksOpened();
        }
        app(ChallengeService::class)->recordEvent($user, Challenge::METRIC_PACKS_OPENED);

        $user->refresh();

        return [
            'cards'   => $cards,
            'user'    => $user,
            'is_free' => $isFree,
            'pack'    => $pack,
        ];
    }

    protected function generatePackCards(int $packSize, ?int $clubTeamId = null, ?array $rarityBoosts = null, ?int $packId = null): Collection
    {
        $cards = collect();

        $baseRarities = $rarityBoosts ?? self::RARITIES;

        $baseSlugs    = ['common', 'uncommon', 'rare', 'epic', 'legendary', 'icone'];
        $specialSlugs = \DB::table('rarities')
            ->whereNotIn('slug', $baseSlugs)
            ->pluck('slug')
            ->toArray();

        $countsQuery = \DB::table('cards')
            ->join('rarities', 'rarities.id', '=', 'cards.rarities_id')
            ->where('cards.is_exclusive', false);
        if ($clubTeamId) {
            $countsQuery->where('cards.club_team_id', $clubTeamId);
        }
        if ($packId) {
            $countsQuery->where('cards.pack_id', $packId);
        }
        $availableCounts = $countsQuery
            ->groupBy('rarities.slug')
            ->selectRaw('rarities.slug, COUNT(*) as total')
            ->pluck('total', 'rarities.slug')
            ->toArray();

        $specialQuery = \DB::table('cards')
            ->join('rarities', 'rarities.id', '=', 'cards.rarities_id')
            ->whereIn('rarities.slug', $specialSlugs);
        if ($clubTeamId) {
            $specialQuery->where('cards.club_team_id', $clubTeamId);
        }
        if ($packId) {
            $specialQuery->where('cards.pack_id', $packId);
        }
        $specialCount = empty($specialSlugs) ? 0 : $specialQuery->count();

        $filteredRarities = array_filter(
            $baseRarities,
            fn($w, $slug) => $w > 0 && (
                ($slug === 'special' && $specialCount > 0) || array_key_exists($slug, $availableCounts)
            ),
            ARRAY_FILTER_USE_BOTH
        );

        if (empty($filteredRarities)) {
            $filteredRarities = self::RARITIES;
        }

        // Pre-fetch card IDs by rarity — évite N requêtes ORDER BY RAND()
        $cardIdsByRarity = [];
        foreach (array_keys($filteredRarities) as $slug) {
            if ($slug === 'special') {
                $cardIdsByRarity[$slug] = \DB::table('cards')
                    ->join('rarities', 'rarities.id', '=', 'cards.rarities_id')
                    ->whereIn('rarities.slug', $specialSlugs)
                    ->when($clubTeamId, fn($q) => $q->where('cards.club_team_id', $clubTeamId))
                    ->when($packId, fn($q) => $q->where('cards.pack_id', $packId))
                    ->pluck('cards.id')->toArray();
            } else {
                $cardIdsByRarity[$slug] = \DB::table('cards')
                    ->join('rarities', 'rarities.id', '=', 'cards.rarities_id')
                    ->where('cards.is_exclusive', false)
                    ->where('rarities.slug', $slug)
                    ->when($clubTeamId, fn($q) => $q->where('cards.club_team_id', $clubTeamId))
                    ->when($packId, fn($q) => $q->where('cards.pack_id', $packId))
                    ->pluck('cards.id')->toArray();
            }
        }

        $fallbackIds = \DB::table('cards')
            ->where('is_exclusive', false)
            ->when($clubTeamId, fn($q) => $q->where('club_team_id', $clubTeamId))
            ->when($packId, fn($q) => $q->where('pack_id', $packId))
            ->pluck('id')->toArray();

        // Tirage aléatoire en PHP — plus de ORDER BY RAND()
        $selectedIds = [];
        for ($i = 0; $i < $packSize; $i++) {
            $raritySlug = $this->pickRandomRarity($filteredRarities);
            $pool = $cardIdsByRarity[$raritySlug] ?? [];
            if (empty($pool)) {
                $pool = $fallbackIds;
            }
            if (!empty($pool)) {
                $selectedIds[] = $pool[array_rand($pool)];
            }
        }

        if (empty($selectedIds)) {
            return collect();
        }

        // Une seule requête eager-loaded pour toutes les cartes sélectionnées
        $loadedCards = Card::with(['position', 'rarity'])
            ->whereIn('id', $selectedIds)
            ->get()
            ->keyBy('id');

        return collect($selectedIds)
            ->map(fn($id) => $loadedCards->get($id))
            ->filter()
            ->values();
    }

    protected function pickRandomRarity(array $rarities): string
    {
        $totalWeight   = array_sum($rarities);
        $random        = random_int(1, (int) ($totalWeight * 10000)) / 10000;
        $currentWeight = 0;

        foreach ($rarities as $rarity => $probability) {
            $currentWeight += $probability;
            if ($random <= $currentWeight) {
                return $rarity;
            }
        }

        return array_key_last($rarities) ?? 'common';
    }

    public function getTimeUntilNextFreePack(User $user): ?int
    {
        if ($user->canClaimFreePack()) {
            return null;
        }

        return (int) now()->diffInSeconds(now()->startOfWeek(\Carbon\Carbon::MONDAY)->addWeek());
    }
}
