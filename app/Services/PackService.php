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
    const PACK_SIZE = 9;
    const PACK_COST = 50;

    public function openPack(User $user, bool $isFree = false, ?int $packId = null, bool $useMoney = false): array
    {
        $pack = $packId ? Pack::findOrFail($packId) : null;
        $packSize = $pack ? $pack->card_count : self::PACK_SIZE;
        $packCost = $pack ? $pack->price : self::PACK_COST;
        $moneyCost = $pack ? $pack->money_price : 0;

        // Vérifications
        if ($isFree) {
            if (!$user->useFreePack()) {
                throw new \Exception('Pas de pack gratuit disponible');
            }
        } elseif ($useMoney) {
            // ✅ Payer avec money
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

        $rarityBoosts = $pack ? $pack->rarity_boosts : null;
        $cards = $this->generatePackCards($packSize, $rarityBoosts);

        foreach ($cards as $card) {
            $user->addCard($card);
        }

        if ($user->profile) {
            $user->profile->incrementPacksOpened();
        }
        app(ChallengeService::class)->recordEvent($user, Challenge::METRIC_PACKS_OPENED);

        $user->refresh();

        return [
            'cards' => $cards,
            'user' => $user,
            'is_free' => $isFree,
            'used_money' => $useMoney,
            'pack' => $pack,
        ];
    }

    protected function generatePackCards(int $packSize, ?array $rarityBoosts = null): Collection
    {
        $cards = collect();

        $rarities = $rarityBoosts ?? [
            'common' => 70,
            'uncommon' => 20,
            'rare' => 7,
            'epic' => 2.5,
            'legendary' => 0.5,
        ];

        for ($i = 0; $i < $packSize; $i++) {
            $raritySlug = $this->pickRandomRarity($rarities);

            $card = Card::with(['position', 'rarity'])
                                ->where('is_exclusive', false)
                ->whereHas('rarity', function ($query) use ($raritySlug) {
                    $query->where('slug', $raritySlug);
                })
                ->inRandomOrder()
                ->first();

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

        return $cards;
    }

    protected function pickRandomRarity(array $rarities): string
    {
        $totalWeight = array_sum($rarities);
        $random = mt_rand(1, $totalWeight * 100) / 100;
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

        return now()->diffInSeconds(now()->endOfDay());
    }
}



