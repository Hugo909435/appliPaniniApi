<?php

namespace App\Services;

use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TradeService
{
    const TRADE_TTL_MONTHS = 2;
    const MAX_ACTIVE_TRADES = 5;
    const MAX_DAILY_TRADES = 10;

    /**
     * Publie une offre 1-contre-1 sur le marché.
     * $offeredCardId  = carte que le proposeur donne (qu'il possède)
     * $requestedCardId = carte que le proposeur veut recevoir
     */
    public function propose(User $proposer, int $offeredCardId, int $requestedCardId): Trade
    {
        return DB::transaction(function () use ($proposer, $offeredCardId, $requestedCardId) {
            $this->checkDailyLimit($proposer);
            $this->checkActiveLimit($proposer);

            $this->lockCard($proposer, $offeredCardId);

            $trade = Trade::create([
                'proposer_id'      => $proposer->id,
                'receiver_id'      => null,
                'club_team_id'     => $proposer->club_team_id,
                'status'           => 'pending',
                'offered_card_id'  => $offeredCardId,
                'requested_card_id'=> $requestedCardId,
                'expires_at'       => now()->addMonths(self::TRADE_TTL_MONTHS),
            ]);

            return $trade->load(['offeredCard.rarity', 'requestedCard.rarity']);
        });
    }

    /**
     * N'importe quel utilisateur (sauf le proposeur) accepte l'offre.
     * L'accepteur doit posséder la carte demandée.
     */
    public function accept(User $acceptor, Trade $trade): Trade
    {
        if ($trade->proposer_id === $acceptor->id) {
            throw new \Exception('Vous ne pouvez pas accepter votre propre offre.');
        }
        if (!$trade->isPending()) {
            throw new \Exception('Cette offre n\'est plus disponible.');
        }
        if ($trade->expires_at && $trade->expires_at->isPast()) {
            $this->expire($trade);
            throw new \Exception('Cette offre a expiré.');
        }

        return DB::transaction(function () use ($acceptor, $trade) {
            $trade = Trade::lockForUpdate()->findOrFail($trade->id);

            if (!$trade->isPending()) {
                throw new \Exception('Cette offre n\'est plus disponible.');
            }
            if ($trade->expires_at && $trade->expires_at->isPast()) {
                $this->unlockOfferedCard($trade);
                $trade->update(['status' => 'cancelled']);
                throw new \Exception('Cette offre a expiré.');
            }

            // Re-vérifie que la carte offerte du proposeur est toujours verrouillée
            $proposerCard = DB::table('user_cards')
                ->where('user_id', $trade->proposer_id)
                ->where('card_id', $trade->offered_card_id)
                ->lockForUpdate()
                ->first();

            if (!$proposerCard || !$proposerCard->is_locked) {
                throw new \Exception('La carte proposée n\'est plus disponible.');
            }

            // Vérifie et verrouille la carte de l'accepteur
            $this->lockCard($acceptor, $trade->requested_card_id);

            $trade->update(['receiver_id' => $acceptor->id]);

            // Exécute l'échange
            $this->executeSwap($trade, $acceptor);

            $trade->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            $this->checkAbuseFlags($trade->proposer_id, $acceptor->id);

            return $trade->fresh(['offeredCard.rarity', 'requestedCard.rarity', 'proposer', 'receiver']);
        });
    }

    /**
     * Retire une offre du marché (proposeur uniquement).
     */
    public function cancel(User $user, Trade $trade): Trade
    {
        if ($trade->proposer_id !== $user->id) {
            throw new \Exception('Seul le créateur de l\'offre peut la retirer.');
        }
        if (!$trade->isActive()) {
            throw new \Exception('Cet échange ne peut plus être annulé.');
        }

        return DB::transaction(function () use ($trade) {
            $trade = Trade::lockForUpdate()->findOrFail($trade->id);

            if (!$trade->isActive()) {
                throw new \Exception('Cet échange ne peut plus être annulé.');
            }

            $this->unlockOfferedCard($trade);
            $trade->update(['status' => 'cancelled']);

            return $trade;
        });
    }

    /**
     * Expire les échanges périmés (à appeler en job planifié).
     */
    public function expireStale(): int
    {
        $stale = Trade::active()->where('expires_at', '<', now())->get();
        $expired = 0;
        foreach ($stale as $trade) {
            DB::transaction(function () use ($trade, &$expired) {
                $locked = Trade::lockForUpdate()->find($trade->id);
                if (!$locked || !$locked->isActive()) {
                    return;
                }
                $this->unlockOfferedCard($locked);
                $locked->update(['status' => 'cancelled']);
                $expired++;
            });
        }
        return $expired;
    }

    // ─── Privé ────────────────────────────────────────────────────────────────

    private function lockCard(User $user, int $cardId): void
    {
        $userCard = DB::table('user_cards')
            ->where('user_id', $user->id)
            ->where('card_id', $cardId)
            ->lockForUpdate()
            ->first();

        if (!$userCard) {
            throw new \Exception("Vous ne possédez pas la carte #{$cardId}.");
        }
        if ($userCard->is_locked) {
            throw new \Exception("La carte #{$cardId} est déjà en cours d'échange.");
        }
        if ($userCard->quantity < 1) {
            throw new \Exception("Quantité insuffisante pour la carte #{$cardId}.");
        }

        DB::table('user_cards')
            ->where('user_id', $user->id)
            ->where('card_id', $cardId)
            ->update(['is_locked' => true]);
    }

    private function unlockOfferedCard(Trade $trade): void
    {
        if (!$trade->offered_card_id) {
            return;
        }
        DB::table('user_cards')
            ->where('user_id', $trade->proposer_id)
            ->where('card_id', $trade->offered_card_id)
            ->update(['is_locked' => false]);
    }

    private function executeSwap(Trade $trade, User $acceptor): void
    {
        // Proposer gives offered_card → acceptor
        $this->transferCard(
            giverId: $trade->proposer_id,
            receiverId: $acceptor->id,
            cardId: $trade->offered_card_id,
        );

        // Acceptor gives requested_card → proposer
        $this->transferCard(
            giverId: $acceptor->id,
            receiverId: $trade->proposer_id,
            cardId: $trade->requested_card_id,
        );
    }

    private function transferCard(int $giverId, int $receiverId, int $cardId): void
    {
        $giverCard = DB::table('user_cards')
            ->where('user_id', $giverId)
            ->where('card_id', $cardId)
            ->lockForUpdate()
            ->first();

        if (!$giverCard) {
            throw new \Exception("Carte #{$cardId} introuvable pour l'utilisateur #{$giverId}.");
        }

        if ($giverCard->quantity <= 1) {
            DB::table('user_cards')
                ->where('user_id', $giverId)
                ->where('card_id', $cardId)
                ->delete();
        } else {
            DB::table('user_cards')
                ->where('user_id', $giverId)
                ->where('card_id', $cardId)
                ->update([
                    'quantity'  => $giverCard->quantity - 1,
                    'is_locked' => false,
                ]);
        }

        $existing = DB::table('user_cards')
            ->where('user_id', $receiverId)
            ->where('card_id', $cardId)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            DB::table('user_cards')
                ->where('user_id', $receiverId)
                ->where('card_id', $cardId)
                ->update(['quantity' => $existing->quantity + 1]);
        } else {
            DB::table('user_cards')->insert([
                'user_id'    => $receiverId,
                'card_id'    => $cardId,
                'quantity'   => 1,
                'is_locked'  => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function checkDailyLimit(User $user): void
    {
        $count = Trade::where('proposer_id', $user->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        if ($count >= self::MAX_DAILY_TRADES) {
            throw new \Exception('Limite journalière d\'échanges atteinte (' . self::MAX_DAILY_TRADES . '/jour).');
        }
    }

    private function checkActiveLimit(User $user): void
    {
        $count = Trade::where('proposer_id', $user->id)->active()->count();

        if ($count >= self::MAX_ACTIVE_TRADES) {
            throw new \Exception('Vous avez déjà ' . self::MAX_ACTIVE_TRADES . ' échanges en attente.');
        }
    }

    private function checkAbuseFlags(int $proposerId, int $acceptorId): void
    {
        $flags = [];

        $proposer = User::find($proposerId);
        $acceptor = User::find($acceptorId);

        if ($proposer && $acceptor && $proposer->created_at->isSameDay($acceptor->created_at)) {
            $flags[] = 'comptes créés le même jour';
        }

        $recentCount = Trade::where(function ($q) use ($proposerId, $acceptorId) {
            $q->where('proposer_id', $proposerId)->where('receiver_id', $acceptorId);
        })->orWhere(function ($q) use ($proposerId, $acceptorId) {
            $q->where('proposer_id', $acceptorId)->where('receiver_id', $proposerId);
        })->where('created_at', '>=', now()->subDays(7))->count();

        if ($recentCount > 5) {
            $flags[] = 'échanges répétés entre les mêmes comptes';
        }

        if (!empty($flags)) {
            $this->pendingFlags = $flags;
        }
    }

    private array $pendingFlags = [];

    public function consumePendingFlags(): array
    {
        $flags = $this->pendingFlags;
        $this->pendingFlags = [];
        return $flags;
    }

    private function expire(Trade $trade): void
    {
        DB::transaction(function () use ($trade) {
            $locked = Trade::lockForUpdate()->find($trade->id);
            if (!$locked || !$locked->isActive()) {
                return;
            }
            $this->unlockOfferedCard($locked);
            $locked->update(['status' => 'cancelled']);
        });
    }
}
