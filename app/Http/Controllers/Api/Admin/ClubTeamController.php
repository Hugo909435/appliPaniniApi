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
        $teams = $admin->is_super_admin
            ? ClubTeam::orderBy('name')->get()
            : ClubTeam::where('id', $admin->club_team_id)->get();

        return response()->json(['teams' => $teams]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->is_super_admin) {
            return response()->json(['message' => 'Réservé au super admin.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'short_name' => 'nullable|string|max:30',
            'is_active' => 'boolean',
            'is_main_club' => 'boolean',
            'primary_color' => 'nullable|string|max:20',
        ]);

        $team = ClubTeam::create($validated);
        return response()->json(['message' => 'Équipe créée.', 'team' => $team], 201);
    }

    public function update(Request $request, ClubTeam $clubTeam): JsonResponse
    {
        $admin = $request->user();
        if (!$admin->is_super_admin && $admin->club_team_id !== $clubTeam->id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'short_name' => 'nullable|string|max:30',
            'is_active' => 'boolean',
            'is_main_club' => 'boolean',
            'primary_color' => 'nullable|string|max:20',
        ]);

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
