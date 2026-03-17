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

    // Drop rates for FREE packs (par carte)
    const FREE_RARITIES = [
        'common'    => 38.0,
        'uncommon'  => 28.0,
        'rare'      => 16.0,
        'epic'      =>  8.0,
        'legendary' =>  3.5,
        'icone'     =>  1.5,
        'special'   =>  5.0,
    ];

    // Drop rates for PAID (reward) packs (par carte)
    const PAID_RARITIES = [
        'common'    => 38.0,
        'uncommon'  => 28.0,
        'rare'      => 16.0,
        'epic'      =>  8.0,
        'legendary' =>  3.5,
        'icone'     =>  1.5,
        'special'   =>  5.0,
    ];

    // Special card chance (legacy per-pack fallback)
    const FREE_SPECIAL_CHANCE  = 5;
    const PAID_SPECIAL_CHANCE  = 5;

    public function openPack(User $user, bool $isFree = false, ?int $packId = null, bool $useMoney = false): array
    {
        $pack = $packId ? Pack::findOrFail($packId) : null;
        $packSize = $pack ? $pack->card_count : self::PACK_SIZE;
        $packCost = $pack ? $pack->price : self::PACK_COST;

        // Vérifications
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
            // Payer avec coins
            if (!$user->removeCoins($packCost)) {
                throw new \Exception('Pas assez de pièces');
            }
        }

        // Choose rarities based on pack type
        if ($pack && $pack->rarity_boosts) {
            $rarityBoosts = $pack->rarity_boosts;
        } elseif ($isFree) {
            $rarityBoosts = self::FREE_RARITIES;
        } else {
            $rarityBoosts = self::PAID_RARITIES;
        }

        // Inject special into boosts if missing (keeps legacy packs consistent)
        if (!array_key_exists('special', $rarityBoosts)) {
            $rarityBoosts['special'] = $isFree ? self::FREE_SPECIAL_CHANCE : self::PAID_SPECIAL_CHANCE;
        }

        // We now handle "special" as a per-card rarity
        $cards = $this->generatePackCards($packSize, $rarityBoosts, 0);

        foreach ($cards as $card) {
            $user->addCard($card);
        }

        if ($user->profile) {
            $user->profile->incrementPacksOpened();
        }
        app(ChallengeService::class)->recordEvent($user, Challenge::METRIC_PACKS_OPENED);

        $user->refresh();

        return [
            'cards'       => $cards,
            'user'        => $user,
            'is_free'     => $isFree,
            'used_money'  => $useMoney,
            'pack'        => $pack,
        ];
    }

    protected function generatePackCards(int $packSize, array $rarities, int $specialChance = 0): Collection
    {
        $cards = collect();

        // Filter out rarities with no available cards (prevents fake probabilities)
        $availableCounts = \DB::table('cards')
            ->join('rarities', 'rarities.id', '=', 'cards.rarities_id')
            ->where('cards.is_exclusive', false)
            ->groupBy('rarities.slug')
            ->selectRaw('rarities.slug, COUNT(*) as total')
            ->pluck('total', 'rarities.slug')
            ->toArray();

        $specialSlugs = ['special'];
        $specialCount = \DB::table('cards')
            ->join('rarities', 'rarities.id', '=', 'cards.rarities_id')
            ->whereIn('rarities.slug', $specialSlugs)
            ->count();
        $filteredRarities = array_filter(
            $rarities,
            fn($w, $slug) => $w > 0 && (
                ($slug === 'special' && $specialCount > 0) || array_key_exists($slug, $availableCounts)
            ),
            ARRAY_FILTER_USE_BOTH
        );

        if (empty($filteredRarities)) {
            $filteredRarities = $rarities;
        }

        for ($i = 0; $i < $packSize; $i++) {
            $raritySlug = $this->pickRandomRarity($filteredRarities);

            if ($raritySlug === 'special') {
                $card = Card::with(['position', 'rarity'])
                    ->whereHas('rarity', function ($query) use ($specialSlugs) {
                        $query->whereIn('slug', $specialSlugs);
                    })
                    ->inRandomOrder()
                    ->first();
            } else {
                $card = Card::with(['position', 'rarity'])
                    ->where('is_exclusive', false)
                    ->whereHas('rarity', function ($query) use ($raritySlug) {
                        $query->where('slug', $raritySlug);
                    })
                    ->inRandomOrder()
                    ->first();
            }

            if (!$card) {
                $card = Card::with(['position', 'rarity'])
                    ->where('is_exclusive', false)
                    ->inRandomOrder()
                    ->first();
            }

            if ($card) {
                $cards->push($card);
            }
        }

        // Special card: chance to replace one card with an exclusive/event card (legacy only)
        if ($specialChance > 0 && mt_rand(1, 100) <= $specialChance) {
            $specialCard = Card::with(['position', 'rarity'])
                ->where('is_exclusive', true)
                ->inRandomOrder()
                ->first();

            if ($specialCard && $cards->isNotEmpty()) {
                $cards->pop();
                $cards->push($specialCard);
            }
        }

        return $cards;
    }

    protected function pickRandomRarity(array $rarities): string
    {
        $totalWeight = array_sum($rarities);
        $random = mt_rand(1, (int) ($totalWeight * 100)) / 100;
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

        // Seconds until next Monday
        return (int) now()->diffInSeconds(now()->startOfWeek(\Carbon\Carbon::MONDAY)->addWeek());
    }
}
