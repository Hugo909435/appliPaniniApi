<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\AwardPredictionRewards;
use App\Models\ClubMatch;
use App\Models\ClubTeam;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClubMatchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ClubMatch::with('clubTeam')->orderBy('kickoff_at');

        if ($request->filled('team_id')) {
            $query->where('club_team_id', $request->team_id);
        }

        if ($request->filled('week_id')) {
            $query->where('match_week_id', $request->week_id);
        } elseif ($request->filled('week_start')) {
            $start = Carbon::parse($request->week_start)->startOfWeek(Carbon::MONDAY)->startOfDay();
            $end = $start->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
            $query->whereBetween('kickoff_at', [$start, $end]);
        }

        if ($request->filled('status')) {
            if ($request->status === 'upcoming') {
                $query->where('kickoff_at', '>', now());
            }
            if ($request->status === 'played') {
                $query->whereNotNull('result_outcome');
            }
        }

        $matches = $query->get()->map(fn($match) => $this->mapMatch($match));

        return response()->json([
            'matches' => $matches,
            'teams' => ClubTeam::orderBy('name')->get(['id', 'name', 'short_name', 'is_active']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'match_week_id' => 'nullable|integer|exists:match_weeks,id',
            'club_team_id' => 'required|exists:club_teams,id',
            'opponent_name' => 'required|string|max:100',
            'location' => 'nullable|string|max:150',
            'kickoff_at' => 'required|date',
            'is_home' => 'required|boolean',
            'home_score' => 'nullable|integer|min:0|max:99',
            'away_score' => 'nullable|integer|min:0|max:99',
        ]);

        $validated['location'] = $request->input('location') ?? '';

        $match = ClubMatch::create($validated);
        $this->applyResultIfAny($match);

        return response()->json(['message' => 'Match créé.', 'match' => $this->mapMatch($match->fresh('clubTeam'))], 201);
    }

    public function update(Request $request, ClubMatch $clubMatch): JsonResponse
    {
        $validated = $request->validate([
            'match_week_id' => 'nullable|integer|exists:match_weeks,id',
            'club_team_id' => 'required|exists:club_teams,id',
            'opponent_name' => 'required|string|max:100',
            'location' => 'nullable|string|max:150',
            'kickoff_at' => 'required|date',
            'is_home' => 'required|boolean',
            'home_score' => 'nullable|integer|min:0|max:99',
            'away_score' => 'nullable|integer|min:0|max:99',
        ]);

        $validated['location'] = $validated['location'] ?? '';

        $previousOutcome = $clubMatch->result_outcome;
        $clubMatch->update($validated);
        $this->applyResultIfAny($clubMatch, $previousOutcome);

        return response()->json(['message' => 'Match mis à jour.', 'match' => $this->mapMatch($clubMatch->fresh('clubTeam'))]);
    }

    public function destroy(ClubMatch $clubMatch): JsonResponse
    {
        $clubMatch->delete();
        return response()->json(['message' => 'Match supprimé.']);
    }

    private function applyResultIfAny(ClubMatch $match, ?string $previousOutcome = null): void
    {
        $outcome = $match->computeOutcome();
        if (!$outcome) {
            if ($match->result_outcome !== null || $match->result_set_at !== null) {
                $match->update(['result_outcome' => null, 'result_set_at' => null]);
            }
            return;
        }

        $match->forceFill([
            'result_outcome' => $outcome,
            'result_set_at' => $match->result_set_at ?? now(),
        ])->save();

        if ($previousOutcome !== $outcome) {
            AwardPredictionRewards::dispatch($match->id);
        }
    }

    private function mapMatch(ClubMatch $match): array
    {
        $clubName = $match->clubTeam?->short_name ?: $match->clubTeam?->name;
        $homeName = $match->is_home ? $clubName : $match->opponent_name;
        $awayName = $match->is_home ? $match->opponent_name : $clubName;

        return [
            'id' => $match->id,
            'match_week_id' => $match->match_week_id,
            'club_team_id' => $match->club_team_id,
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
        ];
    }
}
