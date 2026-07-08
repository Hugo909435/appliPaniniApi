<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Challenge;
use App\Models\User;
use App\Models\UserChallenge;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ChallengeService
{
    public function recordEvent(User $user, string $metric, int $amount = 1): void
    {
        $challenges = Challenge::query()
            ->active()
            ->where('metric', $metric)
            ->get();

        if ($challenges->isEmpty()) {
            return;
        }

        $user->loadMissing('profile');

        foreach ($challenges as $challenge) {
            if (!$this->isChallengeAvailable($challenge)) {
                continue;
            }

            $userChallenge = $this->getOrCreateUserChallenge($user, $challenge);

            if ($challenge->type === Challenge::TYPE_DAILY) {
                $this->syncDailyPeriod($userChallenge);
            }

            if ($challenge->type === Challenge::TYPE_DAILY && $userChallenge->completed_at && $this->isInCurrentPeriod($userChallenge)) {
                continue;
            }

            if ($challenge->metric === Challenge::METRIC_LOGIN_STREAK) {
                $userChallenge->progress = $user->profile?->login_streak ?? 0;
            } else {
                $userChallenge->progress = min($challenge->target, $userChallenge->progress + $amount);
            }

            $userChallenge->last_progress_at = now();

            if ($userChallenge->progress >= $challenge->target && !$userChallenge->completed_at) {
                $userChallenge->completed_at = now();
            }

            $userChallenge->save();
        }
    }

    public function getOrCreateUserChallenge(User $user, Challenge $challenge): UserChallenge
    {
        $userChallenge = UserChallenge::firstOrNew([
            'user_id' => $user->id,
            'challenge_id' => $challenge->id,
        ]);

        if (!$userChallenge->exists) {
            $userChallenge->progress = $this->getInitialProgress($user, $challenge);
            if ($userChallenge->progress >= $challenge->target) {
                $userChallenge->completed_at = now();
            }
        }

        if ($challenge->type === Challenge::TYPE_DAILY) {
            $this->syncDailyPeriod($userChallenge);
        }

        if ($userChallenge->isDirty() || !$userChallenge->exists) {
            $userChallenge->save();
        }

        return $userChallenge;
    }

    private function isChallengeAvailable(Challenge $challenge): bool
    {
        if (!$challenge->is_active) {
            return false;
        }

        if ($challenge->type !== Challenge::TYPE_TIMED) {
            return true;
        }

        $now = now();
        if ($challenge->start_at && $now->lt($challenge->start_at)) {
            return false;
        }
        if ($challenge->end_at && $now->gt($challenge->end_at)) {
            return false;
        }

        return true;
    }

    private function syncDailyPeriod(UserChallenge $userChallenge): void
    {
        $now = now();
        $start = $now->copy()->startOfDay();
        $end = $now->copy()->endOfDay();

        if (!$userChallenge->period_start || !$userChallenge->period_end || !$this->isInCurrentPeriod($userChallenge)) {
            $userChallenge->period_start = $start->toDateString();
            $userChallenge->period_end = $end->toDateString();
            $userChallenge->progress = 0;
            $userChallenge->completed_at = null;
            $userChallenge->claimed_at = null;
        }
    }

    private function isInCurrentPeriod(UserChallenge $userChallenge): bool
    {
        if (!$userChallenge->period_start || !$userChallenge->period_end) {
            return false;
        }

        $start = Carbon::parse($userChallenge->period_start)->startOfDay();
        $end = Carbon::parse($userChallenge->period_end)->endOfDay();

        return now()->betweenIncluded($start, $end);
    }

    private function grantRewards(User $user, Challenge $challenge): void
    {
        $user->loadMissing('profile');

        if ($challenge->reward_xp > 0 && $user->profile) {
            $user->profile->addExperience($challenge->reward_xp);
        }

        if ($challenge->reward_coins > 0) {
            $user->addCoins($challenge->reward_coins);
        }

        if ($challenge->reward_medal_id) {
            $this->grantMedal($user, $challenge->reward_medal_id);
        }

        if ($challenge->reward_card_id) {
            $this->grantCard($user, $challenge->reward_card_id, max(1, $challenge->reward_card_quantity));
        }
    }

    private function grantMedal(User $user, int $medalId): void
    {
        $existingMedal = $user->medals()->where('medal_id', $medalId)->first();
        if ($existingMedal) {
            return;
        }

        $user->medals()->attach($medalId, [
            'earned_at' => now(),
        ]);
    }

    private function grantCard(User $user, int $cardId, int $quantity): void
    {
        $card = Card::find($cardId);
        if (!$card) {
            return;
        }

        $existingCard = $user->cards()->where('card_id', $cardId)->first();
        if ($existingCard) {
            $user->cards()->updateExistingPivot($cardId, [
                'quantity' => $existingCard->pivot->quantity + $quantity,
            ]);
            return;
        }

        $user->cards()->attach($cardId, ['quantity' => $quantity]);
    }

    private function getInitialProgress(User $user, Challenge $challenge): int
    {
        if ($challenge->type !== Challenge::TYPE_PERMANENT) {
            return 0;
        }

        $user->loadMissing('profile');

        return match ($challenge->metric) {
            Challenge::METRIC_PACKS_OPENED => $user->profile?->total_packs_opened ?? 0,
            Challenge::METRIC_TRADES_COMPLETED => $user->profile?->total_trades_completed ?? 0,
            Challenge::METRIC_LOGIN_STREAK => $user->profile?->login_streak ?? 0,
            default => 0,
        };
    }

    public function claimReward(User $user, Challenge $challenge): bool
    {
        $user->loadMissing('profile');

        // Assure l'existence de la ligne avant de la verrouiller.
        $this->getOrCreateUserChallenge($user, $challenge);

        // Verrou pessimiste : sérialise les réclamations concurrentes pour empêcher
        // qu'une même récompense (coins / carte / médaille) soit accordée deux fois.
        return DB::transaction(function () use ($user, $challenge) {
            $userChallenge = UserChallenge::where('user_id', $user->id)
                ->where('challenge_id', $challenge->id)
                ->lockForUpdate()
                ->first();

            if (!$userChallenge) {
                return false;
            }

            if ($challenge->type === Challenge::TYPE_DAILY) {
                $this->syncDailyPeriod($userChallenge);
            }

            if ($challenge->metric === Challenge::METRIC_LOGIN_STREAK) {
                $userChallenge->progress = $user->profile?->login_streak ?? 0;
            }

            if (!$this->isChallengeAvailable($challenge) && !$userChallenge->completed_at) {
                return false;
            }

            $hasCompleted = (bool) $userChallenge->completed_at;

            if (!$hasCompleted && $userChallenge->progress < $challenge->target) {
                $userChallenge->save();
                return false;
            }

            if (!$hasCompleted) {
                $userChallenge->completed_at = now();
            }

            // Déjà réclamé : la ligne est verrouillée, la seconde requête voit claimed_at.
            if ($userChallenge->claimed_at) {
                return false;
            }

            $this->grantRewards($user, $challenge);
            $userChallenge->claimed_at = now();
            $userChallenge->save();

            return true;
        });
    }
}
