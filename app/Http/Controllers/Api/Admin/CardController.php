<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\ClubTeam;
use App\Models\Pack;
use App\Models\Position;
use App\Models\Rarity;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Card::with(['position', 'rarity', 'clubTeam', 'pack']);
        if ($request->filled('search')) $query->where('name', 'like', '%' . $request->search . '%');
        if ($request->filled('rarity')) {
            $rarity = Rarity::where('slug', $request->rarity)->first();
            if ($rarity) $query->where('rarities_id', $rarity->id);
        }
        if ($request->filled('position_id')) $query->where('positions_id', $request->position_id);
        if ($request->filled('pack_id')) $query->where('pack_id', $request->pack_id);
        $user = $request->user();
        if ($user && !$user->is_super_admin && $user->club_team_id) {
            $query->where('club_team_id', $user->club_team_id);
        }

        $cards = $query->latest()->paginate(20)->through(fn($card) => [
            'id' => $card->id,
            'name' => $card->name,
            'description' => $card->description,
            'position' => $card->position?->name,
            'rarity' => $card->rarity?->slug,
            'rarity_label' => $card->rarity?->name,
            'rarity_color' => $card->rarity?->color,
            'image' => $card->image_url,
            'attack' => $card->attack,
            'defense' => $card->defense,
            'speed' => $card->speed,
            'stamina' => $card->stamina,
            'overall' => $card->overall,
            'number' => $card->number,
            'positions_id' => $card->positions_id,
            'rarities_id' => $card->rarities_id,
            'club_team_id' => $card->club_team_id,
            'pack_id' => $card->pack_id,
            'pack_name' => $card->pack?->name,
        ]);

        $packsQuery = Pack::orderBy('name');
        if ($user && !$user->is_super_admin && $user->club_team_id) {
            $packsQuery->where('club_team_id', $user->club_team_id);
        }

        return response()->json([
            'cards' => $cards,
            'positions' => Position::orderBy('name')->get(['id', 'name']),
            'rarities' => Rarity::orderBy('drop_rate', 'desc')->get(['id', 'name', 'slug', 'color']),
            'club_teams' => ClubTeam::orderBy('name')->get(['id', 'name', 'short_name', 'is_active']),
            'packs' => $packsQuery->get(['id', 'name', 'club_team_id']),
        ]);
    }

    public function show(Card $card): JsonResponse
    {
        $card->load(['position', 'rarity']);
        return response()->json([
            'card' => [
                'id' => $card->id,
                'name' => $card->name,
                'description' => $card->description,
                'positions_id' => $card->positions_id,
                'rarities_id' => $card->rarities_id,
                'club_team_id' => $card->club_team_id,
                'pack_id' => $card->pack_id,
                'attack' => $card->attack,
                'defense' => $card->defense,
                'speed' => $card->speed,
                'stamina' => $card->stamina,
                'number' => $card->number,
                'image' => $card->image_url,
                'overall' => $card->overall,
            ],
            'positions' => Position::orderBy('name')->get(['id', 'name']),
            'rarities' => Rarity::orderBy('drop_rate', 'desc')->get(['id', 'name', 'slug', 'color', 'drop_rate']),
            'packs' => Pack::orderBy('name')->get(['id', 'name', 'club_team_id']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'club_team_id' => 'nullable|exists:club_teams,id',
            'pack_id' => 'nullable|exists:packs,id',
            'positions_id' => 'required|exists:positions,id',
            'rarities_id' => 'required|exists:rarities,id',
            'attack' => 'required|integer|min:0|max:99',
            'defense' => 'required|integer|min:0|max:99',
            'speed' => 'required|integer|min:0|max:99',
            'stamina' => 'required|integer|min:0|max:99',
            'number' => 'nullable|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = app(ImageService::class)->store($request->file('image'), 'assets/players', 600, 900);
        }

        $card = Card::create($validated);
        return response()->json(['message' => 'Carte créée avec succès.', 'card' => $card], 201);
    }

    public function update(Request $request, Card $card): JsonResponse
    {
        $admin = $request->user();
        if (!$admin->is_super_admin && $admin->club_team_id && $card->club_team_id !== $admin->club_team_id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'club_team_id' => 'nullable|exists:club_teams,id',
            'pack_id' => 'nullable|exists:packs,id',
            'positions_id' => 'required|exists:positions,id',
            'rarities_id' => 'required|exists:rarities,id',
            'attack' => 'required|integer|min:0|max:99',
            'defense' => 'required|integer|min:0|max:99',
            'speed' => 'required|integer|min:0|max:99',
            'stamina' => 'required|integer|min:0|max:99',
            'number' => 'nullable|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
        ]);

        if ($request->hasFile('image')) {
            if ($card->image && str_starts_with($card->image, '/assets/players/')) {
                $old = public_path(ltrim($card->image, '/'));
                if (File::exists($old)) File::delete($old);
            }
            $validated['image'] = app(ImageService::class)->store($request->file('image'), 'assets/players', 600, 900);
        } else {
            unset($validated['image']);
        }

        $card->update($validated);
        return response()->json(['message' => 'Carte mise à jour avec succès.', 'card' => $card]);
    }

    public function destroy(Request $request, Card $card): JsonResponse
    {
        $admin = $request->user();
        if (!$admin->is_super_admin && $admin->club_team_id && $card->club_team_id !== $admin->club_team_id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        if ($card->image && File::exists(public_path($card->image))) File::delete(public_path($card->image));
        $card->delete();
        return response()->json(['message' => 'Carte supprimée avec succès.']);
    }
}



