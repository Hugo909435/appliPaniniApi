<?php

namespace App\Services;

use App\Models\Trade;
use App\Models\TradeItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TradeService
{
    // Durée de validité d'une proposition (en heures)
    const TRADE_TTL_HOURS = 48;

    // Max échanges actifs par utilisateur
    const MAX_ACTIVE_TRADES = 5;

    // Max échanges par jour
    const MAX_DAILY_TRADES = 10;

    /**
     * Publie une offre sur le marché.
     * $offeredItems   = [['card_id' => X, 'quantity' => 1], ...]  (cartes que le proposeur donne)
     * $requestedItems = [['card_id' => Y, 'quantity' => 1], ...]  (cartes que le proposeur veut en retour)
     * Pas de receiver : n'importe quel utilisateur peut accepter.
     */
    public function propose(User $proposer, array $offeredItems, array $requestedItems): Trade
    {
        return DB::transaction(function () use ($proposer, $offeredItems, $requestedItems) {
            // Vérifications de limite DANS la transaction pour éviter le TOCTOU
            $this->checkDailyLimit($proposer);
            $this->checkActiveLimit($proposer);

            // Vérifie et verrouille les cartes offertes
            foreach ($offeredItems as $item) {
                $this->lockCard($proposer, $item['card_id'], $item['quantity'] ?? 1);
            }

            $trade = Trade::create([
                'proposer_id'  => $proposer->id,
                'receiver_id'  => null,
                'club_team_id' => $proposer->club_team_id,
                'status'       => 'pending',
                'expires_at'   => now()->addHours(self::TRADE_TTL_HOURS),
            ]);

            // Cartes offertes — propriétaire connu : le proposeur
            foreach ($offeredItems as $item) {
                TradeItem::create([
                    'trade_id' => $trade->id,
                    'owner_id' => $proposer->id,
                    'card_id'  => $item['card_id'],
                    'quantity' => $item['quantity'] ?? 1,
                ]);
            }

            // Cartes demandées — owner_id null jusqu'à ce qu'un accepteur se manifeste
            foreach ($requestedItems as $item) {
                TradeItem::create([
                    'trade_id' => $trade->id,
                    'owner_id' => null,
                    'card_id'  => $item['card_id'],
                    'quantity' => $item['quantity'] ?? 1,
                ]);
            }

            return $trade->load('items.card.rarity');
        });
    }

    /**
     * N'importe quel utilisateur (sauf le proposeur) accepte l'offre de marché.
     * L'accepteur devient le receiver et doit posséder les cartes demandées.
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
            // Recharge sous lock — idempotence
            $trade = Trade::lockForUpdate()->findOrFail($trade->id);

            if (!$trade->isPending()) {
                throw new \Exception('Cette offre n\'est plus disponible.');
            }
            if ($trade->expires_at && $trade->expires_at->isPast()) {
                $this->unlockTradeCards($trade);
                $trade->update(['status' => 'cancelled']);
                throw new \Exception('Cette offre a expiré.');
            }

            // Re-vérifie que les cartes du proposeur sont toujours verrouillées
            $proposerItems = $trade->items->whereNotNull('owner_id')->where('owner_id', $trade->proposer_id);
            foreach ($proposerItems as $item) {
                $proposerCard = DB::table('user_cards')
                    ->where('user_id', $trade->proposer_id)
                    ->where('card_id', $item->card_id)
                    ->lockForUpdate()
                    ->first();

                if (!$proposerCard) {
                    throw new \Exception("Le proposeur ne possède plus la carte #{$item->card_id}.");
                }
                if (!$proposerCard->is_locked) {
                    throw new \Exception("La carte #{$item->card_id} du proposeur n'est plus réservée.");
                }
                if ($proposerCard->quantity < $item->quantity) {
                    throw new \Exception("Quantité insuffisante pour la carte #{$item->card_id} du proposeur.");
                }
            }

            // Assigne l'accepteur comme receiver sur les cartes demandées
            DB::table('trade_items')
                ->where('trade_id', $trade->id)
                ->whereNull('owner_id')
                ->update(['owner_id' => $acceptor->id]);

            // Recharge les items avec les owner_id mis à jour
            $trade->load('items');

            // Vérifie et verrouille les cartes de l'accepteur
            $acceptorItems = $trade->items->where('owner_id', $acceptor->id);
            foreach ($acceptorItems as $item) {
                $this->lockCard($acceptor, $item->card_id, $item->quantity);
            }

            // Enregistre le receiver sur le trade
            $trade->update(['receiver_id' => $acceptor->id]);

            // Exécute l'échange atomiquement
            $this->executeSwap($trade);

            $trade->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            $this->checkAbuseFlags($trade->proposer_id, $acceptor->id);

            return $trade->fresh('items.card.rarity', 'proposer', 'receiver');
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

            // Idempotence : re-vérifie sous lock (évite cancel sur un échange déjà complété)
            if (!$trade->isActive()) {
                throw new \Exception('Cet échange ne peut plus être annulé.');
            }

            $this->unlockTradeCards($trade);

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
                // Recharge sous lock pour éviter le double-traitement concurrent
                $locked = Trade::lockForUpdate()->find($trade->id);
                if (!$locked || !$locked->isActive()) {
                    return; // déjà traité par un autre process
                }
                $this->unlockTradeCards($locked);
                $locked->update(['status' => 'cancelled']);
                $expired++;
            });
        }
        return $expired;
    }

    // ─── Privé ────────────────────────────────────────────────────────────────

    private function lockCard(User $user, int $cardId, int $quantity): void
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
        if ($userCard->quantity < $quantity) {
            throw new \Exception("Quantité insuffisante pour la carte #{$cardId}.");
        }

        DB::table('user_cards')
            ->where('user_id', $user->id)
            ->where('card_id', $cardId)
            ->update(['is_locked' => true]);
    }

    private function unlockTradeCards(Trade $trade): void
    {
        $trade->loadMissing('items');
        foreach ($trade->items as $item) {
            if ($item->owner_id === null) {
                continue; // pas encore assigné, rien à déverrouiller
            }
            DB::table('user_cards')
                ->where('user_id', $item->owner_id)
                ->where('card_id', $item->card_id)
                ->update(['is_locked' => false]);
        }
    }

    private function executeSwap(Trade $trade): void
    {
        $trade->loadMissing('items');

        foreach ($trade->items as $item) {
            $giverId    = $item->owner_id;
            $receiverId = $giverId === $trade->proposer_id ? $trade->receiver_id : $trade->proposer_id;

            // Retire la carte au donneur (lockForUpdate pour lecture courante)
            $giverCard = DB::table('user_cards')
                ->where('user_id', $giverId)
                ->where('card_id', $item->card_id)
                ->lockForUpdate()
                ->first();

            if (!$giverCard) {
                throw new \Exception("Carte #{$item->card_id} introuvable pour l'utilisateur #{$giverId} au moment du swap.");
            }

            if ($giverCard->quantity <= $item->quantity) {
                DB::table('user_cards')
                    ->where('user_id', $giverId)
                    ->where('card_id', $item->card_id)
                    ->delete();
            } else {
                DB::table('user_cards')
                    ->where('user_id', $giverId)
                    ->where('card_id', $item->card_id)
                    ->update([
                        'quantity'  => $giverCard->quantity - $item->quantity,
                        'is_locked' => false,
                    ]);
            }

            // Donne la carte au receveur (lockForUpdate pour éviter les doublons concurrents)
            $existing = DB::table('user_cards')
                ->where('user_id', $receiverId)
                ->where('card_id', $item->card_id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                DB::table('user_cards')
                    ->where('user_id', $receiverId)
                    ->where('card_id', $item->card_id)
                    ->update(['quantity' => $existing->quantity + $item->quantity]);
            } else {
                DB::table('user_cards')->insert([
                    'user_id'    => $receiverId,
                    'card_id'    => $item->card_id,
                    'quantity'   => $item->quantity,
                    'is_locked'  => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
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

        // Échanges répétés entre les deux mêmes comptes (>5 dans les 7 derniers jours)
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
                return; // idempotent : déjà expiré/annulé
            }
            $this->unlockTradeCards($locked);
            $locked->update(['status' => 'cancelled']);
        });
    }
}
