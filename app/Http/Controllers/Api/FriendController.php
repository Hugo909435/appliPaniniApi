<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FriendController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $incoming = Friendship::where('addressee_id', $user->id)
            ->where('status', Friendship::STATUS_PENDING)
            ->latest()->get()
            ->map(fn($f) => $this->mapFriendship($f, $f->requester));

        $outgoing = Friendship::where('requester_id', $user->id)
            ->where('status', Friendship::STATUS_PENDING)
            ->latest()->get()
            ->map(fn($f) => $this->mapFriendship($f, $f->addressee));

        $friends = Friendship::where('status', Friendship::STATUS_ACCEPTED)
            ->where(fn($q) => $q->where('requester_id', $user->id)->orWhere('addressee_id', $user->id))
            ->latest()->get()
            ->map(function ($f) use ($user) {
                $other = $f->requester_id === $user->id ? $f->addressee : $f->requester;
                return $this->mapFriendship($f, $other);
            });

        return response()->json(['incoming' => $incoming, 'outgoing' => $outgoing, 'friends' => $friends]);
    }

    public function request(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:50']]);
        $user = $request->user();

        $target = User::where('name', $data['name'])->first();
        if (!$target) return response()->json(['message' => "Utilisateur introuvable."], 404);
        if ($target->id === $user->id) return response()->json(['message' => "Vous ne pouvez pas vous ajouter vous-même."], 422);

        $existing = Friendship::where(fn($q) => $q->where('requester_id', $user->id)->where('addressee_id', $target->id))
            ->orWhere(fn($q) => $q->where('requester_id', $target->id)->where('addressee_id', $user->id))
            ->first();

        if ($existing) {
            if ($existing->status === Friendship::STATUS_ACCEPTED) return response()->json(['message' => "Vous êtes déjà amis."], 422);
            if ($existing->status === Friendship::STATUS_PENDING) {
                if ($existing->requester_id === $user->id) return response()->json(['message' => "Demande déjà envoyée."], 422);
                return response()->json(['message' => "Cet utilisateur vous a déjà envoyé une demande."], 422);
            }
            if ($existing->status === Friendship::STATUS_BLOCKED) return response()->json(['message' => "Impossible d'ajouter cet utilisateur."], 422);

            $existing->forceFill(['requester_id' => $user->id, 'addressee_id' => $target->id, 'status' => Friendship::STATUS_PENDING])->save();
            return response()->json(['message' => "Demande d'amitié envoyée."]);
        }

        Friendship::create(['requester_id' => $user->id, 'addressee_id' => $target->id, 'status' => Friendship::STATUS_PENDING]);
        return response()->json(['message' => "Demande d'amitié envoyée."], 201);
    }

    public function accept(Request $request, Friendship $friendship): JsonResponse
    {
        $user = $request->user();
        if ($friendship->addressee_id !== $user->id) return response()->json(['message' => "Action non autorisée."], 403);
        if ($friendship->status !== Friendship::STATUS_PENDING) return response()->json(['message' => "Demande déjà traitée."], 422);
        $friendship->update(['status' => Friendship::STATUS_ACCEPTED]);
        return response()->json(['message' => "Ami ajouté."]);
    }

    public function decline(Request $request, Friendship $friendship): JsonResponse
    {
        $user = $request->user();
        if ($friendship->addressee_id !== $user->id) return response()->json(['message' => "Action non autorisée."], 403);
        if ($friendship->status !== Friendship::STATUS_PENDING) return response()->json(['message' => "Demande déjà traitée."], 422);
        $friendship->update(['status' => Friendship::STATUS_DECLINED]);
        return response()->json(['message' => "Demande refusée."]);
    }

    public function remove(Request $request, Friendship $friendship): JsonResponse
    {
        $user = $request->user();
        $isParticipant = $friendship->requester_id === $user->id || $friendship->addressee_id === $user->id;
        if (!$isParticipant) return response()->json(['message' => "Action non autorisée."], 403);
        if ($friendship->status === Friendship::STATUS_PENDING && $friendship->requester_id !== $user->id) return response()->json(['message' => "Action non autorisée."], 403);
        $friendship->delete();
        return response()->json(['message' => "Relation supprimée."]);
    }

    private function mapFriendship(Friendship $friendship, ?User $otherUser): array
    {
        return [
            'id' => $friendship->id,
            'status' => $friendship->status,
            'created_at' => $friendship->created_at?->toDateTimeString(),
            'user' => $otherUser ? [
                'id' => $otherUser->id,
                'name' => $otherUser->name,
            ] : null,
        ];
    }
}
