<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Services\ChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ChallengeController extends Controller
{
    public function index(Request $request, ChallengeService $challengeService): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('profile');
        $now = now();

        $challenges = Challenge::query()
            ->active()
            ->with(['rewardMedal', 'rewardCard.rarity'])
            ->get()
            ->filter(function (Challenge $challenge) use ($now) {
                if ($challenge->type !== Challenge::TYPE_TIMED) return true;
                if ($challenge->start_at && $now->lt($challenge->start_at)) return false;
                if ($challenge->end_at && $now->gt($challenge->end_at)) return false;
                return true;
            })
            ->map(function (Challenge $challenge) use ($challengeService, $user, $now) {
                $userChallenge = $challengeService->getOrCreateUserChallenge($user, $challenge);
                $progress = $challenge->metric === Challenge::METRIC_LOGIN_STREAK
                    ? ($user->profile?->login_streak ?? 0)
                    : $userChallenge->progress;
                $target = max(1, $challenge->target);
                if ($userChallenge->completed_at) $progress = $target;
                $percent = min(100, round(($progress / $target) * 100));

                $timeRemaining = null;
                if ($challenge->type === Challenge::TYPE_DAILY && $userChallenge->period_end) {
                    $periodEnd = Carbon::parse($userChallenge->period_end)->endOfDay();
                    $timeRemaining = max(0, $now->diffInSeconds($periodEnd, false));
                }
                if ($challenge->type === Challenge::TYPE_TIMED && $challenge->end_at) {
                    $timeRemaining = max(0, $now->diffInSeconds($challenge->end_at, false));
                }

                return [
                    'id' => $challenge->id,
                    'slug' => $challenge->slug,
                    'name' => $challenge->name,
                    'description' => $challenge->description,
                    'type' => $challenge->type,
                    'metric' => $challenge->metric,
                    'target' => $challenge->target,
                    'progress' => min($progress, $challenge->target),
                    'percent' => $percent,
                    'completed_at' => $userChallenge->completed_at?->toDateTimeString(),
                    'claimed_at' => $userChallenge->claimed_at?->toDateTimeString(),
                    'can_claim' => $userChallenge->completed_at && !$userChallenge->claimed_at,
                    'time_remaining' => $timeRemaining,
                    'reward' => [
                        'xp' => $challenge->reward_xp,
                        'coins' => $challenge->reward_coins,
                        'medal' => $challenge->rewardMedal ? [
                            'id' => $challenge->rewardMedal->id,
                            'name' => $challenge->rewardMedal->name,
                            'image' => $challenge->rewardMedal->image_url,
                            'icon' => $challenge->rewardMedal->icon_url,
                            'rarity' => $challenge->rewardMedal->rarity,
                        ] : null,
                        'card' => $challenge->rewardCard ? [
                            'id' => $challenge->rewardCard->id,
                            'name' => $challenge->rewardCard->name,
                            'image' => $challenge->rewardCard->image_url,
                            'rarity' => $challenge->rewardCard->rarity?->slug,
                        ] : null,
                        'card_quantity' => $challenge->reward_card_quantity,
                    ],
                ];
            })
            ->values()
            ->groupBy('type');

        return response()->json([
            'permanent' => $challenges->get(Challenge::TYPE_PERMANENT, []),
            'timed' => $challenges->get(Challenge::TYPE_TIMED, []),
            'daily' => $challenges->get(Challenge::TYPE_DAILY, []),
        ]);
    }

    public function claim(Request $request, Challenge $challenge, ChallengeService $challengeService): JsonResponse
    {
        $user = $request->user();
        $claimed = $challengeService->claimReward($user, $challenge);

        if (!$claimed) {
            return response()->json(['message' => 'R?compense indisponible ou d?j? r?cup?r?e.'], 400);
        }

        return response()->json(['message' => 'R?compense r?cup?r?e avec succ?s !']);
    }
}
