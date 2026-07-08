<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->showUser($request, null);
    }

    public function showUser(Request $request, $userId = null): JsonResponse
    {
        $user = $userId ? User::findOrFail($userId) : $request->user();
        $profile = $user->profile;

        if ($userId && !$profile->profile_public && $user->id !== $request->user()->id) {
            return response()->json(['message' => 'Ce profil est priv?.'], 403);
        }

        $profile->load(['medalSlot1', 'medalSlot2', 'medalSlot3', 'favoritePlayerCard.rarity']);

        $showcaseCards = $profile->showcaseCards()->map(fn($card) => [
            'id' => $card->id,
            'name' => $card->name,
            'image' => $card->image_url,
            'rarity' => $card->rarity?->slug,
            'overall' => $card->overall,
            'position' => $card->position?->name,
        ]);

        $totalCards = Card::count();
        $userCardsCount = $user->cards()->count();

        $recentCards = $user->userCards()
            ->with(['card.rarity'])
            ->orderBy('created_at', 'desc')
            ->take(6)->get()
            ->map(fn($uc) => [
                'id' => $uc->card->id,
                'name' => $uc->card->name,
                'image' => $uc->card->image_url,
                'rarity' => $uc->card->rarity?->slug,
                'obtained_at' => $uc->created_at->diffForHumans(),
            ]);

        $isOwnProfile = $user->id === $request->user()->id;

        // Données personnelles (email, soldes) réservées au propriétaire du profil.
        $profileUser = [
            'id' => $user->id,
            'name' => $user->name,
            'level' => $user->level ?? 1,
            'created_at' => $user->created_at->format('d/m/Y'),
        ];
        if ($isOwnProfile) {
            $profileUser['email'] = $user->email;
            $profileUser['coins'] = $user->coins;
            $profileUser['money'] = $user->money;
        }

        return response()->json([
            'profile_user' => $profileUser,
            'profile' => [
                'bio' => $profile->bio,
                'profile_theme' => $profile->profile_theme,
                'profile_frame' => $profile->profile_frame,
                'experience' => $profile->experience,
                'xp_for_next_level' => $profile->getXpForNextLevel(),
                'progress_to_next_level' => $profile->getProgressToNextLevel(),
                'total_packs_opened' => $profile->total_packs_opened,
                'total_trades_completed' => $profile->total_trades_completed,
                'login_streak' => $profile->login_streak,
                'active_title' => $profile->active_title,
                'is_complete' => $profile->isComplete(),
                'favorite_player_card' => $profile->favoritePlayerCard ? [
                    'id' => $profile->favoritePlayerCard->id,
                    'name' => $profile->favoritePlayerCard->name,
                    'image' => $profile->favoritePlayerCard->image_url,
                    'rarity' => $profile->favoritePlayerCard->rarity?->slug,
                    'overall' => $profile->favoritePlayerCard->overall,
                ] : null,
                'medals' => collect([$profile->medalSlot1, $profile->medalSlot2, $profile->medalSlot3])
                    ->filter()->values()
                    ->map(fn($m) => ['id' => $m->id, 'name' => $m->name, 'image' => $m->image_url, 'icon' => $m->icon_url, 'rarity' => $m->rarity]),
                'showcase_cards' => $showcaseCards,
            ],
            'stats' => [
                'total_cards' => $totalCards,
                'user_cards_count' => $userCardsCount,
                'completion_rate' => $totalCards > 0 ? round(($userCardsCount / $totalCards) * 100, 1) : 0,
            ],
            'recent_cards' => $recentCards,
            'is_own_profile' => $user->id === $request->user()->id,
        ]);
    }

    /**
     * Liste tous les utilisateurs (sauf soi-même) — pour sélectionner un destinataire d'échange.
     * Supporte ?search=xxx pour filtrer par nom.
     */
    public function listUsers(Request $request): JsonResponse
    {
        $me      = $request->user();
        $search  = $request->query('search');

        $users = User::where('id', '!=', $me->id)
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->select(['id', 'name'])
            ->get();

        return response()->json(['users' => $users]);
    }

    public function customize(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile;

        $userMedals = $user->medals()->get()->map(fn($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'image' => $m->image_url,
            'icon' => $m->icon_url,
            'rarity' => $m->rarity,
        ]);

        $userCards = $user->cards()->with(['rarity', 'position'])->get()->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'image' => $c->image_url,
            'rarity' => $c->rarity?->slug,
            'overall' => $c->overall,
            'position' => $c->position?->name,
        ]);

        return response()->json([
            'profile' => [
                'bio' => $profile->bio,
                'profile_theme' => $profile->profile_theme,
                'profile_frame' => $profile->profile_frame,
                'medal_slot_1_id' => $profile->medal_slot_1_id,
                'medal_slot_2_id' => $profile->medal_slot_2_id,
                'medal_slot_3_id' => $profile->medal_slot_3_id,
                'favorite_player_card_id' => $profile->favorite_player_card_id,
                'showcase_card_ids' => $profile->showcase_card_ids ?? [],
                'available_themes' => $profile->getAvailableThemes(),
                'available_frames' => $profile->getAvailableFrames(),
            ],
            'user_medals' => $userMedals,
            'user_cards' => $userCards,
        ]);
    }

    public function updateCustomization(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile()->firstOrCreate(['user_id' => $user->id], ['experience' => 0]);

        $validated = $request->validate([
            'bio' => ['nullable', 'string', 'max:100'],
            'profile_theme' => ['nullable', 'string'],
            'profile_frame' => ['nullable', 'string'],
            'medal_ids' => ['nullable', 'array', 'max:3'],
            'medal_ids.*' => ['integer', 'exists:medals,id'],
            'favorite_player_card_id' => ['nullable'],
            'showcase_card_ids' => ['nullable', 'array', 'max:5'],
        ]);

        $medalIds = array_values(array_unique(array_filter($validated['medal_ids'] ?? [])));
        $medalIds = array_slice($medalIds, 0, 3);

        $profile->fill([
            'bio' => $validated['bio'] ?? null,
            'profile_theme' => $validated['profile_theme'] ?? 'classic',
            'profile_frame' => $validated['profile_frame'] ?? 'default',
            'medal_slot_1_id' => $medalIds[0] ?? null,
            'medal_slot_2_id' => $medalIds[1] ?? null,
            'medal_slot_3_id' => $medalIds[2] ?? null,
            'favorite_player_card_id' => !empty($validated['favorite_player_card_id']) ? $validated['favorite_player_card_id'] : null,
            'showcase_card_ids' => $validated['showcase_card_ids'] ?? [],
        ])->save();

        return response()->json(['message' => 'Profil mis ? jour avec succ?s !']);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $request->user()->id],
        ]);

        $user = $request->user();
        if ($user->email !== $validated['email']) {
            $user->email_verified_at = null;
        }
        $user->fill($validated)->save();

        return response()->json(['message' => 'Informations mises ? jour !', 'user' => ['name' => $user->name, 'email' => $user->email]]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user();
        $user->currentAccessToken()->delete();
        $user->delete();
        return response()->json(['message' => 'Compte supprim? avec succ?s.']);
    }
}






