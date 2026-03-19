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
        $clubId = $user->club_team_id;

        $ownedCards = $user->cards()
            ->with(['position', 'rarity', 'clubTeam'])
            ->when($clubId, fn($q) => $q->where('cards.club_team_id', $clubId))
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
            ->when($clubId, fn($q) => $q->where('club_team_id', $clubId))
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

    /**
     * Cartes possédées par l'utilisateur (sans all_cards) — pour CreateTradeScreen.
     */
    public function ownedCards(Request $request): JsonResponse
    {
        $user = $request->user();
        $clubId = $user->club_team_id;

        $cards = $user->cards()
            ->with(['position', 'rarity', 'clubTeam'])
            ->when($clubId, fn($q) => $q->where('cards.club_team_id', $clubId))
            ->get()
            ->map(fn($card) => [
                'id'          => $card->id,
                'name'        => $card->name,
                'position'    => $card->position?->name,
                'team'        => $card->clubTeam?->name,
                'rarity'      => strtolower($card->rarity?->slug ?? 'common'),
                'rarity_label'=> $card->rarity?->name,
                'rarity_color'=> $card->rarity?->color,
                'image'       => $card->image_url,
                'overall'     => $card->overall,
                'number'      => $card->number,
                'quantity'    => $card->pivot->quantity,
            ]);

        return response()->json(['cards' => $cards]);
    }

    /**
     * Toutes les cartes (champs minimaux, paginées) — pour la sélection dans CreateTradeScreen.
     * Supporte ?search=xxx et ?per_page=N (max 50).
     */
    public function allCards(Request $request): JsonResponse
    {
        $user    = $request->user();
        $clubId  = $user->club_team_id;
        $search  = $request->query('search');
        $perPage = min((int) ($request->query('per_page', 30)), 50);

        $cards = Card::with(['position', 'rarity', 'clubTeam'])
            ->when($clubId, fn($q) => $q->where('club_team_id', $clubId))
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'cards'        => $cards->map(fn($card) => [
                'id'          => $card->id,
                'name'        => $card->name,
                'position'    => $card->position?->name,
                'team'        => $card->clubTeam?->name,
                'rarity'      => strtolower($card->rarity?->slug ?? 'common'),
                'rarity_label'=> $card->rarity?->name,
                'rarity_color'=> $card->rarity?->color,
                'image'       => $card->image_url,
                'overall'     => $card->overall,
                'number'      => $card->number,
            ]),
            'current_page' => $cards->currentPage(),
            'last_page'    => $cards->lastPage(),
            'total'        => $cards->total(),
        ]);
    }

    public function showCard(Request $request, Card $card): JsonResponse
    {
        $user = $request->user();
        $clubId = $user->club_team_id;
        $userCard = $user->cards()->where('card_id', $card->id)->first();
        $card->load(['position', 'rarity', 'clubTeam']);

        $availableForTrade = Card::whereNotIn('id', $user->cards()->pluck('cards.id'))
            ->with(['position', 'rarity'])
            ->when($clubId, fn($q) => $q->where('club_team_id', $clubId))
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


