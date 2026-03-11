<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pack;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PackController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Pack::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', '%' . $search . '%')
                ->orWhere('slug', 'like', '%' . $search . '%');
        }

        $packs = $query->latest()->paginate(20)->through(fn($pack) => [
            'id' => $pack->id,
            'name' => $pack->name,
            'slug' => $pack->slug,
            'description' => $pack->description,
            'image' => $pack->image_url,
            'price' => $pack->price,
            'money_price' => $pack->money_price,
            'card_count' => $pack->card_count,
            'is_active' => $pack->is_active,
            'rarity_boosts' => $pack->rarity_boosts,
        ]);

        return response()->json(['packs' => $packs]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:packs,slug',
            'description' => 'nullable|string',
            'price' => 'required|integer|min:0',
            'money_price' => 'required|integer|min:0',
            'card_count' => 'required|integer|min:1|max:99',
            'is_active' => 'boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'image_url' => 'nullable|string|max:255',
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);
        $validated['is_active'] = $request->boolean('is_active', true);

        if ($request->hasFile('image')) {
            $validated['image'] = $this->storeImage($request->file('image'));
        } elseif (!empty($validated['image_url'])) {
            $validated['image'] = $validated['image_url'];
        }

        unset($validated['image_url']);

        $pack = Pack::create($validated);

        return response()->json(['message' => 'Pack créé avec succès.', 'pack' => $pack], 201);
    }

    public function update(Request $request, Pack $pack): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:packs,slug,' . $pack->id,
            'description' => 'nullable|string',
            'price' => 'required|integer|min:0',
            'money_price' => 'required|integer|min:0',
            'card_count' => 'required|integer|min:1|max:99',
            'is_active' => 'boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'image_url' => 'nullable|string|max:255',
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);
        $validated['is_active'] = $request->boolean('is_active', $pack->is_active);

        if ($request->hasFile('image')) {
            $this->deleteImageIfLocal($pack->image);
            $validated['image'] = $this->storeImage($request->file('image'));
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

    public function destroy(Pack $pack): JsonResponse
    {
        $this->deleteImageIfLocal($pack->image);
        $pack->delete();

        return response()->json(['message' => 'Pack supprimé.']);
    }

    private function storeImage($file): string
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $directory = public_path('assets/packs');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
        $file->move($directory, $filename);
        return '/assets/packs/' . $filename;
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
