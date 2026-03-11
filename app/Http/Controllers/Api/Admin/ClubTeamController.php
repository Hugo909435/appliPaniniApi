<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClubTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClubTeamController extends Controller
{
    public function index(): JsonResponse
    {
        $teams = ClubTeam::orderBy('name')->get();
        return response()->json(['teams' => $teams]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'short_name' => 'nullable|string|max:30',
            'is_active' => 'boolean',
        ]);

        $team = ClubTeam::create($validated);
        return response()->json(['message' => 'Équipe créée.', 'team' => $team], 201);
    }

    public function update(Request $request, ClubTeam $clubTeam): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'short_name' => 'nullable|string|max:30',
            'is_active' => 'boolean',
        ]);

        $clubTeam->update($validated);
        return response()->json(['message' => 'Équipe mise à jour.', 'team' => $clubTeam]);
    }

    public function destroy(ClubTeam $clubTeam): JsonResponse
    {
        $clubTeam->delete();
        return response()->json(['message' => 'Équipe supprimée.']);
    }
}
