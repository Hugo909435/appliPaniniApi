<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Pack;
use App\Models\Position;
use App\Models\Rarity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CollectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $clubId = $user->club_team_id;

        $ownedCards = $user->cards()
            ->with(['position', 'rarity', 'clubTeam', 'pack'])
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
                'pack_id' => $card->pack_id,
                'pack_name' => $card->pack?->name,
            ]);

        $allCards = Card::with(['position', 'rarity', 'clubTeam', 'pack'])
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
                'pack_id' => $card->pack_id,
                'pack_name' => $card->pack?->name,
            ]);

        // Référentiels mis en cache 10 min
        $rarities = Cache::remember('ref:rarities:v1', 600, fn () => Rarity::select('id', 'slug', 'name', 'color')->orderBy('id')->get());
        $positions = Cache::remember('ref:positions:v1', 600, fn () => Position::select('id', 'name')->orderBy('name')->get());
        $packs = Cache::remember("ref:packs:club:" . ($clubId ?? 'all') . ":v1", 600, fn () =>
            Pack::when($clubId, fn($q) => $q->where('club_team_id', $clubId))
                ->orderByDesc('id')
                ->get(['id', 'name'])
        );

        return response()->json([
            'cards' => $ownedCards,
            'all_cards' => $allCards,
            'rarities' => $rarities,
            'positions' => $positions,
            'packs' => $packs->map(fn($p) => ['id' => $p->id, 'name' => $p->name]),
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

        // Suggestions de cartes non possédées : requête NOT EXISTS + projection légère
        $availableForTrade = Card::select(['id', 'name', 'positions_id', 'rarities_id', 'club_team_id', 'image'])
            ->with(['position:id,name', 'rarity:id,slug', 'clubTeam:id,name'])
            ->when($clubId, fn($q) => $q->where('club_team_id', $clubId))
            ->whereDoesntHave('userCards', fn($q) => $q->where('user_id', $user->id))
            ->orderBy('id')
            ->limit(50)
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


