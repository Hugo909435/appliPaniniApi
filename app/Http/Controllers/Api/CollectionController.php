<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $ownedCards = $user->cards()
            ->with(['position', 'rarity', 'clubTeam'])
            ->get()
            ->map(fn($card) => [
                'id' => $card->id,
                'name' => $card->name,
                'position' => $card->position?->name,
                'team' => $card->clubTeam?->name,
                'rarity' => strtolower($card->rarity?->slug ?? 'common'),
                'rarity_label' => $card->rarity?->name,
                'rarity_color' => $card->rarity?->color,
                'image' => $card->image_url,
                'attack' => $card->attack,
                'defense' => $card->defense,
                'speed' => $card->speed,
                'stamina' => $card->stamina,
                'overall' => $card->overall,
                'number' => $card->number,
                'quantity' => $card->pivot->quantity,
            ]);

        $allCards = Card::with(['position', 'rarity', 'clubTeam'])
            ->get()
            ->map(fn($card) => [
                'id' => $card->id,
                'name' => $card->name,
                'position' => $card->position?->name,
                'team' => $card->clubTeam?->name,
                'rarity' => strtolower($card->rarity?->slug ?? 'common'),
                'rarity_label' => $card->rarity?->name,
                'rarity_color' => $card->rarity?->color,
                'image' => $card->image_url,
                'attack' => $card->attack,
                'defense' => $card->defense,
                'speed' => $card->speed,
                'stamina' => $card->stamina,
                'overall' => $card->overall,
                'number' => $card->number,
            ]);
        return response()->json([
            'cards' => $ownedCards,
            'all_cards' => $allCards,
            'rarities' => $allCards->pluck('rarity')->unique()->values(),
            'positions' => $allCards->pluck('position')->filter()->unique()->values(),
        ]);
    }

    public function showCard(Request $request, Card $card): JsonResponse
    {
        $user = $request->user();
        $userCard = $user->cards()->where('card_id', $card->id)->first();
        $card->load(['position', 'rarity', 'clubTeam']);

        $availableForTrade = Card::whereNotIn('id', $user->cards()->pluck('cards.id'))
            ->with(['position', 'rarity'])
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'position' => $c->position?->name,
                'team' => $c->clubTeam?->name,
                'rarity' => strtolower($c->rarity?->slug ?? 'common'),
                'image' => $c->image_url,
            ]);

        return response()->json([
            'card' => [
                'id' => $card->id,
                'name' => $card->name,
                'position' => $card->position?->name,
                'team' => $card->clubTeam?->name,
                'rarity' => strtolower($card->rarity?->slug ?? 'common'),
                'rarity_label' => $card->rarity?->name,
                'rarity_color' => $card->rarity?->color,
                'image' => $card->image_url,
                'attack' => $card->attack,
                'defense' => $card->defense,
                'speed' => $card->speed,
                'stamina' => $card->stamina,
                'overall' => $card->overall,
                'number' => $card->number,
                'owned' => $userCard !== null,
                'quantity' => $userCard ? $userCard->pivot->quantity : 0,
            ],
            'available_for_trade' => $availableForTrade,
        ]);
    }
}


