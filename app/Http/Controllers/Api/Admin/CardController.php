<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Position;
use App\Models\Rarity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Card::with(['position', 'rarity']);
        if ($request->filled('search')) $query->where('name', 'like', '%' . $request->search . '%');
        if ($request->filled('rarity')) {
            $rarity = Rarity::where('slug', $request->rarity)->first();
            if ($rarity) $query->where('rarities_id', $rarity->id);
        }
        if ($request->filled('position_id')) $query->where('positions_id', $request->position_id);

        $cards = $query->latest()->paginate(20)->through(fn($card) => [
            'id' => $card->id,
            'name' => $card->name,
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
        ]);

        return response()->json([
            'cards' => $cards,
            'positions' => Position::orderBy('name')->get(['id', 'name']),
            'rarities' => Rarity::orderBy('drop_rate', 'desc')->get(['id', 'name', 'slug', 'color']),
        ]);
    }

    public function show(Card $card): JsonResponse
    {
        $card->load(['position', 'rarity']);
        return response()->json([
            'card' => [
                'id' => $card->id,
                'name' => $card->name,
                'positions_id' => $card->positions_id,
                'rarities_id' => $card->rarities_id,
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
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'positions_id' => 'required|exists:positions,id',
            'rarities_id' => 'required|exists:rarities,id',
            'attack' => 'required|integer|min:0|max:99',
            'defense' => 'required|integer|min:0|max:99',
            'speed' => 'required|integer|min:0|max:99',
            'stamina' => 'required|integer|min:0|max:99',
            'number' => 'nullable|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $directory = public_path('assets/players');
            if (!File::exists($directory)) File::makeDirectory($directory, 0755, true);
            $file->move($directory, $filename);
            $validated['image'] = '/assets/players/' . $filename;
        }

        $card = Card::create($validated);
        return response()->json(['message' => 'Carte créée avec succès.', 'card' => $card], 201);
    }

    public function update(Request $request, Card $card): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'positions_id' => 'required|exists:positions,id',
            'rarities_id' => 'required|exists:rarities,id',
            'attack' => 'required|integer|min:0|max:99',
            'defense' => 'required|integer|min:0|max:99',
            'speed' => 'required|integer|min:0|max:99',
            'stamina' => 'required|integer|min:0|max:99',
            'number' => 'nullable|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            if ($card->image && File::exists(public_path($card->image))) File::delete(public_path($card->image));
            $file = $request->file('image');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $directory = public_path('assets/players');
            if (!File::exists($directory)) File::makeDirectory($directory, 0755, true);
            $file->move($directory, $filename);
            $validated['image'] = '/assets/players/' . $filename;
        } else {
            unset($validated['image']);
        }

        $card->update($validated);
        return response()->json(['message' => 'Carte mise à jour avec succès.', 'card' => $card]);
    }

    public function destroy(Card $card): JsonResponse
    {
        if ($card->image && File::exists(public_path($card->image))) File::delete(public_path($card->image));
        $card->delete();
        return response()->json(['message' => 'Carte supprimée avec succès.']);
    }
}



