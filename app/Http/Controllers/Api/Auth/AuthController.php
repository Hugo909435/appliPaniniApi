<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'coins' => 100,
            'money' => 0,
            'level' => 1,
            'free_packs' => 0,
        ]);

        $token = $user->createToken('mobile')->plainTextToken;
        $user->load('profile.favoritePlayerCard.rarity', 'clubTeam');

        return response()->json([
            'user' => $this->formatUser($user),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = $request->user();
        $user->load('profile.favoritePlayerCard.rarity', 'clubTeam');
        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'user' => $this->formatUser($user),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('profile.favoritePlayerCard.rarity', 'clubTeam');

        return response()->json([
            'user' => $this->formatUser($user),
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json(['message' => __($status)], 400);
        }

        return response()->json(['message' => __($status)]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill(['password' => Hash::make($request->password)])->save();
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)], 400);
        }

        return response()->json(['message' => __($status)]);
    }

    private function formatUser(User $user): array
    {
        $profile = $user->profile;
        $level = $user->level ?? 1;
        $experience = $profile?->experience ?? 0;
        $xpForNextLevel = $profile?->getXpForNextLevel($level) ?? (max(1, $level) * 100);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'coins' => $user->coins,
            'money' => $user->money,
            'level' => $level,
            'experience' => $experience,
            'xp_for_next_level' => $xpForNextLevel,
            'free_packs' => $user->free_packs,
            'can_claim_free_pack' => $user->canClaimFreePack(),
            'is_admin' => $user->hasRole('admin'),
            'is_super_admin' => (bool) $user->is_super_admin,
            'club_team_id' => $user->club_team_id,
            'club_team' => $user->clubTeam ? [
                'id'         => $user->clubTeam->id,
                'name'       => $user->clubTeam->name,
                'logo'       => $user->clubTeam->logo,
                'theme_slug' => $user->clubTeam->theme_slug ?? 'default',
            ] : null,
            'created_at' => $user->created_at?->toDateTimeString(),
            'favorite_card' => $profile?->favoritePlayerCard ? [
                'id'     => $profile->favoritePlayerCard->id,
                'image'  => $profile->favoritePlayerCard->image_url,
                'rarity' => $profile->favoritePlayerCard->rarity?->slug,
            ] : null,
        ];
    }
}
