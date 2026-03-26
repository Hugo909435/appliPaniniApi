<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\ClubTeam;
use App\Models\MatchPrediction;
use App\Models\Pack;
use App\Models\Trade;
use App\Models\Position;
use App\Models\Rarity;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClubController extends Controller
{
    public function index()
    {
        $clubs = ClubTeam::select('id', 'name', 'short_name', 'is_active', 'created_at', 'primary_color')
            ->where('is_main_club', true)
            ->orderBy('name')
            ->get();
        return response()->json(['clubs' => $clubs]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255', 'unique:club_teams,name'],
            'logo'         => ['nullable', 'string'],
            'logo_file'    => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'primary_color'=> ['nullable', 'string', 'max:9'],
            'theme_slug'   => ['nullable', 'string', 'in:default,red,orange,blue_yellow,blue,green_red,violet,mono,red_black,red_white,blue_white,blue_red,green_white,green_yellow,orange_black,purple_yellow,teal_orange'],
        ]);

        $club = new ClubTeam();
        $club->name = $data['name'];
        $club->short_name = $data['name'];
        if ($request->hasFile('logo_file')) {
            $club->logo = app(ImageService::class)->store($request->file('logo_file'), 'assets/clubs', 300, 300, 90);
        } elseif (isset($data['logo'])) {
            $club->logo = $data['logo'];
        }
        if (isset($data['primary_color'])) {
            $club->primary_color = $data['primary_color'];
        }
        $club->theme_slug = $data['theme_slug'] ?? 'default';
        // Les nouveaux clubs doivent Ãªtre inactifs par dÃ©faut pour Ã©viter une mise en ligne immÃ©diate
        $club->is_active = false;
        $club->is_main_club = true;
        $club->save();

        return response()->json(['club' => $club], 201);
    }

    public function toggle(ClubTeam $clubTeam)
    {
        $clubTeam->is_active = ! (bool) ($clubTeam->is_active ?? true);
        $clubTeam->save();
        return response()->json(['club' => $clubTeam->fresh()]);
    }

    public function destroy(ClubTeam $clubTeam)
    {
        // Delete children first to avoid parent FK issues
        ClubTeam::where('parent_id', $clubTeam->id)->delete();
        $clubTeam->delete();

        return response()->json(['message' => 'Club supprimÃ©.']);
    }

    public function update(Request $request, ClubTeam $clubTeam)
    {
        $data = $request->validate([
            'name'          => ['nullable', 'string', 'max:255', 'unique:club_teams,name,' . $clubTeam->id],
            'short_name'    => ['nullable', 'string', 'max:255'],
            'theme_slug'    => ['nullable', 'string', 'in:default,red,orange,blue_yellow,blue,green_red,violet,mono,red_black,red_white,blue_white,blue_red,green_white,green_yellow,orange_black,purple_yellow,teal_orange'],
            'logo_file'     => ['nullable', 'image', 'max:2048'],
            'is_active'     => ['nullable', 'boolean'],
        ]);

        if (isset($data['name'])) {
            $clubTeam->name = $data['name'];
        }
        if (isset($data['short_name'])) {
            $clubTeam->short_name = $data['short_name'];
        }
        if (isset($data['theme_slug'])) {
            $clubTeam->theme_slug = $data['theme_slug'];
        }
        if ($request->hasFile('logo_file')) {
            $clubTeam->logo = app(ImageService::class)->store($request->file('logo_file'), 'assets/clubs', 300, 300, 90);
        }
        if (array_key_exists('is_active', $data)) {
            $clubTeam->is_active = (bool) $data['is_active'];
        }

        $clubTeam->save();

        return response()->json(['club' => $clubTeam->fresh()]);
    }

    public function detail(ClubTeam $clubTeam)
    {
        $clubTeam->load(['matches', 'matches.matchWeek']);

        $clubIds = ClubTeam::where('id', $clubTeam->id)
            ->orWhere('parent_id', $clubTeam->id)
            ->pluck('id');

        $users = User::with('roles')
            ->where('is_super_admin', false)
            ->where(function ($q) use ($clubIds, $clubTeam) {
                $q->whereIn('club_team_id', $clubIds);
                if ($clubTeam->is_main_club) {
                    // Montrer aussi les comptes non assignÃ©s pour Ã©viter les listes vides
                    $q->orWhereNull('club_team_id');
                }
            })
            ->select('id', 'name', 'email', 'coins', 'money', 'is_super_admin', 'club_team_id', 'created_at', 'status')
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'coins' => $u->coins ?? 0,
                'money' => $u->money ?? 0,
                'is_admin' => $u->hasRole('admin'),
                'is_super_admin' => (bool) $u->is_super_admin,
                'created_at' => $u->created_at,
                'status' => $u->status ?? 'active',
            ]);

        $cards = Card::with(['rarity', 'position', 'pack', 'clubTeam'])
            ->whereIn('club_team_id', $clubIds)
            ->limit(200)
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'description' => $c->description,
                    'rarities_id' => $c->rarities_id,
                    'rarity_slug' => $c->rarity?->slug,
                    'rarity_label' => $c->rarity?->name,
                    'rarity_color' => $c->rarity?->color,
                    'positions_id' => $c->positions_id,
                    'position' => $c->position?->name,
                    'club_team_id' => $c->club_team_id,
                    'team' => $c->clubTeam?->name,
                    'pack_id' => $c->pack_id,
                    'pack_name' => $c->pack?->name,
                    'number' => $c->number,
                    'attack' => $c->attack,
                    'defense' => $c->defense,
                    'speed' => $c->speed,
                    'stamina' => $c->stamina,
                    'image' => $c->image_url,
                ];
            });

        $teams = ClubTeam::where('parent_id', $clubTeam->id)
            ->select('id', 'name', 'short_name', 'is_active')
            ->orderBy('name')
            ->get();

        $matches = \App\Models\ClubMatch::whereIn('club_team_id', $clubIds)
            ->select('id', 'club_team_id', 'opponent_name', 'location', 'kickoff_at', 'is_home', 'home_score', 'away_score', 'is_cancelled')
            ->orderByDesc('kickoff_at')
            ->limit(100)
            ->get()
            ->map(function ($m) use ($clubTeam) {
                $team = $m->clubTeam;
                $clubName = $team?->short_name ?? $team?->name ?? $clubTeam->short_name ?? $clubTeam->name;
                $home = $m->is_home ? $clubName : $m->opponent_name;
                $away = $m->is_home ? $m->opponent_name : $clubName;
                return [
                    'id' => $m->id,
                    'home_team' => $home,
                    'away_team' => $away,
                    'kickoff_at' => $m->kickoff_at?->toDateTimeString(),
                    'location' => $m->location,
                    'home_score' => $m->home_score,
                    'away_score' => $m->away_score,
                    'is_cancelled' => $m->is_cancelled,
                    'opponent_name' => $m->opponent_name,
                    'is_home' => (bool)$m->is_home,
                    'club_team_id' => $m->club_team_id,
                ];
            });

        $packs = Pack::where('club_team_id', $clubTeam->id)
            ->latest()
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'description' => $p->description,
                'image' => $p->image_url,
                'price' => $p->price,
                'card_count' => $p->card_count,
                'is_active'     => (bool) $p->is_active,
                'rarity_boosts' => $p->rarity_boosts,
            ]);

        return response()->json([
            'club' => $clubTeam->only(['id', 'name', 'short_name', 'logo', 'primary_color', 'theme_slug', 'is_active', 'created_at']),
            'users' => $users,
            'cards' => $cards,
            'teams' => $teams,
            'matches' => $matches,
            'packs' => $packs,
            'rarities' => Rarity::orderBy('drop_rate', 'desc')->get(['id', 'name', 'slug', 'color']),
            'positions' => Position::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function clubDashboard(ClubTeam $clubTeam)
    {
        $clubId = $clubTeam->id;
        $clubIds = ClubTeam::where('id', $clubId)
            ->orWhere('parent_id', $clubId)
            ->pluck('id');

        // â”€â”€ Trades â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $tradeSummary = Trade::whereIn('club_team_id', $clubIds)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status IN ('pending','accepted') THEN 1 ELSE 0 END) as ongoing,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
            ")
            ->first();

        // â”€â”€ Pronostics â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $predStats = \DB::table('match_predictions')
            ->join('club_matches', 'club_matches.id', '=', 'match_predictions.club_match_id')
            ->whereIn('club_matches.club_team_id', $clubIds) // parent + Ã©quipes enfants
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN club_matches.result_outcome IS NULL THEN 1 ELSE 0 END) as ongoing,
                SUM(CASE WHEN club_matches.result_outcome IS NOT NULL THEN 1 ELSE 0 END) as rewarded
            ")
            ->first();

        // Pronostics par match : total / score exact / bon vainqueur / perdus
        $matchPredictionBreakdown = \DB::table('match_predictions')
            ->join('club_matches', 'club_matches.id', '=', 'match_predictions.club_match_id')
            ->join('club_teams', 'club_teams.id', '=', 'club_matches.club_team_id')
            ->whereIn('club_matches.club_team_id', $clubIds) // inclut club parent + enfants
            ->selectRaw("
                club_matches.id as match_id,
                CASE WHEN club_matches.is_home = 1 THEN club_teams.name ELSE club_matches.opponent_name END as home_name,
                CASE WHEN club_matches.is_home = 1 THEN club_matches.opponent_name ELSE club_teams.name END as away_name,
                club_matches.kickoff_at,
                club_matches.home_score,
                club_matches.away_score,
                club_matches.result_outcome,
                COUNT(*) as total,
                SUM(
                    CASE
                        WHEN club_matches.home_score IS NOT NULL
                         AND club_matches.away_score IS NOT NULL
                         AND match_predictions.predicted_home_score = club_matches.home_score
                         AND match_predictions.predicted_away_score = club_matches.away_score
                        THEN 1 ELSE 0 END
                ) as exact_score,
                SUM(
                    CASE
                        WHEN club_matches.result_outcome IS NOT NULL
                         AND match_predictions.predicted_outcome = club_matches.result_outcome
                        THEN 1 ELSE 0 END
                ) as correct_outcome,
                SUM(
                    CASE
                        WHEN club_matches.result_outcome IS NOT NULL
                         AND match_predictions.predicted_outcome <> club_matches.result_outcome
                        THEN 1 ELSE 0 END
                ) as wrong_outcome
            ")
            ->groupBy(
                'club_matches.id',
                'club_matches.is_home',
                'club_teams.name',
                'club_matches.opponent_name',
                'club_matches.kickoff_at',
                'club_matches.home_score',
                'club_matches.away_score',
                'club_matches.result_outcome'
            )
            ->orderByDesc('club_matches.kickoff_at')
            ->get();

        // â”€â”€ Collection completion par utilisateur â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $totalCards = Card::whereIn('club_team_id', $clubIds)->count();

        $users = User::where('is_super_admin', false)
            ->where(function ($q) use ($clubIds, $clubTeam) {
                $q->whereIn('club_team_id', $clubIds);
                if ($clubTeam->is_main_club) {
                    $q->orWhereNull('club_team_id');
                }
            })
            ->select('id', 'name', 'email')
            ->get();

        $userIds = $users->pluck('id');

        $statsPerUser = DB::table('user_cards')
            ->join('cards', 'cards.id', '=', 'user_cards.card_id')
            ->whereIn('cards.club_team_id', $clubIds)
            ->whereIn('user_cards.user_id', $userIds)
            ->groupBy('user_cards.user_id')
            ->selectRaw('user_cards.user_id, COUNT(DISTINCT user_cards.card_id) as unique_owned, SUM(user_cards.quantity) as total_owned')
            ->get()
            ->keyBy('user_id');

        // RaretÃ©s possÃ©dÃ©es par utilisateur
        $rarityPerUser = DB::table('user_cards')
            ->join('cards', 'cards.id', '=', 'user_cards.card_id')
            ->join('rarities', 'rarities.id', '=', 'cards.rarities_id')
            ->whereIn('cards.club_team_id', $clubIds)
            ->whereIn('user_cards.user_id', $userIds)
            ->groupBy('user_cards.user_id', 'rarities.slug')
            ->selectRaw('user_cards.user_id, rarities.slug as rarity, SUM(user_cards.quantity) as qty')
            ->get()
            ->groupBy('user_id');

        // Packs ouverts par utilisateur
        $packsOpened = UserProfile::whereIn('user_id', $userIds)
            ->pluck('total_packs_opened', 'user_id');

        // Trades par utilisateur (crÃ©Ã©s, terminÃ©s, annulÃ©s, actifs)
        $trades = Trade::whereIn('club_team_id', $clubIds)
            ->where(function ($q) use ($userIds) {
                $q->whereIn('proposer_id', $userIds)->orWhereIn('receiver_id', $userIds);
            })
            ->select('proposer_id', 'receiver_id', 'status')
            ->get();
        $userTradeStats = [];
        foreach ($userIds as $uid) {
            $userTradeStats[$uid] = ['created' => 0, 'completed' => 0, 'cancelled' => 0, 'active' => 0];
        }
        foreach ($trades as $t) {
            if (isset($userTradeStats[$t->proposer_id])) {
                $userTradeStats[$t->proposer_id]['created']++;
            }
            $participants = [$t->proposer_id, $t->receiver_id];
            foreach ($participants as $uid) {
                if (!isset($userTradeStats[$uid])) continue;
                if ($t->status === 'completed')      $userTradeStats[$uid]['completed']++;
                else if ($t->status === 'cancelled') $userTradeStats[$uid]['cancelled']++;
                else                                 $userTradeStats[$uid]['active']++;
            }
        }

        // Pronostics par utilisateur (exact / bon vainqueur / perdus)
        $predictions = DB::table('match_predictions')
            ->join('club_matches', 'club_matches.id', '=', 'match_predictions.club_match_id')
            ->whereIn('club_matches.club_team_id', $clubIds)
            ->whereIn('match_predictions.user_id', $userIds)
            ->whereNotNull('club_matches.result_outcome')
            ->select(
                'match_predictions.user_id',
                'match_predictions.predicted_outcome',
                'match_predictions.predicted_home_score',
                'match_predictions.predicted_away_score',
                'club_matches.result_outcome',
                'club_matches.home_score',
                'club_matches.away_score'
            )
            ->get();
        $predictionStats = [];
        foreach ($userIds as $uid) {
            $predictionStats[$uid] = ['total' => 0, 'exact' => 0, 'winner' => 0, 'failed' => 0];
        }
        foreach ($predictions as $p) {
            $predictionStats[$p->user_id]['total']++;
            $isExact = $p->home_score !== null && $p->away_score !== null
                && $p->predicted_home_score === $p->home_score
                && $p->predicted_away_score === $p->away_score;
            $isWinner = $p->predicted_outcome === $p->result_outcome;
            if ($isExact) {
                $predictionStats[$p->user_id]['exact']++;
            } elseif ($isWinner) {
                $predictionStats[$p->user_id]['winner']++;
            } else {
                $predictionStats[$p->user_id]['failed']++;
            }
        }

        $collection = $users->map(fn($u) => [
            'id'           => $u->id,
            'name'         => $u->name,
            'email'        => $u->email,
            'unique_owned' => (int) ($statsPerUser[$u->id]->unique_owned ?? 0),
            'total_owned'  => (int) ($statsPerUser[$u->id]->total_owned ?? 0),
            'total'        => $totalCards,
            'percent'      => $totalCards > 0
                ? round(($statsPerUser[$u->id]->unique_owned ?? 0) / $totalCards * 100, 1)
                : 0,
            'packs_opened' => (int) ($packsOpened[$u->id] ?? 0),
            'trades'       => $userTradeStats[$u->id] ?? ['created' => 0, 'completed' => 0, 'cancelled' => 0, 'active' => 0],
            'predictions'  => $predictionStats[$u->id] ?? ['total' => 0, 'exact' => 0, 'winner' => 0, 'failed' => 0],
            'rarities'     => ($rarityPerUser[$u->id] ?? collect())->mapWithKeys(fn($r) => [$r->rarity => (int) $r->qty]),
        ])->sortByDesc('percent')->values();

        return response()->json([
            'club' => [
                'id'   => $clubTeam->id,
                'name' => $clubTeam->name,
                'slug' => $clubTeam->slug,
            ],
            'trades' => [
                'total'     => (int) $tradeSummary->total,
                'ongoing'   => (int) $tradeSummary->ongoing,
                'completed' => (int) $tradeSummary->completed,
                'cancelled' => (int) $tradeSummary->cancelled,
            ],
            'predictions' => [
                'total'    => (int) $predStats->total,
                'ongoing'  => (int) $predStats->ongoing,
                'rewarded' => (int) $predStats->rewarded,
                'by_match' => $matchPredictionBreakdown,
            ],
            'collection' => [
                'total_cards' => $totalCards,
                'users'       => $collection,
            ],
        ]);
    }

    public function stats(ClubTeam $clubTeam)
    {
        return response()->json([
            'club' => $clubTeam->only(['id', 'name', 'is_active']),
            'stats' => [
                'cards' => Card::where('club_team_id', $clubTeam->id)->count(),
                'packs' => Pack::where('club_team_id', $clubTeam->id)->count(),
                'trades' => Trade::where('club_team_id', $clubTeam->id)->count(),
                'predictions' => MatchPrediction::where('club_team_id', $clubTeam->id)->count(),
            ],
        ]);
    }
}
