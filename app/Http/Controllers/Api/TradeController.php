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
        $trades = Trade::with(['proposer', 'receiver', 'offeredCard.rarity', 'offeredCard.position', 'requestedCard.rarity', 'requestedCard.position'])
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

        $query = Trade::with(['proposer', 'offeredCard.rarity', 'offeredCard.position', 'requestedCard.rarity', 'requestedCard.position'])
            ->where('status', 'pending')
            ->where('proposer_id', '!=', $user->id)
            ->when($user->club_team_id, fn($q) => $q->where('club_team_id', $user->club_team_id))
            ->latest();

        if ($request->filled('rarity')) {
            $slugs = explode(',', $request->rarity);
            $query->whereHas('offeredCard.rarity', fn($q) => $q->whereIn('slug', $slugs));
        }

        $trades = $query->paginate(12)->through(fn($trade) => $this->formatTrade($trade, $user));

        return response()->json(['trades' => $trades]);
    }

    /** Publie une offre 1-contre-1 sur le marché */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'offered_card_id'   => 'required|exists:cards,id',
            'requested_card_id' => 'required|exists:cards,id',
        ]);

        $proposer = $request->user();

        try {
            $trade = $this->tradeService->propose(
                $proposer,
                (int) $validated['offered_card_id'],
                (int) $validated['requested_card_id'],
            );

            return response()->json([
                'message' => 'Offre publiée sur le marché.',
                'trade'   => $this->formatTrade($trade->fresh(['offeredCard.rarity', 'requestedCard.rarity', 'proposer']), $proposer),
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

            return response()->json([
                'message' => 'Échange réalisé avec succès.',
                'trade'   => $this->formatTrade($trade, $request->user()),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** Annule un échange (proposeur uniquement) */
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
        $trades = Trade::with(['proposer', 'receiver', 'offeredCard.rarity', 'requestedCard.rarity'])
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
        return [
            'id'              => $trade->id,
            'status'          => $trade->status,
            'is_proposer'     => $trade->proposer_id === $me->id,
            'proposer'        => ['id' => $trade->proposer?->id, 'name' => $trade->proposer?->name],
            'receiver'        => $trade->receiver ? ['id' => $trade->receiver->id, 'name' => $trade->receiver->name] : null,
            'offered_card'    => $trade->offeredCard ? $this->formatCard($trade->offeredCard) : null,
            'requested_card'  => $trade->requestedCard ? $this->formatCard($trade->requestedCard) : null,
            'expires_at'      => $trade->expires_at?->toISOString(),
            'completed_at'    => $trade->completed_at?->toISOString(),
            'created_at'      => $trade->created_at->toISOString(),
        ];
    }

    private function formatCard($card): array
    {
        return [
            'card_id'      => $card->id,
            'name'         => $card->name,
            'image'        => $card->image_url,
            'position'     => $card->position?->name,
            'rarity'       => $card->rarity?->slug,
            'rarity_label' => $card->rarity?->name,
            'rarity_color' => $card->rarity?->color,
            'overall'      => $card->overall,
        ];
    }
}
