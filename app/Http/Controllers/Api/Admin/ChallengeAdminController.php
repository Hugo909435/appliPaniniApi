<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Challenge;
use App\Models\Medal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChallengeAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $challenges = Challenge::with(['rewardMedal', 'rewardCard'])->latest()->paginate(20);
        return response()->json(['challenges' => $challenges]);
    }

    public function show(Challenge $challenge): JsonResponse
    {
        $challenge->load(['rewardMedal', 'rewardCard']);
        return response()->json([
            'challenge' => $challenge,
            'medals' => Medal::all(['id', 'name']),
            'cards' => Card::all(['id', 'name']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:challenges',
            'description' => 'nullable|string',
            'type' => 'required|in:permanent,timed,daily',
            'metric' => 'required|string',
            'target' => 'required|integer|min:1',
            'reward_xp' => 'nullable|integer|min:0',
            'reward_coins' => 'nullable|integer|min:0',
            'reward_medal_id' => 'nullable|exists:medals,id',
            'reward_card_id' => 'nullable|exists:cards,id',
            'reward_card_quantity' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after:start_at',
        ]);

        $challenge = Challenge::create($validated);
        return response()->json(['message' => 'Challenge cr?? avec succ?s.', 'challenge' => $challenge], 201);
    }

    public function update(Request $request, Challenge $challenge): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:challenges,slug,' . $challenge->id,
            'description' => 'nullable|string',
            'type' => 'required|in:permanent,timed,daily',
            'metric' => 'required|string',
            'target' => 'required|integer|min:1',
            'reward_xp' => 'nullable|integer|min:0',
            'reward_coins' => 'nullable|integer|min:0',
            'reward_medal_id' => 'nullable|exists:medals,id',
            'reward_card_id' => 'nullable|exists:cards,id',
            'reward_card_quantity' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date',
        ]);

        $challenge->update($validated);
        return response()->json(['message' => 'Challenge mis ? jour avec succ?s.', 'challenge' => $challenge]);
    }

    public function toggleActive(Challenge $challenge): JsonResponse
    {
        $challenge->update(['is_active' => !$challenge->is_active]);
        return response()->json(['message' => 'Statut modifi?.', 'is_active' => $challenge->is_active]);
    }

    public function destroy(Challenge $challenge): JsonResponse
    {
        $challenge->delete();
        return response()->json(['message' => 'Challenge supprim? avec succ?s.']);
    }
}
