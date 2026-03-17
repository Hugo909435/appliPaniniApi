<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pack;
use App\Models\Card;
use App\Services\PackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackController extends Controller
{
    public function __construct(protected PackService $packService) {}

    /** Raretés par défaut (même valeurs que PackService) */
    private const DEFAULT_RARITIES = [
        'common'    => ['label' => 'Commun',     'color' => '#9CA3AF', 'probability' => 38.0],
        'uncommon'  => ['label' => 'Peu commun', 'color' => '#10B981', 'probability' => 28.0],
        'rare'      => ['label' => 'Rare',       'color' => '#3B82F6', 'probability' => 16.0],
        'epic'      => ['label' => 'Épique',     'color' => '#8B5CF6', 'probability' =>  8.0],
        'legendary' => ['label' => 'Légendaire', 'color' => '#F59E0B', 'probability' =>  3.5],
        'icone'     => ['label' => 'Icône',      'color' => '#EF4444', 'probability' =>  1.5],
    ];

    private function buildRarities(Pack $pack): array
    {
        $boosts = $pack->rarity_boosts ?? [];
        $result = [];

        foreach (self::DEFAULT_RARITIES as $slug => $meta) {
            $result[] = [
                'rarity'      => $slug,
                'label'       => $meta['label'],
                'color'       => $meta['color'],
                'probability' => $boosts[$slug] ?? $meta['probability'],
                'per_pack'    => false,
            ];
        }

        $hasSpecial = Card::whereHas('rarity', fn($q) => $q->where('slug', 'special'))->exists();
        if ($hasSpecial) {
            $result[] = [
                'rarity'      => 'special',
                'label'       => 'Spécial',
                'color'       => '#22D3EE',
                'probability' => $boosts['special'] ?? \App\Services\PackService::PAID_SPECIAL_CHANCE,
                'per_pack'    => false,
            ];
        }

        return $result;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $packs = Pack::where('is_active', true)
            ->orderBy('price', 'asc')
            ->get()
            ->map(fn($pack) => [
                'id'          => $pack->id,
                'name'        => $pack->name,
                'slug'        => $pack->slug,
                'description' => $pack->description,
                'image'       => $pack->image_url,
                'price'       => $pack->price,
                'money_price' => $pack->money_price,
                'card_count'  => $pack->card_count,
                'rarities'    => $this->buildRarities($pack),
            ]);

        return response()->json([
            'packs' => $packs,
            'user' => [
                'coins' => $user->coins,
                'money' => $user->money,
                'free_packs' => $user->free_packs,
                'can_claim_free_pack' => $user->canClaimFreePack(),
                'time_until_next_claim' => $this->packService->getTimeUntilNextFreePack($user),
            ],
        ]);
    }

    public function opening(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pack_id'   => 'nullable|integer',
            'free'      => 'boolean',
            'use_money' => 'boolean',
            'count'     => 'integer|min:1|max:10',
        ]);

        $user     = $request->user();
        $isFree   = $request->boolean('free');
        $useMoney = $request->boolean('use_money');
        $count    = (int) ($validated['count'] ?? 1);

        // Les packs gratuits ne sont pas multipliables
        if ($isFree) $count = 1;

        $packId = $validated['pack_id'] ?? null;
        $pack = $packId
            ? Pack::find($packId)
            : Pack::where('is_active', true)->orderBy('price', 'asc')->first();

        if (!$pack) {
            return response()->json(['message' => 'Aucun pack disponible.'], 404);
        }

        // Vérification des ressources pour le total
        if ($isFree && !$user->hasFreePacks()) {
            return response()->json(['message' => "Vous n'avez pas de pack gratuit disponible."], 400);
        }
        if (!$isFree && $useMoney && $user->money < $pack->money_price * $count) {
            return response()->json(['message' => "Pas assez de money pour {$count} pack(s)."], 400);
        }
        if (!$isFree && !$useMoney && $user->coins < $pack->price * $count) {
            return response()->json(['message' => "Pas assez de pièces pour {$count} pack(s)."], 400);
        }

        try {
            $allCards = collect();
            for ($i = 0; $i < $count; $i++) {
                $result = $this->packService->openPack($user, $isFree && $i === 0, $pack->id, $useMoney);
                $allCards = $allCards->merge($result['cards']);
                $user     = $result['user'];
            }
            $cards = $allCards;

            $formattedCards = $cards->map(function ($card) use ($user) {
                $userCard = $user->cards()->where('card_id', $card->id)->first();
                return [
                    'id' => $card->id,
                    'name' => $card->name,
                    'position' => $card->position?->name,
                    'rarity' => strtolower(trim($card->rarity?->slug ?? 'common')),
                    'rarity_label' => $card->rarity?->name,
                    'rarity_color' => $card->rarity?->color,
                    'image' => $card->image_url,
                    'attack' => $card->attack,
                    'defense' => $card->defense,
                    'speed' => $card->speed,
                    'stamina' => $card->stamina,
                    'overall' => $card->overall,
                    'number' => $card->number,
                    'is_new' => $userCard && $userCard->pivot->quantity === 1,
                    'quantity' => $userCard ? $userCard->pivot->quantity : 1,
                ];
            });

            return response()->json([
                'pack' => [
                    'id' => $pack->id,
                    'name' => $pack->name,
                    'slug' => $pack->slug,
                    'image' => $pack->image_url,
                    'card_count' => $pack->card_count,
                ],
                'cards' => $formattedCards,
                'has_prestige_card' => $formattedCards->contains(fn($c) => in_array($c['rarity'], ['epic', 'legendary', 'icone'])),
                'user' => [
                    'coins' => $user->coins,
                    'money' => $user->money,
                    'free_packs' => $user->free_packs,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function claim(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->claimFreePack()) {
            return response()->json([
                'success' => false,
                'message' => "Vous ne pouvez pas encore récupérer de pack gratuit.",
            ], 400);
        }

        return response()->json([
            'success' => true,
            'free_packs' => $user->free_packs,
            'can_claim_free_pack' => $user->canClaimFreePack(),
            'time_until_next_claim' => $this->packService->getTimeUntilNextFreePack($user),
        ]);
    }
}
