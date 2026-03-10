<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\Trade;
use App\Services\ChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TradeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Trade::with([
            'creator',
            'offeredCard.position', 'offeredCard.rarity',
            'requestedCard.position', 'requestedCard.rarity',
        ])->pending()->where('creator_id', '!=', $user->id)->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('offeredCard', fn($q) => $q->where('name', 'like', "%{$search}%"));
        }
        if ($request->filled('rarity')) {
            $rarityIds = explode(',', $request->rarity);
            $query->whereHas('offeredCard.rarity', fn($q) => $q->whereIn('slug', $rarityIds));
        }
        $trades = $query->paginate(12)->through(fn($trade) => [
            'id' => $trade->id,
            'status' => $trade->status,
            'can_accept' => $trade->canAccept($user),
            'user_has_offered_card' => $trade->userHasOfferedCard($user),
            'creator' => ['name' => $trade->creator->name],
            'offered_card' => $this->formatCard($trade->offeredCard),
            'requested_card' => $this->formatCard($trade->requestedCard),
            'created_at' => $trade->created_at->diffForHumans(),
        ]);

        return response()->json([
            'trades' => $trades,
            'filter_config' => $this->getFilterConfig(),
        ]);
    }

    public function myTrades(Request $request): JsonResponse
    {
        $user = $request->user();

        $trades = Trade::with([
            'creator', 'responder',
            'offeredCard.position', 'offeredCard.rarity',
            'requestedCard.position', 'requestedCard.rarity',
        ])->forUser($user->id)->latest()->paginate(12)->through(function ($trade) use ($user) {
            $isCreator = $trade->creator_id === $user->id;
            return [
                'id' => $trade->id,
                'status' => $trade->status,
                'is_creator' => $isCreator,
                'can_cancel' => $trade->status === 'pending' && $isCreator,
                'creator' => ['name' => $trade->creator->name],
                'responder' => $trade->responder ? ['name' => $trade->responder->name] : null,
                'offered_card' => $this->formatCard($trade->offeredCard),
                'requested_card' => $this->formatCard($trade->requestedCard),
                'created_at' => $trade->created_at->diffForHumans(),
                'completed_at' => $trade->completed_at?->diffForHumans(),
            ];
        });

        return response()->json(['trades' => $trades]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'offered_card_id' => 'required|exists:cards,id',
            'requested_card_id' => 'required|exists:cards,id|different:offered_card_id',
        ]);

        $user = $request->user();

        if (!$user->cards()->where('card_id', $validated['offered_card_id'])->exists()) {
            return response()->json(['message' => 'Vous ne possédez pas cette carte.'], 422);
        }

        $existingTrade = Trade::where('creator_id', $user->id)
            ->where('offered_card_id', $validated['offered_card_id'])
            ->where('requested_card_id', $validated['requested_card_id'])
            ->where('status', 'pending')->exists();

        if ($existingTrade) {
            return response()->json(['message' => 'Vous avez déjà une offre identique en cours.'], 422);
        }

        $trade = Trade::create([
            'creator_id' => $user->id,
            'offered_card_id' => $validated['offered_card_id'],
            'requested_card_id' => $validated['requested_card_id'],
        ]);

        return response()->json(['trade' => $trade, 'message' => 'Offre créée avec succès.'], 201);
    }

    public function accept(Trade $trade, Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$trade->canAccept($user)) {
            return response()->json(['message' => 'Vous ne pouvez pas accepter cet échange.'], 403);
        }

        DB::transaction(function () use ($trade, $user) {
            $trade->creator->cards()->detach($trade->offered_card_id);

            $existingCard = $user->cards()->where('card_id', $trade->offered_card_id)->first();
            if ($existingCard) {
                $user->cards()->updateExistingPivot($trade->offered_card_id, ['quantity' => $existingCard->pivot->quantity + 1]);
            } else {
                $user->cards()->attach($trade->offered_card_id, ['quantity' => 1]);
            }

            $user->cards()->detach($trade->requested_card_id);

            $creatorHasCard = $trade->creator->cards()->where('card_id', $trade->requested_card_id)->first();
            if ($creatorHasCard) {
                $trade->creator->cards()->updateExistingPivot($trade->requested_card_id, ['quantity' => $creatorHasCard->pivot->quantity + 1]);
            } else {
                $trade->creator->cards()->attach($trade->requested_card_id, ['quantity' => 1]);
            }

            $trade->update(['status' => 'accepted', 'responder_id' => $user->id, 'completed_at' => now()]);
        });

        foreach ([$user, $trade->creator] as $participant) {
            if ($participant?->profile) {
                $participant->profile->incrementTradesCompleted();
            }
            app(ChallengeService::class)->recordEvent($participant, Challenge::METRIC_TRADES_COMPLETED);
        }

        return response()->json(['message' => 'Échange accepté avec succès.']);
    }

    public function cancel(Trade $trade, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($trade->creator_id !== $user->id || $trade->status !== 'pending') {
            return response()->json(['message' => 'Vous ne pouvez pas annuler cet échange.'], 403);
        }

        $trade->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Offre annulée avec succès.']);
    }

    private function formatCard($card): array
    {
        return [
            'id' => $card->id,
            'name' => $card->name,
            'image' => $card->image_url,
            'position' => $card->position?->name,
            'rarity' => $card->rarity?->slug,
            'overall' => $card->overall,
        ];
    }

    private function getFilterConfig(): array
    {
        $rarities = \App\Models\Rarity::orderByRaw("CASE slug WHEN 'common' THEN 1 WHEN 'uncommon' THEN 2 WHEN 'rare' THEN 3 WHEN 'epic' THEN 4 WHEN 'legendary' THEN 5 ELSE 99 END")->get();
        
        return [
            'rarities' => $rarities->map(fn($r) => ['label' => $r->name, 'value' => $r->slug]),
        ];
    }
}

