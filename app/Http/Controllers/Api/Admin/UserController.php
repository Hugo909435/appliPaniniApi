<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $admin  = $request->user();
        $clubId = (!$admin->is_super_admin && $admin->club_team_id) ? $admin->club_team_id : null;

        $query = User::with('roles')
            ->where('is_super_admin', false)
            ->when($clubId, fn($q) => $q->where('club_team_id', $clubId));
        if ($request->filled('search')) {
            $query->where(fn($q) => $q
                ->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('email', 'like', '%' . $request->search . '%')
            );
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
            'status' => $u->status ?? 'active',
            'created_at' => $u->created_at?->toDateTimeString(),
        ]);
        return response()->json(['users' => $users]);
    }

    public function store(Request $request): JsonResponse
    {
        $admin = $request->user();

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|string|min:6',
            'club_team_id' => 'nullable|integer|exists:club_teams,id',
        ]);

        // Regular admin can only create users in their own club
        if (!$admin->is_super_admin) {
            $validated['club_team_id'] = $admin->club_team_id;
        }

        $user = User::create([
            'name'         => $validated['name'],
            'email'        => $validated['email'],
            'password'     => Hash::make($validated['password']),
            'club_team_id' => $validated['club_team_id'] ?? null,
        ]);

        return response()->json([
            'message' => 'Utilisateur créé.',
            'user' => [
                'id'             => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'coins'          => $user->coins ?? 0,
                'money'          => $user->money ?? 0,
                'is_admin'       => false,
                'is_super_admin' => false,
                'status'         => $user->status ?? 'active',
                'created_at'     => $user->created_at?->toDateTimeString(),
            ],
        ], 201);
    }

    private function authorizeClub(Request $request, User $target): bool
    {
        $admin = $request->user();
        if ($admin->is_super_admin) return true;
        return $admin->club_team_id && $admin->club_team_id === $target->club_team_id;
    }

    public function update(Request $request, User $user): JsonResponse
    {
        if (!$this->authorizeClub($request, $user)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6',
            'coins'    => 'nullable|integer|min:0',
            'money'    => 'nullable|integer|min:0',
        ]);

        $user->name  = $validated['name'];
        $user->email = $validated['email'];
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        if (isset($validated['coins'])) $user->coins = $validated['coins'];
        if (isset($validated['money'])) $user->money = $validated['money'];
        $user->save();

        return response()->json([
            'message' => 'Utilisateur mis à jour.',
            'user' => [
                'id'             => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'coins'          => $user->coins ?? 0,
                'money'          => $user->money ?? 0,
                'is_admin'       => $user->hasRole('admin'),
                'is_super_admin' => (bool) $user->is_super_admin,
                'status'         => $user->status ?? 'active',
                'created_at'     => $user->created_at?->toDateTimeString(),
            ],
        ]);
    }

    public function toggleAdmin(Request $request, User $user): JsonResponse
    {
        if (!$this->authorizeClub($request, $user)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        if ($user->hasRole('admin')) {
            $user->removeRole('admin');
            return response()->json(['message' => 'Admin retiré.', 'is_admin' => false]);
        }
        $user->assignRole('admin');
        return response()->json(['message' => 'Admin ajouté.', 'is_admin' => true]);
    }

    public function addCoins(Request $request, User $user): JsonResponse
    {
        if (!$this->authorizeClub($request, $user)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        $validated = $request->validate(['amount' => 'required|integer|min:1|max:100000']);
        $user->addCoins($validated['amount']);
        return response()->json(['message' => "Coins ajoutés.", 'coins' => $user->fresh()->coins]);
    }

    public function addPacks(Request $request, User $user): JsonResponse
    {
        if (!$this->authorizeClub($request, $user)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        $validated = $request->validate(['amount' => 'required|integer|min:1|max:100']);
        $user->increment('free_packs', $validated['amount']);
        return response()->json(['message' => "Packs ajoutés.", 'free_packs' => $user->fresh()->free_packs]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if (!$this->authorizeClub($request, $user)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        $user->delete();
        return response()->json(['message' => 'Utilisateur supprimé.']);
    }

    public function toggleStatus(Request $request, User $user): JsonResponse
    {
        if (!$this->authorizeClub($request, $user)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        $newStatus = ($user->status ?? 'active') === 'banned' ? 'active' : 'banned';
        $user->status = $newStatus;
        $user->save();

        return response()->json([
            'message' => 'Statut mis à jour.',
            'status'  => $newStatus,
        ]);
    }
}
