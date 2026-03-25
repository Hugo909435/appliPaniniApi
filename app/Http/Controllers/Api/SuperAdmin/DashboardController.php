<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\ClubTeam;
use App\Models\MatchPrediction;
use App\Models\Pack;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $clubs = ClubTeam::select('id', 'name', 'is_active', 'is_main_club', 'theme_slug', 'created_at')
            ->where('is_main_club', true)
            ->orderBy('name')
            ->get()
            ->map(function ($club) {
                $clubIds = ClubTeam::where('id', $club->id)
                    ->orWhere('parent_id', $club->id)
                    ->pluck('id');
                $usersCount = User::where('is_super_admin', false)
                    ->where(function ($q) use ($club, $clubIds) {
                    $q->whereIn('club_team_id', $clubIds);
                    if ($club->is_main_club) {
                        // Inclure les utilisateurs non assignés pour éviter d'en perdre dans les stats
                        $q->orWhereNull('club_team_id');
                    }
                })->count();

                $tradesCount = Trade::whereIn('club_team_id', $clubIds)->count();

                $predictionsCount = DB::table('match_predictions')
                    ->join('club_matches', 'club_matches.id', '=', 'match_predictions.club_match_id')
                    ->whereIn('club_matches.club_team_id', $clubIds)
                    ->count();

                return [
                    'id' => $club->id,
                    'name' => $club->name,
                    'is_active' => (bool) ($club->is_active ?? true),
                    'is_main_club' => (bool) ($club->is_main_club ?? false),
                    'cards' => Card::whereIn('club_team_id', $clubIds)->count(),
                    'packs' => Pack::whereIn('club_team_id', $clubIds)->count(),
                    'trades' => $tradesCount,
                    'predictions' => $predictionsCount,
                    'users' => $usersCount,
                ];
            });

        return response()->json([
            'totals' => [
                'users' => User::count(),
                'cards' => Card::count(),
                'packs' => Pack::count(),
                'trades' => Trade::count(),
                'predictions' => MatchPrediction::count(),
                'clubs' => $clubs->count(),
            ],
            'clubs' => $clubs,
        ]);
    }
}
