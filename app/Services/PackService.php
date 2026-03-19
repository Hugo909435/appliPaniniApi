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

    public function openPack(User $user, bool $isFree = false, ?int $packId = null, bool $useMoney = false): array
    {
        $pack = $packId ? Pack::findOrFail($packId) : null;
        $packSize = $pack ? $pack->card_count : self::PACK_SIZE;
        $packCost = $pack ? $pack->price : self::PACK_COST;
        $clubTeamId = $user->club_team_id;

        if ($isFree) {
            if (!$user->useFreePack()) {
                throw new \Exception('Pas de pack gratuit disponible');
            }
        } elseif ($useMoney) {
            $moneyCost = $pack ? $pack->money_price : 0;
            if ($user->money < $moneyCost) {
                throw new \Exception('Pas assez de money');
            }
            $user->money -= $moneyCost;
            $user->save();
        } else {
            if (!$user->removeCoins($packCost)) {
                throw new \Exception('Pas assez de pièces');
            }
        }

        $cards = $this->generatePackCards($packSize, $clubTeamId);

        foreach ($cards as $card) {
            $user->addCard($card);
        }

        if ($user->profile) {
            $user->profile->incrementPacksOpened();
        }
        app(ChallengeService::class)->recordEvent($user, Challenge::METRIC_PACKS_OPENED);

        $user->refresh();

        return [
            'cards'      => $cards,
            'user'       => $user,
            'is_free'    => $isFree,
            'used_money' => $useMoney,
            'pack'       => $pack,
        ];
    }

    protected function generatePackCards(int $packSize, ?int $clubTeamId = null): Collection
    {
        $cards = collect();

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
        $specialCount = empty($specialSlugs) ? 0 : $specialQuery->count();

        $filteredRarities = array_filter(
            self::RARITIES,
            fn($w, $slug) => $w > 0 && (
                ($slug === 'special' && $specialCount > 0) || array_key_exists($slug, $availableCounts)
            ),
            ARRAY_FILTER_USE_BOTH
        );

        if (empty($filteredRarities)) {
            $filteredRarities = self::RARITIES;
        }

        for ($i = 0; $i < $packSize; $i++) {
            $raritySlug = $this->pickRandomRarity($filteredRarities);

            if ($raritySlug === 'special' && !empty($specialSlugs)) {
                $card = Card::with(['position', 'rarity'])
                    ->whereHas('rarity', fn($q) => $q->whereIn('slug', $specialSlugs))
                    ->when($clubTeamId, fn($q) => $q->where('club_team_id', $clubTeamId))
                    ->inRandomOrder()
                    ->first();
            } else {
                $card = Card::with(['position', 'rarity'])
                    ->where('is_exclusive', false)
                    ->whereHas('rarity', fn($q) => $q->where('slug', $raritySlug))
                    ->when($clubTeamId, fn($q) => $q->where('club_team_id', $clubTeamId))
                    ->inRandomOrder()
                    ->first();
            }

            if (!$card) {
                $card = Card::with(['position', 'rarity'])
                    ->where('is_exclusive', false)
                    ->when($clubTeamId, fn($q) => $q->where('club_team_id', $clubTeamId))
                    ->inRandomOrder()
                    ->first();
            }

            if ($card) {
                $cards->push($card);
            }
        }

        return $cards;
    }

    protected function pickRandomRarity(array $rarities): string
    {
        $totalWeight   = array_sum($rarities);
        $random        = mt_rand(1, (int) ($totalWeight * 100)) / 100;
        $currentWeight = 0;

        foreach ($rarities as $rarity => $probability) {
            $currentWeight += $probability;
            if ($random <= $currentWeight) {
                return $rarity;
            }
        }

        return 'common';
    }

    public function getTimeUntilNextFreePack(User $user): ?int
    {
        if ($user->canClaimFreePack()) {
            return null;
        }

        return (int) now()->diffInSeconds(now()->startOfWeek(\Carbon\Carbon::MONDAY)->addWeek());
    }
}
