<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClubMatch;
use App\Models\MatchPrediction;
use App\Models\PredictionWeeklyBonus;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PredictionController extends Controller
{
    public function week(Request $request): JsonResponse
    {
        $user = $request->user();
        $weekStart = $request->filled('week_start')
            ? Carbon::parse($request->week_start)->startOfWeek(Carbon::MONDAY)->startOfDay()
            : now()->startOfWeek(Carbon::MONDAY)->startOfDay();

        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $matches = ClubMatch::with([
            'clubTeam',
            'predictions' => fn($q) => $q->where('user_id', $user->id),
        ])
            ->whereBetween('kickoff_at', [$weekStart, $weekEnd])
            ->orderBy('kickoff_at')
            ->get()
            ->map(function (ClubMatch $match) use ($user) {
                $clubName = $match->clubTeam?->short_name ?: $match->clubTeam?->name;
                $homeName = $match->is_home ? $clubName : $match->opponent_name;
                $awayName = $match->is_home ? $match->opponent_name : $clubName;
                $prediction = $match->predictions->first();

                return [
                    'id' => $match->id,
                    'club_team' => $match->clubTeam ? [
                        'id' => $match->clubTeam->id,
                        'name' => $match->clubTeam->name,
                        'short_name' => $match->clubTeam->short_name,
                    ] : null,
                    'opponent_name' => $match->opponent_name,
                    'location' => $match->location,
                    'kickoff_at' => $match->kickoff_at?->toDateTimeString(),
                    'is_home' => $match->is_home,
                    'home_name' => $homeName,
                    'away_name' => $awayName,
                    'home_score' => $match->home_score,
                    'away_score' => $match->away_score,
                    'result_outcome' => $match->result_outcome,
                    'can_predict' => $match->kickoff_at?->isFuture() ?? false,
                    'user_prediction' => $prediction?->predicted_outcome,
                ];
            });

        $bonusAwarded = PredictionWeeklyBonus::where('user_id', $user->id)
            ->where('week_start_date', $weekStart->toDateString())
            ->exists();

        return response()->json([
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'bonus_awarded' => $bonusAwarded,
            'matches' => $matches,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'match_id' => 'required|exists:club_matches,id',
            'predicted_outcome' => 'required|in:home,draw,away',
        ]);

        $match = ClubMatch::findOrFail($validated['match_id']);
        if ($match->kickoff_at && $match->kickoff_at->isPast()) {
            return response()->json(['message' => "Le match a déjà commencé."], 422);
        }

        $prediction = MatchPrediction::updateOrCreate(
            ['club_match_id' => $match->id, 'user_id' => $user->id],
            ['predicted_outcome' => $validated['predicted_outcome']]
        );

        $bonusAwarded = $this->awardWeeklyBonusIfEligible($user->id, $match->kickoff_at);

        return response()->json([
            'message' => 'Pronostic enregistré.',
            'prediction' => [
                'id' => $prediction->id,
                'match_id' => $prediction->club_match_id,
                'predicted_outcome' => $prediction->predicted_outcome,
            ],
            'bonus_awarded' => $bonusAwarded,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = ClubMatch::with([
            'clubTeam',
            'predictions' => fn($q) => $q->where('user_id', $user->id),
        ])
            ->whereNotNull('result_outcome')
            ->orderByDesc('kickoff_at');

        if ($request->filled('week_start')) {
            $weekStart = Carbon::parse($request->week_start)->startOfWeek(Carbon::MONDAY)->startOfDay();
            $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
            $query->whereBetween('kickoff_at', [$weekStart, $weekEnd]);
        }

        $matches = $query->get()
            ->filter(fn($match) => $match->predictions->isNotEmpty())
            ->map(function (ClubMatch $match) {
                $clubName = $match->clubTeam?->short_name ?: $match->clubTeam?->name;
                $homeName = $match->is_home ? $clubName : $match->opponent_name;
                $awayName = $match->is_home ? $match->opponent_name : $clubName;
                $prediction = $match->predictions->first();
                $correct = $prediction && $prediction->predicted_outcome === $match->result_outcome;

                return [
                    'id' => $match->id,
                    'club_team' => $match->clubTeam ? [
                        'id' => $match->clubTeam->id,
                        'name' => $match->clubTeam->name,
                        'short_name' => $match->clubTeam->short_name,
                    ] : null,
                    'opponent_name' => $match->opponent_name,
                    'location' => $match->location,
                    'kickoff_at' => $match->kickoff_at?->toDateTimeString(),
                    'is_home' => $match->is_home,
                    'home_name' => $homeName,
                    'away_name' => $awayName,
                    'home_score' => $match->home_score,
                    'away_score' => $match->away_score,
                    'result_outcome' => $match->result_outcome,
                    'user_prediction' => $prediction?->predicted_outcome,
                    'is_correct' => $correct,
                ];
            })
            ->values();

        return response()->json(['matches' => $matches]);
    }

    public function leaderboard(Request $request): JsonResponse
    {
        $scope = $request->input('scope', 'week');
        $weekStart = null;
        $weekEnd = null;

        if ($scope !== 'global') {
            $weekStart = $request->filled('week_start')
                ? Carbon::parse($request->week_start)->startOfWeek(Carbon::MONDAY)->startOfDay()
                : now()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
        }

        $rows = MatchPrediction::query()
            ->select([
                'match_predictions.user_id',
                DB::raw("SUM(CASE WHEN match_predictions.predicted_outcome = club_matches.result_outcome THEN 1 ELSE 0 END) AS correct"),
                DB::raw("COUNT(*) AS total"),
            ])
            ->join('club_matches', 'club_matches.id', '=', 'match_predictions.club_match_id')
            ->whereNotNull('club_matches.result_outcome');

        if ($weekStart && $weekEnd) {
            $rows->whereBetween('club_matches.kickoff_at', [$weekStart, $weekEnd]);
        }

        $rows = $rows
            ->groupBy('match_predictions.user_id')
            ->orderByDesc('correct')
            ->orderByDesc('total')
            ->get();

        $userIds = $rows->pluck('user_id')->all();
        $users = \App\Models\User::whereIn('id', $userIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        $rank = 1;
        $leaderboard = $rows->map(function ($row) use ($users, &$rank) {
            $user = $users->get($row->user_id);
            $correct = (int) $row->correct;
            $total = (int) $row->total;
            $entry = [
                'rank' => $rank,
                'user_id' => $row->user_id,
                'name' => $user?->name ?? 'Utilisateur',
                'correct' => $correct,
                'total' => $total,
                'points' => $correct * 50,
            ];
            $rank++;
            return $entry;
        });

        return response()->json([
            'scope' => $scope,
            'week_start' => $weekStart?->toDateString(),
            'week_end' => $weekEnd?->toDateString(),
            'leaderboard' => $leaderboard,
        ]);
    }

    private function awardWeeklyBonusIfEligible(int $userId, ?Carbon $kickoffAt): bool
    {
        if (!$kickoffAt) return false;

        $weekStart = $kickoffAt->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $matchesCount = ClubMatch::whereBetween('kickoff_at', [$weekStart, $weekEnd])->count();
        if ($matchesCount === 0) return false;

        $predictionsCount = MatchPrediction::where('user_id', $userId)
            ->whereHas('match', fn($q) => $q->whereBetween('kickoff_at', [$weekStart, $weekEnd]))
            ->count();

        if ($predictionsCount < $matchesCount) return false;

        return DB::transaction(function () use ($userId, $weekStart) {
            $existing = PredictionWeeklyBonus::where('user_id', $userId)
                ->where('week_start_date', $weekStart->toDateString())
                ->lockForUpdate()
                ->first();

            if ($existing) return false;

            $bonus = PredictionWeeklyBonus::create([
                'user_id' => $userId,
                'week_start_date' => $weekStart->toDateString(),
                'awarded_at' => now(),
            ]);

            if ($bonus) {
                \App\Models\User::where('id', $userId)->increment('coins', 10);
                return true;
            }

            return false;
        });
    }
}
