<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClubMatch;
use App\Models\MatchWeek;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchWeekAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $weeks = MatchWeek::withCount('matches')
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
        return response()->json(['weeks' => $weeks]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'number'     => 'required|integer|min:1|unique:match_weeks,number',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'label'      => 'nullable|string|max:100',
        ]);
        $week = MatchWeek::create($data);
        return response()->json(['week' => $week], 201);
    }

    public function update(Request $request, MatchWeek $matchWeek): JsonResponse
    {
        $data = $request->validate([
            'number'     => 'sometimes|integer|min:1|unique:match_weeks,number,' . $matchWeek->id,
            'start_date' => 'sometimes|date',
            'end_date'   => 'sometimes|date',
            'label'      => 'nullable|string|max:100',
        ]);
        $matchWeek->update($data);
        return response()->json(['week' => $matchWeek]);
    }

    public function destroy(MatchWeek $matchWeek): JsonResponse
    {
        $matchWeek->matches()->update(['match_week_id' => null]);
        $matchWeek->delete();
        return response()->json(['message' => 'Semaine supprimée.']);
    }
}
