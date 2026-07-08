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
    // ─── Récompenses individuelles ──────────────────────────────────────────────
    const COINS_EXACT   = 75;
    const COINS_WINNER  = 30;
    const XP_EXACT      = 50;
    const XP_WINNER     = 20;

    // ─── weeks ──────────────────────────────────────────────────────────────────
    public function weeks(Request $request): JsonResponse
    {
        $weeks = \App\Models\MatchWeek::withCount('matches')
            ->orderByDesc('number')
            ->get()
            ->map(fn($w) => [
                'id'          => $w->id,
                'number'      => $w->number,
                'start_date'  => $w->start_date->format('Y-m-d'),
                'end_date'    => $w->end_date->format('Y-m-d'),
                'label'       => $w->label,
                'match_count' => $w->matches_count,
            ]);

        $now     = now();
        $current = \App\Models\MatchWeek::where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->first();

        return response()->json([
            'weeks'           => $weeks,
            'current_week_id' => $current?->id,
        ]);
    }

    // ─── week ────────────────────────────────────────────────────────────────────
    public function week(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($request->filled('week_id')) {
            $matchWeek = \App\Models\MatchWeek::find($request->week_id);
        } else {
            $now = now();
            $matchWeek = \App\Models\MatchWeek::where('start_date', '<=', $now)
                ->where('end_date', '>=', $now)
                ->first()
                ?? \App\Models\MatchWeek::orderByDesc('number')->first();
        }

        if (!$matchWeek) {
            return response()->json([
                'week_id'       => null,
                'week_number'   => null,
                'week_start'    => null,
                'week_end'      => null,
                'week_label'    => null,
                'bonus_awarded' => false,
                'matches'       => [],
            ]);
        }

        $weekStart = $matchWeek->start_date->startOfDay();
        $weekEnd   = $matchWeek->end_date->endOfDay();

        $clubIds = $this->allowedClubIds($user);

        $matches = ClubMatch::with([
            'clubTeam',
            'matchWeek',
            'predictions' => fn($q) => $q->where('user_id', $user->id),
        ])
            ->when($clubIds, fn($q) => $q->whereIn('club_team_id', $clubIds))
            ->where(function ($q) use ($matchWeek, $weekStart, $weekEnd) {
                $q->where('match_week_id', $matchWeek->id)
                  ->orWhere(function ($q) use ($weekStart, $weekEnd) {
                      $q->whereNull('match_week_id')
                        ->whereBetween('kickoff_at', [$weekStart, $weekEnd]);
                  });
            })
            ->orderBy('kickoff_at')
            ->get()
            ->map(function (ClubMatch $match) use ($user) {
                $now        = now();
                $weekStart2 = $match->matchWeek?->start_date?->copy()->startOfDay();
                if (!$weekStart2 && $match->kickoff_at) {
                    $weekStart2 = $match->kickoff_at->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
                }
                $predictClose  = $weekStart2?->copy()->addDays(4)->setTime(12, 0);
                $withinWindow  = $weekStart2 && $predictClose
                    ? ($now->greaterThanOrEqualTo($weekStart2) && $now->lessThanOrEqualTo($predictClose))
                    : false;
                $clubName  = $match->clubTeam?->short_name ?: $match->clubTeam?->name;
                $homeName  = $match->is_home ? $clubName : $match->opponent_name;
                $awayName  = $match->is_home ? $match->opponent_name : $clubName;
                $prediction = $match->predictions->first();

                return [
                    'id'             => $match->id,
                    'club_team'      => $match->clubTeam ? [
                        'id'         => $match->clubTeam->id,
                        'name'       => $match->clubTeam->name,
                        'short_name' => $match->clubTeam->short_name,
                    ] : null,
                    'opponent_name'  => $match->opponent_name,
                    'location'       => $match->location,
                    'kickoff_at'     => $match->kickoff_at?->toDateTimeString(),
                    'is_home'        => $match->is_home,
                    'home_name'      => $homeName,
                    'away_name'      => $awayName,
                    'home_score'     => $match->home_score,
                    'away_score'     => $match->away_score,
                    'result_outcome' => $match->result_outcome,
                    'is_cancelled'   => $match->is_cancelled,
                    'can_predict'    => !$match->is_cancelled && $withinWindow && ($match->kickoff_at?->isFuture() ?? false) && $prediction === null,
                    'user_prediction'=> $prediction?->predicted_outcome,
                    'user_predicted_home_score' => $prediction?->predicted_home_score,
                    'user_predicted_away_score' => $prediction?->predicted_away_score,
                    'is_double'      => $prediction?->is_double ?? false,
                ];
            });

        $bonusAwarded = PredictionWeeklyBonus::where('user_id', $user->id)
            ->where('week_start_date', $weekStart->toDateString())
            ->exists();

        return response()->json([
            'week_id'       => $matchWeek->id,
            'week_number'   => $matchWeek->number,
            'week_start'    => $weekStart->toDateString(),
            'week_end'      => $weekEnd->toDateString(),
            'week_label'    => $matchWeek->label,
            'bonus_awarded' => $bonusAwarded,
            'matches'       => $matches,
        ]);
    }

    // ─── store ───────────────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $user      = $request->user();
        $validated = $request->validate([
            'match_id'             => 'required|exists:club_matches,id',
            'predicted_home_score' => 'required|integer|min:0|max:99',
            'predicted_away_score' => 'required|integer|min:0|max:99',
            'is_double'            => 'sometimes|boolean',
        ]);

        $match  = ClubMatch::findOrFail($validated['match_id']);
        $allowedClubIds = $this->allowedClubIds($user);
        if ($allowedClubIds && !in_array($match->club_team_id, $allowedClubIds, true)) {
            return response()->json(['message' => "Match non autorisÃ© pour ton club."], 403);
        }
        $window = $this->predictionWindowForMatch($match);
        if ($window) {
            [$windowStart, $windowClose] = $window;
            $now = now();
            if ($now->lessThan($windowStart)) {
                return response()->json(['message' => "Les pronostics ouvrent le lundi."], 422);
            }
            if ($now->greaterThan($windowClose)) {
                return response()->json(['message' => "Les pronostics sont clos (vendredi midi)."], 422);
            }
        }
        if ($match->is_cancelled) {
            return response()->json(['message' => "Le match est annulé."], 422);
        }
        if ($match->kickoff_at && $match->kickoff_at->isPast()) {
            return response()->json(['message' => "Le match a déjà commencé."], 422);
        }

        $predictedOutcome = $validated['predicted_home_score'] > $validated['predicted_away_score']
            ? 'home'
            : ($validated['predicted_home_score'] < $validated['predicted_away_score'] ? 'away' : 'draw');

        $isDouble = $validated['is_double'] ?? false;

        $existing = MatchPrediction::where('club_match_id', $match->id)
            ->where('user_id', $user->id)
            ->exists();
        if ($existing) {
            return response()->json(['message' => "Tu as déjà pronostiqué ce match, impossible de modifier."], 422);
        }

        $prediction = MatchPrediction::create([
            'club_match_id'        => $match->id,
            'user_id'              => $user->id,
            'predicted_outcome'    => $predictedOutcome,
            'predicted_home_score' => $validated['predicted_home_score'],
            'predicted_away_score' => $validated['predicted_away_score'],
            'is_double'            => $isDouble,
        ]);

        if ($isDouble) {
            $weekId = $match->match_week_id;
            if ($weekId) {
                MatchPrediction::where('user_id', $user->id)
                    ->where('club_match_id', '!=', $match->id)
                    ->whereHas('match', fn($q) => $q->where('match_week_id', $weekId))
                    ->update(['is_double' => false]);
            } elseif ($match->kickoff_at) {
                $weekStart = $match->kickoff_at->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
                $weekEnd   = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
                MatchPrediction::where('user_id', $user->id)
                    ->where('club_match_id', '!=', $match->id)
                    ->whereHas('match', fn($q) => $q->whereBetween('kickoff_at', [$weekStart, $weekEnd]))
                    ->update(['is_double' => false]);
            }
        }

        // +10 XP pour avoir pronostiqué
        $user->load('profile');
        if ($user->profile) {
            $user->profile->addExperience(10);
        }

        // +20 coins premier pronostic de la semaine
        $weekId            = $match->match_week_id;
        $isFirstPrediction = !MatchPrediction::where('user_id', $user->id)
            ->where('club_match_id', '!=', $match->id)
            ->whereHas('match', function ($q) use ($weekId, $match) {
                if ($weekId) {
                    $q->where('match_week_id', $weekId);
                } elseif ($match->kickoff_at) {
                    $weekStart = $match->kickoff_at->copy()->startOfWeek(\Carbon\Carbon::MONDAY)->startOfDay();
                    $weekEnd   = $weekStart->copy()->endOfWeek(\Carbon\Carbon::SUNDAY)->endOfDay();
                    $q->whereBetween('kickoff_at', [$weekStart, $weekEnd]);
                }
            })
            ->exists();
        if ($isFirstPrediction) {
            $user->addCoins(20);
            $user->refresh();
        }

        $this->prepareWeeklyBonusIfEligible($user->id, $match);

        return response()->json([
            'message'    => 'Pronostic enregistré.',
            'prediction' => [
                'id'                      => $prediction->id,
                'match_id'                => $prediction->club_match_id,
                'predicted_outcome'       => $prediction->predicted_outcome,
                'predicted_home_score'    => $prediction->predicted_home_score,
                'predicted_away_score'    => $prediction->predicted_away_score,
                'is_double'               => $prediction->is_double,
            ],
        ]);
    }

    // ─── results ─────────────────────────────────────────────────────────────────
    public function results(Request $request): JsonResponse
    {
        $user   = $request->user();
        $clubIds = $this->allowedClubIds($user);

        // Toutes les prédictions avec les matchs
        $predictions = MatchPrediction::where('user_id', $user->id)
            ->with('match.matchWeek', 'match.clubTeam')
            ->when($clubIds, fn($q) => $q->whereHas('match', fn($mq) => $mq->whereIn('club_team_id', $clubIds)))
            ->get();

        if ($predictions->isEmpty()) {
            return response()->json(['weeks' => []]);
        }

        // Grouper par semaine
        $weekGroups = [];
        foreach ($predictions as $pred) {
            $match = $pred->match;
            if (!$match) continue;
            $weekKey = $this->weekKeyForMatch($match);
            $weekGroups[$weekKey][] = $pred;
        }

        // Bonuses existants
        $bonuses = PredictionWeeklyBonus::where('user_id', $user->id)
            ->get()
            ->keyBy(fn($b) => $b->week_start_date->toDateString());

        $weeks = [];
        foreach ($weekGroups as $weekStart => $preds) {
            $total    = count($preds);
            $settled  = 0;
            $correct  = 0;
            $exact    = 0;
            $matches  = [];
            $coinsInd = 0;
            $xpInd    = 0;

            // Infos semaine
            $firstMatch = $preds[0]->match;
            $weekEnd    = $firstMatch->matchWeek?->end_date?->toDateString()
                ?? Carbon::parse($weekStart)->endOfWeek(Carbon::SUNDAY)->toDateString();
            $weekLabel  = $firstMatch->matchWeek?->label
                ?? 'Semaine du ' . Carbon::parse($weekStart)->format('d/m');

            foreach ($preds as $pred) {
                $m          = $pred->match;
                $hasResult  = $m->result_outcome !== null && !$m->is_cancelled;
                if ($hasResult) $settled++;

                $exactScore = $hasResult
                    && $pred->predicted_home_score !== null
                    && $pred->predicted_home_score === $m->home_score
                    && $pred->predicted_away_score === $m->away_score;
                $goodWinner = $hasResult && $pred->predicted_outcome === $m->result_outcome;

                if ($exactScore) { $exact++; $correct++; }
                elseif ($goodWinner) $correct++;

                $mCoins = 0;
                $mXp    = 0;
                $rtype  = 'none';
                if ($exactScore) {
                    $mCoins = self::COINS_EXACT;  $mXp = self::XP_EXACT;  $rtype = 'exact';
                } elseif ($goodWinner) {
                    $mCoins = self::COINS_WINNER; $mXp = self::XP_WINNER; $rtype = 'winner';
                }
                if ($pred->is_double && $mCoins > 0) $mCoins *= 2;

                $coinsInd += $mCoins;
                $xpInd    += $mXp;

                $clubName = $m->clubTeam?->short_name ?: $m->clubTeam?->name;
                $matches[] = [
                    'id'                        => $m->id,
                    'home_name'                 => $m->is_home ? $clubName : $m->opponent_name,
                    'away_name'                 => $m->is_home ? $m->opponent_name : $clubName,
                    'home_score'                => $m->home_score,
                    'away_score'                => $m->away_score,
                    'result_outcome'            => $m->result_outcome,
                    'kickoff_at'                => $m->kickoff_at?->toDateTimeString(),
                    'is_cancelled'              => $m->is_cancelled,
                    'user_prediction'           => $pred->predicted_outcome,
                    'user_predicted_home_score' => $pred->predicted_home_score,
                    'user_predicted_away_score' => $pred->predicted_away_score,
                    'is_double'                 => $pred->is_double,
                    'reward_type'               => $rtype,
                    'coins_earned'              => $mCoins,
                    'xp_earned'                 => $mXp,
                ];
            }

            $bonus         = $bonuses->get($weekStart);
            $bonusCoins    = $bonus?->coins_earned ?? 0;
            $bonusXp       = $bonus?->xp_earned ?? 0;
            $isSettled     = $settled === $total && $total > 0;
            $isClaimable   = $isSettled && (!$bonus || $bonus->claimed_at === null);
            $claimedAt     = $bonus?->claimed_at;

            $weeks[] = [
                'week_start'          => $weekStart,
                'week_end'            => $weekEnd,
                'week_label'          => $weekLabel,
                'total_predictions'   => $total,
                'settled_predictions' => $settled,
                'correct_predictions' => $correct,
                'exact_predictions'   => $exact,
                'coins_to_claim'      => $coinsInd + $bonusCoins,
                'xp_to_claim'         => $xpInd + $bonusXp,
                'is_settled'          => $isSettled,
                'is_claimable'        => $isClaimable,
                'claimed_at'          => $claimedAt?->toIso8601String(),
                'matches'             => $matches,
            ];
        }

        usort($weeks, fn($a, $b) => strcmp($b['week_start'], $a['week_start']));

        return response()->json(['weeks' => $weeks]);
    }

    // ─── claimWeek ───────────────────────────────────────────────────────────────
    public function claimWeek(Request $request, string $weekStart): JsonResponse
    {
        $user           = $request->user();
        $weekStartDate  = Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEndDate    = $weekStartDate->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        return DB::transaction(function () use ($user, $weekStartDate, $weekEndDate) {
            // Vérif déjà récupéré
            $bonus = PredictionWeeklyBonus::where('user_id', $user->id)
                ->where('week_start_date', $weekStartDate->toDateString())
                ->lockForUpdate()
                ->first();

            if ($bonus && $bonus->claimed_at) {
                return response()->json(['message' => 'Déjà récupéré.'], 422);
            }

            // Récupérer toutes les prédictions de la semaine
            $predictions = MatchPrediction::where('user_id', $user->id)
                ->with('match')
                ->whereHas('match', function ($q) use ($weekStartDate, $weekEndDate) {
                    $q->where('is_cancelled', false)
                      ->where(function ($q2) use ($weekStartDate, $weekEndDate) {
                          $q2->whereHas('matchWeek', fn($q3) => $q3->whereDate('start_date', $weekStartDate->toDateString()))
                             ->orWhereBetween('kickoff_at', [$weekStartDate, $weekEndDate]);
                      });
                })
                ->get();

            if ($predictions->isEmpty()) {
                return response()->json(['message' => 'Aucun pronostic cette semaine.'], 422);
            }

            // Vérif tous les matchs terminés
            $allSettled = $predictions->every(fn($p) => $p->match?->result_outcome !== null);
            if (!$allSettled) {
                return response()->json(['message' => 'Tous les matchs ne sont pas terminés.'], 422);
            }

            // Calculer récompenses individuelles
            $totalCoins = 0;
            $totalXp    = 0;

            foreach ($predictions as $pred) {
                $m     = $pred->match;
                $exact  = $pred->predicted_home_score !== null
                        && $pred->predicted_home_score === $m->home_score
                        && $pred->predicted_away_score === $m->away_score;
                $winner = $pred->predicted_outcome === $m->result_outcome;

                if ($exact) {
                    $c = self::COINS_EXACT; $x = self::XP_EXACT;
                } elseif ($winner) {
                    $c = self::COINS_WINNER; $x = self::XP_WINNER;
                } else {
                    continue;
                }
                if ($pred->is_double) $c *= 2;
                $totalCoins += $c;
                $totalXp    += $x;
            }

            // Bonus hebdomadaire stocké
            if ($bonus) {
                $totalCoins += $bonus->coins_earned;
                $totalXp    += $bonus->xp_earned;
                $bonus->update(['claimed_at' => now()]);
            } else {
                // Calculer et créer le bonus maintenant (cas où il n'a pas été créé automatiquement)
                [$bonusCoins, $bonusXp] = $this->computeWeeklyBonus($predictions, $weekStartDate);
                $totalCoins += $bonusCoins;
                $totalXp    += $bonusXp;
                PredictionWeeklyBonus::create([
                    'user_id'         => $user->id,
                    'week_start_date' => $weekStartDate->toDateString(),
                    'awarded_at'      => now(),
                    'coins_earned'    => $bonusCoins,
                    'xp_earned'       => $bonusXp,
                    'claimed_at'      => now(),
                ]);
            }

            // Donner les récompenses
            if ($totalCoins > 0) $user->addCoins($totalCoins);
            $user->load('profile');
            if ($totalXp > 0 && $user->profile) {
                $user->profile->addExperience($totalXp);
            }

            return response()->json([
                'message'     => 'Récompenses récupérées !',
                'coins_earned'=> $totalCoins,
                'xp_earned'   => $totalXp,
            ]);
        });
    }

    // ─── history ─────────────────────────────────────────────────────────────────
    public function history(Request $request): JsonResponse
    {
        $user   = $request->user();
        $clubIds = $this->allowedClubIds($user);
        $query  = ClubMatch::with([
            'clubTeam',
            'predictions' => fn($q) => $q->where('user_id', $user->id),
        ])
            ->when($clubIds, fn($q) => $q->whereIn('club_team_id', $clubIds))
            ->whereNotNull('result_outcome')
            ->orderByDesc('kickoff_at');

        if ($request->filled('week_start')) {
            $weekStart = Carbon::parse($request->week_start)->startOfWeek(Carbon::MONDAY)->startOfDay();
            $weekEnd   = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
            $query->whereBetween('kickoff_at', [$weekStart, $weekEnd]);
        }

        $matches = $query->get()
            ->filter(fn($match) => $match->predictions->isNotEmpty())
            ->map(function (ClubMatch $match) {
                $clubName  = $match->clubTeam?->short_name ?: $match->clubTeam?->name;
                $prediction = $match->predictions->first();
                $correct    = $prediction && $prediction->predicted_outcome === $match->result_outcome;
                return [
                    'id'                        => $match->id,
                    'club_team'                 => $match->clubTeam ? ['id' => $match->clubTeam->id, 'name' => $match->clubTeam->name, 'short_name' => $match->clubTeam->short_name] : null,
                    'opponent_name'             => $match->opponent_name,
                    'location'                  => $match->location,
                    'kickoff_at'                => $match->kickoff_at?->toDateTimeString(),
                    'is_home'                   => $match->is_home,
                    'home_name'                 => $match->is_home ? $clubName : $match->opponent_name,
                    'away_name'                 => $match->is_home ? $match->opponent_name : $clubName,
                    'home_score'                => $match->home_score,
                    'away_score'                => $match->away_score,
                    'result_outcome'            => $match->result_outcome,
                    'user_prediction'           => $prediction?->predicted_outcome,
                    'user_predicted_home_score' => $prediction?->predicted_home_score,
                    'user_predicted_away_score' => $prediction?->predicted_away_score,
                    'is_double'                 => $prediction?->is_double ?? false,
                    'is_correct'                => $correct,
                ];
            })
            ->values();

        return response()->json(['matches' => $matches]);
    }

    // ─── leaderboard ─────────────────────────────────────────────────────────────
    public function leaderboard(Request $request): JsonResponse
    {
        $user      = $request->user();
        $clubIds   = $this->allowedClubIds($user);
        $scope     = $request->input('scope', 'week');
        $weekStart = null;
        $weekEnd   = null;

        if ($scope !== 'global') {
            $weekStart = $request->filled('week_start')
                ? Carbon::parse($request->week_start)->startOfWeek(Carbon::MONDAY)->startOfDay()
                : now()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
        }

        // Le classement est identique pour tous les joueurs d'un même club/scope/semaine :
        // on met en cache le résultat calculé pendant 120 s pour absorber la charge.
        $clubKey   = $clubIds ? implode('-', $clubIds) : 'all';
        $cacheKey  = "leaderboard:{$clubKey}:{$scope}:" . ($weekStart?->toDateString() ?? 'global');

        $leaderboard = \Illuminate\Support\Facades\Cache::remember(
            $cacheKey,
            120,
            fn () => $this->computeLeaderboard($clubIds, $weekStart, $weekEnd)
        );

        return response()->json([
            'scope'      => $scope,
            'week_start' => $weekStart?->toDateString(),
            'week_end'   => $weekEnd?->toDateString(),
            'leaderboard'=> $leaderboard,
        ]);
    }

    /**
     * Calcul lourd du classement (points, séries, bonus). Extrait pour la mise en cache.
     */
    private function computeLeaderboard(?array $clubIds, ?Carbon $weekStart, ?Carbon $weekEnd): array
    {
        $allMatchesQuery = ClubMatch::with('matchWeek')
            ->whereNotNull('result_outcome')
            ->where('is_cancelled', false)
            ->when($clubIds, fn($q) => $q->whereIn('club_team_id', $clubIds));
        if ($weekEnd) $allMatchesQuery->where('kickoff_at', '<=', $weekEnd);
        $allMatches  = $allMatchesQuery->get();
        $allMatchIds = $allMatches->pluck('id')->all();
        $matchById   = $allMatches->keyBy('id');

        if (empty($allMatchIds)) {
            return [];
        }

        $predictions = MatchPrediction::whereIn('club_match_id', $allMatchIds)->get();

        $weekMatches = [];
        foreach ($allMatches as $match) {
            $wk = $this->weekKeyForMatch($match);
            if (!isset($weekMatches[$wk])) $weekMatches[$wk] = ['total' => 0];
            $weekMatches[$wk]['total'] += 1;
        }
        $weekKeys = collect(array_keys($weekMatches))->sort()->values();

        $userWeekStats = [];
        foreach ($predictions as $pred) {
            $match = $matchById->get($pred->club_match_id);
            if (!$match) continue;
            $wk = $this->weekKeyForMatch($match);
            if (!isset($userWeekStats[$pred->user_id][$wk])) {
                $userWeekStats[$pred->user_id][$wk] = ['predicted' => 0, 'correct' => 0];
            }
            $userWeekStats[$pred->user_id][$wk]['predicted'] += 1;
            if ($pred->predicted_outcome === $match->result_outcome) {
                $userWeekStats[$pred->user_id][$wk]['correct'] += 1;
            }
        }

        $bonusByUserWeek = [];
        foreach ($userWeekStats as $userId => $stats) {
            $streak = 0; $lastWeek = null;
            foreach ($weekKeys as $wk) {
                $totalM   = $weekMatches[$wk]['total'] ?? 0;
                if ($totalM === 0) continue;
                $predicted = $stats[$wk]['predicted'] ?? 0;
                $correct   = $stats[$wk]['correct'] ?? 0;
                $required  = min(2, $totalM);
                $rate      = $totalM > 0 ? ($correct / $totalM) : 0;
                $qualifies = $predicted >= $required && $rate >= 0.5;
                if ($qualifies) {
                    if ($lastWeek) {
                        $diff   = Carbon::parse($wk)->diffInDays(Carbon::parse($lastWeek));
                        $streak = ($diff === 7 && $streak > 0) ? $streak + 1 : 1;
                    } else { $streak = 1; }
                    $lastWeek = $wk;
                    if ($streak === 3) $bonusByUserWeek[$userId][$wk] = ($bonusByUserWeek[$userId][$wk] ?? 0) + 2;
                    if ($streak === 5) $bonusByUserWeek[$userId][$wk] = ($bonusByUserWeek[$userId][$wk] ?? 0) + 5;
                } else { $streak = 0; $lastWeek = $wk; }
            }
        }

        $matchesForPoints = $allMatches;
        if ($weekStart && $weekEnd) {
            $matchesForPoints = $allMatches->filter(fn($m) => $m->kickoff_at && $m->kickoff_at->between($weekStart, $weekEnd));
        }
        $matchIdsForPoints = $matchesForPoints->pluck('id')->flip();

        $pointsByUser  = [];
        $correctByUser = [];
        $totalByUser   = [];

        foreach ($predictions as $pred) {
            if (!isset($matchIdsForPoints[$pred->club_match_id])) continue;
            $match = $matchById->get($pred->club_match_id);
            if (!$match) continue;
            $exact       = $pred->predicted_home_score === $match->home_score && $pred->predicted_away_score === $match->away_score;
            $winnerOk    = $pred->predicted_outcome === $match->result_outcome;
            $points      = $exact ? 5 : ($winnerOk ? 3 : 0);
            if ($pred->is_double) $points *= 2;
            $pointsByUser[$pred->user_id]  = ($pointsByUser[$pred->user_id] ?? 0) + $points;
            $totalByUser[$pred->user_id]   = ($totalByUser[$pred->user_id] ?? 0) + 1;
            if ($winnerOk) $correctByUser[$pred->user_id] = ($correctByUser[$pred->user_id] ?? 0) + 1;
        }

        if ($weekStart) {
            $wk = $weekStart->toDateString();
            foreach ($bonusByUserWeek as $uid => $weeks) {
                $b = $weeks[$wk] ?? 0;
                if ($b > 0) $pointsByUser[$uid] = ($pointsByUser[$uid] ?? 0) + $b;
            }
        } else {
            foreach ($bonusByUserWeek as $uid => $weeks) {
                $sum = array_sum($weeks);
                if ($sum > 0) $pointsByUser[$uid] = ($pointsByUser[$uid] ?? 0) + $sum;
            }
        }

        $userIds     = array_unique(array_merge(array_keys($pointsByUser), array_keys($totalByUser)));
        $usersQuery  = \App\Models\User::whereIn('id', $userIds);
        if ($clubIds) {
            $usersQuery->whereIn('club_team_id', $clubIds);
        }
        $users       = $usersQuery->get(['id', 'name'])->keyBy('id');

        $rank        = 1;
        $leaderboard = collect($userIds)->map(function ($uid) use ($users, $pointsByUser, $correctByUser, $totalByUser) {
            return [
                'user_id' => $uid,
                'name'    => $users->get($uid)?->name ?? 'Utilisateur',
                'correct' => (int) ($correctByUser[$uid] ?? 0),
                'total'   => (int) ($totalByUser[$uid] ?? 0),
                'points'  => (int) ($pointsByUser[$uid] ?? 0),
            ];
        })->sort(fn($a, $b) => [$b['points'], $b['correct'], $b['total']] <=> [$a['points'], $a['correct'], $a['total']])->values()
          ->map(function ($e) use (&$rank) { $e['rank'] = $rank++; return $e; });

        return $leaderboard->all();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────
    private function weekKeyForMatch(ClubMatch $match): string
    {
        $date = $match->matchWeek?->start_date ?? $match->kickoff_at;
        if (!$date) return '1970-01-01';
        return $date->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    private function predictionWindowForMatch(ClubMatch $match): ?array
    {
        $weekStart = $match->matchWeek?->start_date?->copy()->startOfDay();
        if (!$weekStart && $match->kickoff_at) {
            $weekStart = $match->kickoff_at->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        }
        if (!$weekStart) return null;
        return [$weekStart, $weekStart->copy()->addDays(4)->setTime(12, 0)];
    }

    private function computeWeeklyBonus(\Illuminate\Support\Collection $predictions, Carbon $weekStart): array
    {
        $total   = $predictions->count();
        if ($total < 2) return [0, 0];

        $correct = $predictions->filter(function ($p) {
            if (!$p->match || $p->match->result_outcome === null) return false;
            $exact  = $p->predicted_home_score === $p->match->home_score && $p->predicted_away_score === $p->match->away_score;
            $winner = $p->predicted_outcome === $p->match->result_outcome;
            return $exact || $winner;
        })->count();

        $rate = $total > 0 ? $correct / $total : 0;
        $coins = match(true) {
            $rate >= 0.5  => 500,
            $rate >= 0.30 => 250,
            $rate >  0    => 100,
            default       => 0,
        };

        // Streak: semaines consécutives avec ≥50% de réussite
        $userId = $predictions->first()->user_id;
        $streak = $this->calculateStreak($userId, $weekStart);
        if ($streak >= 1 && $coins > 0) $coins += 100;

        $xp = 0;
        foreach ($predictions as $p) {
            if (!$p->match || $p->match->result_outcome === null) continue;
            $exact  = $p->predicted_home_score === $p->match->home_score && $p->predicted_away_score === $p->match->away_score;
            $winner = $p->predicted_outcome === $p->match->result_outcome;
            if ($exact) $xp += self::XP_EXACT;
            elseif ($winner) $xp += self::XP_WINNER;
        }
        if ($streak >= 1) $xp += 50;

        return [$coins, $xp];
    }

    private function calculateStreak(int $userId, Carbon $currentWeekStart): int
    {
        // Une seule requête : on charge tout l'historique du joueur puis on regroupe
        // par semaine en mémoire (au lieu de jusqu'à 52 requêtes SQL).
        $predictions = MatchPrediction::where('user_id', $userId)
            ->with('match.matchWeek')
            ->get();

        $byWeek = [];
        foreach ($predictions as $p) {
            $m = $p->match;
            if (!$m || $m->is_cancelled) {
                continue;
            }
            $date = $m->matchWeek?->start_date ?? $m->kickoff_at;
            if (!$date) {
                continue;
            }
            $wk = $date->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
            $byWeek[$wk][] = $p;
        }

        $streak         = 0;
        $checkWeekStart = $currentWeekStart->copy()->subWeek()->startOfWeek(Carbon::MONDAY)->startOfDay();

        for ($i = 0; $i < 52; $i++) {
            $preds = $byWeek[$checkWeekStart->toDateString()] ?? [];
            if (count($preds) < 2) break;

            $settled = array_filter($preds, fn($p) => $p->match?->result_outcome !== null);
            if (count($settled) < 2) break;

            $settledTotal   = count($settled);
            $settledCorrect = 0;
            foreach ($settled as $p) {
                $exact  = $p->predicted_home_score === $p->match->home_score && $p->predicted_away_score === $p->match->away_score;
                $winner = $p->predicted_outcome === $p->match->result_outcome;
                if ($exact || $winner) $settledCorrect++;
            }

            if (($settledCorrect / $settledTotal) < 0.5) break;

            $streak++;
            $checkWeekStart = $checkWeekStart->copy()->subWeek();
        }

        return $streak;
    }

    private function prepareWeeklyBonusIfEligible(int $userId, ClubMatch $match): void
    {
        $weekStart = $match->matchWeek?->start_date?->copy()->startOfDay();
        $weekEnd   = $match->matchWeek?->end_date?->copy()->endOfDay();
        $weekId    = $match->match_week_id;

        if (!$weekStart || !$weekEnd) {
            if (!$match->kickoff_at) return;
            $weekStart = $match->kickoff_at->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $weekEnd   = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
        }

        if (PredictionWeeklyBonus::where('user_id', $userId)->where('week_start_date', $weekStart->toDateString())->exists()) return;

        $predictions = MatchPrediction::where('user_id', $userId)
            ->with('match')
            ->whereHas('match', function ($q) use ($weekId, $weekStart, $weekEnd) {
                if ($weekId) {
                    $q->where('match_week_id', $weekId)->where('is_cancelled', false);
                } else {
                    $q->whereBetween('kickoff_at', [$weekStart, $weekEnd])->where('is_cancelled', false);
                }
            })
            ->get();

        if ($predictions->count() < 2) return;

        $settled = $predictions->filter(fn($p) => $p->match?->result_outcome !== null);
        if ($settled->count() < $predictions->count()) return;
        if ($settled->count() < 2) return;

        [$coins, $xp] = $this->computeWeeklyBonus($settled, $weekStart);
        if ($coins <= 0) return;

        DB::transaction(function () use ($userId, $weekStart, $coins, $xp) {
            if (PredictionWeeklyBonus::where('user_id', $userId)->where('week_start_date', $weekStart->toDateString())->lockForUpdate()->exists()) return;
            PredictionWeeklyBonus::create([
                'user_id'         => $userId,
                'week_start_date' => $weekStart->toDateString(),
                'awarded_at'      => now(),
                'coins_earned'    => $coins,
                'xp_earned'       => $xp,
                'claimed_at'      => null,
            ]);
        });
    }

    /**
     * Clubs accessibles pour l'utilisateur : club assignÃ© + enfants si club parent.
     */
    private function allowedClubIds($user): ?array
    {
        if (!$user || !$user->club_team_id) return null;
        $club = \App\Models\ClubTeam::find($user->club_team_id);
        if (!$club) return null;
        $rootId = $club->parent_id ?: $club->id;
        return \App\Models\ClubTeam::where('id', $rootId)
            ->orWhere('parent_id', $rootId)
            ->pluck('id')
            ->all();
    }
}
