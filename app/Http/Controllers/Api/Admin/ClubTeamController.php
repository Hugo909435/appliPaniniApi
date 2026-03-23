<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClubTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClubTeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user();
        if ($admin->is_super_admin) {
            $teams = ClubTeam::orderBy('name')->get();
        } else {
            $teams = ClubTeam::where('parent_id', $admin->club_team_id)->orderBy('name')->get();
        }

        return response()->json(['teams' => $teams]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->is_super_admin) {
            return response()->json(['message' => 'Réservé au super admin.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'is_active' => 'boolean',
            'is_main_club' => 'boolean',
            'primary_color' => 'nullable|string|max:20',
            'parent_id' => 'nullable|integer|exists:club_teams,id',
        ]);

        // Si admin standard, rattacher automatiquement à son club et marquer non main
        if (!$request->user()->is_super_admin) {
            $validated['parent_id'] = $request->user()->club_team_id;
            $validated['is_main_club'] = false;
        }

        $team = ClubTeam::create($validated);
        return response()->json(['message' => 'Équipe créée.', 'team' => $team], 201);
    }

    public function update(Request $request, ClubTeam $clubTeam): JsonResponse
    {
        $admin = $request->user();
        if (!$admin->is_super_admin && $clubTeam->parent_id !== $admin->club_team_id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'is_active' => 'boolean',
            'is_main_club' => 'boolean',
            'primary_color' => 'nullable|string|max:20',
            'parent_id' => 'nullable|integer|exists:club_teams,id',
        ]);

        if (!$admin->is_super_admin) {
            $validated['parent_id'] = $admin->club_team_id;
            $validated['is_main_club'] = false;
        }

        $clubTeam->update($validated);
        return response()->json(['message' => 'Équipe mise à jour.', 'team' => $clubTeam]);
    }

    public function destroy(Request $request, ClubTeam $clubTeam): JsonResponse
    {
        if (!$request->user()->is_super_admin) {
            return response()->json(['message' => 'Réservé au super admin.'], 403);
        }

        $clubTeam->delete();
        return response()->json(['message' => 'Équipe supprimée.']);
    }
}
