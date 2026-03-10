<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with('roles');
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
        }
        $users = $query->latest()->paginate(20)->through(fn($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'coins' => $u->coins,
            'money' => $u->money,
            'level' => $u->level,
            'free_packs' => $u->free_packs,
            'is_admin' => $u->hasRole('admin'),
            'created_at' => $u->created_at->toDateTimeString(),
        ]);
        return response()->json(['users' => $users]);
    }

    public function toggleAdmin(Request $request, User $user): JsonResponse
    {
        if ($user->hasRole('admin')) {
            $user->removeRole('admin');
            return response()->json(['message' => 'Admin retiré.', 'is_admin' => false]);
        }
        $user->assignRole('admin');
        return response()->json(['message' => 'Admin ajouté.', 'is_admin' => true]);
    }

    public function addCoins(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate(['amount' => 'required|integer|min:1']);
        $user->addCoins($validated['amount']);
        return response()->json(['message' => "Coins ajoutés.", 'coins' => $user->fresh()->coins]);
    }

    public function addPacks(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate(['amount' => 'required|integer|min:1']);
        $user->increment('free_packs', $validated['amount']);
        return response()->json(['message' => "Packs ajoutés.", 'free_packs' => $user->fresh()->free_packs]);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();
        return response()->json(['message' => 'Utilisateur supprimé.']);
    }
}
