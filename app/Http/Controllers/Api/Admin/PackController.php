<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pack;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PackController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Pack::query();
        $user = $request->user();
        if ($user && !$user->is_super_admin && $user->club_team_id) {
            $query->where('club_team_id', $user->club_team_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', '%' . $search . '%')
                ->orWhere('slug', 'like', '%' . $search . '%');
        }

        $packs = $query->latest()->paginate(20)->through(fn($pack) => [
            'id'            => $pack->id,
            'name'          => $pack->name,
            'slug'          => $pack->slug,
            'description'   => $pack->description,
            'image'         => $pack->image_url,
            'price'         => $pack->price,
            'card_count'    => $pack->card_count,
            'is_active'     => $pack->is_active,
            'rarity_boosts' => $pack->rarity_boosts,
        ]);

        return response()->json(['packs' => $packs]);
    }

    public function store(Request $request): JsonResponse
    {
        $admin = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:packs,slug',
            'description' => 'nullable|string',
            'price' => 'nullable|integer|min:0',
            'card_count' => 'nullable|integer|min:1|max:99',
            'is_active' => 'boolean',
            'club_team_id' => 'nullable|integer|exists:club_teams,id',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
            'image_url' => 'nullable|string|max:255',
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['price'] = $validated['price'] ?? 0;
        $validated['card_count'] = $validated['card_count'] ?? 5;

        // Scope to admin's club unless super admin provides explicit club_team_id
        if (!$admin->is_super_admin) {
            $validated['club_team_id'] = $admin->club_team_id;
        } elseif (empty($validated['club_team_id'])) {
            unset($validated['club_team_id']);
        }

        if ($request->hasFile('image')) {
            $validated['image'] = app(ImageService::class)->store($request->file('image'), 'assets/packs', 600, 900);
        } elseif (!empty($validated['image_url'])) {
            $validated['image'] = $validated['image_url'];
        }

        unset($validated['image_url']);

        $pack = Pack::create($validated);

        return response()->json(['message' => 'Pack créé avec succès.', 'pack' => $pack], 201);
    }

    public function update(Request $request, Pack $pack): JsonResponse
    {
        $admin = $request->user();
        if (!$admin->is_super_admin && $admin->club_team_id && $pack->club_team_id !== $admin->club_team_id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:packs,slug,' . $pack->id,
            'description' => 'nullable|string',
            'price' => 'nullable|integer|min:0',
            'card_count' => 'nullable|integer|min:1|max:99',
            'is_active' => 'boolean',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
            'image_url' => 'nullable|string|max:255',
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);
        $validated['is_active'] = $request->boolean('is_active', $pack->is_active);

        if ($request->hasFile('image')) {
            $this->deleteImageIfLocal($pack->image);
            $validated['image'] = app(ImageService::class)->store($request->file('image'), 'assets/packs', 600, 900);
        } elseif (!empty($validated['image_url'])) {
            $this->deleteImageIfLocal($pack->image);
            $validated['image'] = $validated['image_url'];
        } else {
            unset($validated['image']);
        }

        unset($validated['image_url']);

        $pack->update($validated);

        return response()->json(['message' => 'Pack mis à jour.', 'pack' => $pack]);
    }

    public function destroy(Request $request, Pack $pack): JsonResponse
    {
        $admin = $request->user();
        if (!$admin->is_super_admin && $admin->club_team_id && $pack->club_team_id !== $admin->club_team_id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $this->deleteImageIfLocal($pack->image);
        $pack->delete();

        return response()->json(['message' => 'Pack supprimé.']);
    }

    public function updateProbabilities(Request $request, Pack $pack): JsonResponse
    {
        $admin = $request->user();
        if (!$admin->is_super_admin && $admin->club_team_id && $pack->club_team_id !== $admin->club_team_id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $validated = $request->validate([
            'rarity_boosts'   => 'required|array',
            'rarity_boosts.*' => 'required|numeric|min:0|max:100',
        ]);

        $total = array_sum($validated['rarity_boosts']);
        if (abs($total - 100) > 0.01) {
            return response()->json(['message' => 'La somme des probabilités doit être égale à 100%.'], 422);
        }

        $pack->update(['rarity_boosts' => $validated['rarity_boosts']]);

        return response()->json(['message' => 'Probabilités mises à jour.', 'pack' => $pack]);
    }

    private function deleteImageIfLocal(?string $path): void
    {
        if (!$path) return;
        if (str_starts_with($path, '/assets/packs/')) {
            $fullPath = public_path(ltrim($path, '/'));
            if (File::exists($fullPath)) {
                File::delete($fullPath);
            }
        } elseif (!str_starts_with($path, 'http')) {
            $fullPath = public_path('assets/packs/' . ltrim($path, '/'));
            if (File::exists($fullPath)) {
                File::delete($fullPath);
            }
        }
    }
}
