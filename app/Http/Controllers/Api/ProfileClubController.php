<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClubTeam;
use Illuminate\Http\Request;

class ProfileClubController extends Controller
{
    public function select(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $data = $request->validate([
            'club_team_id' => ['required', 'exists:club_teams,id'],
        ]);

        // super admin peut changer plusieurs fois, les autres seulement si pas encore défini
        if (!$user->is_super_admin && $user->club_team_id) {
            return response()->json(['message' => 'Club déjà sélectionné'], 400);
        }

        $club = ClubTeam::where('id', $data['club_team_id'])
            ->when(!$user->is_super_admin, fn($q) => $q->where('is_active', true))
            ->first();
        if (!$club) {
            return response()->json(['message' => 'Club introuvable ou inactif'], 400);
        }

        $user->club_team_id = $club->id;
        $user->save();

        return response()->json(['club_team_id' => $user->club_team_id]);
    }
}
