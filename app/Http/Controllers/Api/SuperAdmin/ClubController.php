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
use Illuminate\Http\Request;
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
            'logo_file'    => ['nullable', 'image', 'max:2048'],
            'primary_color'=> ['nullable', 'string', 'max:9'],
            'theme_slug'   => ['nullable', 'string', 'in:default,red,orange,blue_yellow,blue,green_red'],
        ]);

        $club = new ClubTeam();
        $club->name = $data['name'];
        $club->short_name = $data['name'];
        if ($request->hasFile('logo_file')) {
            $path = $request->file('logo_file')->store('clubs', 'public');
            $club->logo = Storage::disk('public')->url($path);
        } elseif (isset($data['logo'])) {
            $club->logo = $data['logo'];
        }
        if (isset($data['primary_color'])) {
            $club->primary_color = $data['primary_color'];
        }
        $club->theme_slug = $data['theme_slug'] ?? 'default';
        // Les nouveaux clubs doivent être inactifs par défaut pour éviter une mise en ligne immédiate
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

        return response()->json(['message' => 'Club supprimé.']);
    }

    public function update(Request $request, ClubTeam $clubTeam)
    {
        $data = $request->validate([
            'name'          => ['nullable', 'string', 'max:255', 'unique:club_teams,name,' . $clubTeam->id],
            'short_name'    => ['nullable', 'string', 'max:255'],
            'theme_slug'    => ['nullable', 'string', 'in:default,red,orange,blue_yellow,blue,green_red'],
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
            $path = $request->file('logo_file')->store('clubs', 'public');
            $clubTeam->logo = Storage::disk('public')->url($path);
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
                    // Montrer aussi les comptes non assignés pour éviter les listes vides
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

        $cards = Card::with(['rarity', 'position'])
            ->where('club_team_id', $clubTeam->id)
            ->limit(200)
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'rarities_id' => $c->rarities_id,
                    'rarity_slug' => $c->rarity?->slug,
                    'rarity_label' => $c->rarity?->name,
                    'rarity_color' => $c->rarity?->color,
                    'positions_id' => $c->positions_id,
                    'position' => $c->position?->name,
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

        $matches = $clubTeam->matches()
            ->select('id', 'club_team_id', 'opponent_name', 'location', 'kickoff_at', 'is_home', 'home_score', 'away_score', 'is_cancelled')
            ->orderByDesc('kickoff_at')
            ->limit(100)
            ->get()
            ->map(function ($m) use ($clubTeam) {
                $clubName = $clubTeam->short_name ?? $clubTeam->name;
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
                'money_price' => $p->money_price,
                'card_count' => $p->card_count,
                'is_active' => (bool) $p->is_active,
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
