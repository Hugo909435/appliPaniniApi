<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trade;
use App\Models\User;
use App\Services\TradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradeController extends Controller
{
    public function __construct(protected TradeService $tradeService) {}

    /** Liste les échanges actifs proposés à l'utilisateur ou par lui */
    public function index(Request $request): JsonResponse
    {
        $user   = $request->user();
        $trades = Trade::with(['proposer', 'receiver', 'items.card.rarity', 'items.card.position'])
            ->forUser($user->id)
            ->active()
            ->when($user->club_team_id, fn($q) => $q->where('club_team_id', $user->club_team_id))
            ->latest()
            ->paginate(15)
            ->through(fn($trade) => $this->formatTrade($trade, $user));

        return response()->json(['trades' => $trades]);
    }

    /** Liste les échanges en attente (marketplace) — tous sauf les propres offres de l'utilisateur */
    public function marketplace(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Trade::with(['proposer', 'items.card.rarity', 'items.card.position'])
            ->where('status', 'pending')
            ->where('proposer_id', '!=', $user->id)
            ->when($user->club_team_id, fn($q) => $q->where('club_team_id', $user->club_team_id))
            ->latest();

        if ($request->filled('rarity')) {
            $query->whereHas('items.card.rarity', fn($q) => $q->whereIn('slug', explode(',', $request->rarity)));
        }

        $trades = $query->paginate(12)->through(fn($trade) => $this->formatTrade($trade, $user));

        return response()->json(['trades' => $trades]);
    }

    /** Publie une offre sur le marché */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'offered_items'             => 'required|array|min:1|max:5',
            'offered_items.*.card_id'   => 'required|exists:cards,id',
            'offered_items.*.quantity'  => 'integer|min:1|max:10',
            'requested_items'             => 'required|array|min:1|max:5',
            'requested_items.*.card_id'   => 'required|exists:cards,id',
            'requested_items.*.quantity'  => 'integer|min:1|max:10',
        ]);

        $proposer = $request->user();

        try {
            $trade = $this->tradeService->propose(
                $proposer,
                $validated['offered_items'],
                $validated['requested_items'],
            );

            return response()->json([
                'message' => 'Offre publiée sur le marché.',
                'trade'   => $this->formatTrade($trade->fresh('items.card.rarity', 'proposer'), $proposer),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** Accepter une offre du marché */
    public function accept(Request $request, Trade $trade): JsonResponse
    {
        try {
            $trade = $this->tradeService->accept($request->user(), $trade);

            $flags = $this->tradeService->consumePendingFlags();
            if (!empty($flags)) {
                $trade->update(['flagged' => true, 'flag_reason' => implode(', ', $flags)]);
            }

            return response()->json(['message' => 'Échange réalisé avec succès.', 'trade' => $this->formatTrade($trade, $request->user())]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** Annule un échange (proposer ou receiver) */
    public function cancel(Request $request, Trade $trade): JsonResponse
    {
        try {
            $this->tradeService->cancel($request->user(), $trade);
            return response()->json(['message' => 'Échange annulé.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** Historique complet de l'utilisateur */
    public function history(Request $request): JsonResponse
    {
        $user   = $request->user();
        $trades = Trade::with(['proposer', 'receiver', 'items.card.rarity'])
            ->forUser($user->id)
            ->whereIn('status', ['completed', 'cancelled', 'rejected'])
            ->when($user->club_team_id, fn($q) => $q->where('club_team_id', $user->club_team_id))
            ->latest('completed_at')
            ->paginate(20)
            ->through(fn($trade) => $this->formatTrade($trade, $user));

        return response()->json(['trades' => $trades]);
    }

    // ─── Format ───────────────────────────────────────────────────────────────

    private function formatTrade(Trade $trade, User $me): array
    {
        // offered_items = cartes que le proposeur donne (owner_id = proposer)
        // requested_items = cartes que le proposeur veut (owner_id = null avant acceptation, receiver après)
        $offeredItems   = $trade->items->where('owner_id', $trade->proposer_id)->values();
        $requestedItems = $trade->items->filter(fn($i) => $i->owner_id !== $trade->proposer_id)->values();

        return [
            'id'              => $trade->id,
            'status'          => $trade->status,
            'is_proposer'     => $trade->proposer_id === $me->id,
            'proposer'        => ['id' => $trade->proposer?->id, 'name' => $trade->proposer?->name],
            'receiver'        => $trade->receiver ? ['id' => $trade->receiver->id, 'name' => $trade->receiver->name] : null,
            'offered_items'   => $offeredItems->map(fn($i) => $this->formatItem($i)),
            'requested_items' => $requestedItems->map(fn($i) => $this->formatItem($i)),
            'expires_at'      => $trade->expires_at?->toISOString(),
            'completed_at'    => $trade->completed_at?->toISOString(),
            'created_at'      => $trade->created_at->toISOString(),
        ];
    }

    private function formatItem($item): array
    {
        $card = $item->card;
        return [
            'card_id'      => $card->id,
            'name'         => $card->name,
            'image'        => $card->image_url,
            'position'     => $card->position?->name,
            'rarity'       => $card->rarity?->slug,
            'rarity_label' => $card->rarity?->name,
            'rarity_color' => $card->rarity?->color,
            'overall'      => $card->overall,
            'quantity'     => $item->quantity,
        ];
    }
}
