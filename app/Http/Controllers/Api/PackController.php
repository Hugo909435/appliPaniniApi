<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pack;
use App\Models\Card;
use App\Services\PackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PackController extends Controller
{
    public function __construct(protected PackService $packService) {}

    // Labels et couleurs d'affichage (les probabilités viennent de PackService::RARITIES)
    private const RARITY_META = [
        'common'    => ['label' => 'Commun',     'color' => '#9CA3AF'],
        'uncommon'  => ['label' => 'Peu commun', 'color' => '#10B981'],
        'rare'      => ['label' => 'Rare',       'color' => '#3B82F6'],
        'epic'      => ['label' => 'Épique',     'color' => '#8B5CF6'],
        'legendary' => ['label' => 'Légendaire', 'color' => '#F59E0B'],
        'icone'     => ['label' => 'Icône',      'color' => '#EF4444'],
    ];

    private function buildRarities(?array $customBoosts = null, array $specialSlugs = [], bool $hasSpecial = false): array
    {
        $result = [];
        $baseProbabilities = PackService::RARITIES;

        foreach (self::RARITY_META as $slug => $meta) {
            $result[] = [
                'rarity'      => $slug,
                'label'       => $meta['label'],
                'color'       => $meta['color'],
                'probability' => $customBoosts !== null
                    ? (float) ($customBoosts[$slug] ?? 0)
                    : (float) ($baseProbabilities[$slug] ?? 0),
                'per_pack'    => $customBoosts !== null,
            ];
        }

        $result[] = [
            'rarity'      => 'special',
            'label'       => 'Spécial',
            'color'       => '#22D3EE',
            'probability' => $customBoosts !== null
                ? (float) ($customBoosts['special'] ?? 0)
                : (float) \App\Services\PackService::RARITIES['special'],
            'per_pack'    => $customBoosts !== null,
            'available'   => $hasSpecial,
        ];

        return $result;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $clubId = $user->club_team_id;
        // Calculé une fois pour tous les packs
        $baseSlugs    = ['common', 'uncommon', 'rare', 'epic', 'legendary', 'icone'];
        $specialSlugs = \DB::table('rarities')->whereNotIn('slug', $baseSlugs)->pluck('slug')->toArray();
        $hasSpecial   = !empty($specialSlugs)
            && Card::whereHas('rarity', fn($q) => $q->whereIn('slug', $specialSlugs))->exists();

        $packs = Pack::where('is_active', true)
            ->when($clubId, fn($q) => $q->where('club_team_id', $clubId))
            ->orderBy('price', 'asc')
            ->get()
            ->map(fn($pack) => [
                'id'          => $pack->id,
                'name'        => $pack->name,
                'slug'        => $pack->slug,
                'description' => $pack->description,
                'image'       => $pack->image_url,
                'price'       => $pack->price,
                'card_count'  => $pack->card_count,
                'rarities'    => $this->buildRarities($pack->rarity_boosts, $specialSlugs, $hasSpecial),
            ]);

        return response()->json([
            'packs' => $packs,
            'user' => [
                'coins' => $user->coins,
                'money' => $user->money,
                'free_packs' => $user->free_packs,
                'can_claim_free_pack' => $user->canClaimFreePack(),
                'time_until_next_claim' => $this->packService->getTimeUntilNextFreePack($user),
            ],
        ]);
    }

    public function opening(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pack_id'   => 'nullable|integer',
            'free'      => 'boolean',
            'count'     => 'integer|min:1|max:10',
        ]);

        $user     = $request->user();
        $isFree   = $request->boolean('free');
        $count    = (int) ($validated['count'] ?? 1);

        // Les packs gratuits ne sont pas multipliables
        if ($isFree) $count = 1;

        $packId = $validated['pack_id'] ?? null;
        $clubId = $user->club_team_id;

        if ($packId) {
            $pack = Pack::find($packId);
            if ($pack && $clubId && $pack->club_team_id && $pack->club_team_id !== $clubId) {
                return response()->json(['message' => 'Ce pack n\'appartient pas à votre club.'], 403);
            }
        } else {
            $pack = Pack::where('is_active', true)
                ->when($clubId, fn($q) => $q->where('club_team_id', $clubId))
                ->orderBy('price', 'asc')
                ->first();
        }

        if (!$pack) {
            return response()->json(['message' => 'Aucun pack disponible.'], 404);
        }

        // Vérification des ressources pour le total
        if ($isFree && !$user->hasFreePacks()) {
            return response()->json(['message' => "Vous n'avez pas de pack gratuit disponible."], 400);
        }
        if (!$isFree && $user->coins < $pack->price * $count) {
            return response()->json(['message' => "Pas assez de pièces pour {$count} pack(s)."], 400);
        }

        try {
            // Cartes possédées AVANT ouverture pour calculer is_new correctement
            // (évite les faux négatifs quand la même carte tombe 2× dans 10 packs)
            $ownedBeforeIds = $user->cards()->pluck('card_id')->flip()->all();

            [$allCards, $user] = DB::transaction(function () use ($user, $count, $isFree, $pack) {
                $allCards = collect();
                for ($i = 0; $i < $count; $i++) {
                    $result = $this->packService->openPack($user, $isFree && $i === 0, $pack->id);
                    $allCards = $allCards->merge($result['cards']);
                    $user     = $result['user'];
                }
                return [$allCards, $user];
            });
            $cards = $allCards;

            // Charge toutes les quantités en une seule requête
            $cardIds = $cards->pluck('id')->all();
            $userCardsMap = $user->cards()->whereIn('card_id', $cardIds)->get()->keyBy(fn($c) => $c->id);

            $formattedCards = $cards->map(function ($card) use ($user, $ownedBeforeIds, $userCardsMap) {
                $userCard = $userCardsMap->get($card->id);
                return [
                    'id' => $card->id,
                    'name' => $card->name,
                    'position' => $card->position?->name,
                    'rarity' => strtolower(trim($card->rarity?->slug ?? 'common')),
                    'rarity_label' => $card->rarity?->name,
                    'rarity_color' => $card->rarity?->color,
                    'image' => $card->image_url,
                    'attack' => $card->attack,
                    'defense' => $card->defense,
                    'speed' => $card->speed,
                    'stamina' => $card->stamina,
                    'overall' => $card->overall,
                    'number' => $card->number,
                    'is_new' => !array_key_exists($card->id, $ownedBeforeIds),
                    'quantity' => $userCard ? $userCard->pivot->quantity : 1,
                ];
            });

            return response()->json([
                'pack' => [
                    'id' => $pack->id,
                    'name' => $pack->name,
                    'slug' => $pack->slug,
                    'image' => $pack->image_url,
                    'card_count' => $pack->card_count,
                ],
                'cards' => $formattedCards,
                'has_prestige_card' => $formattedCards->contains(fn($c) => in_array($c['rarity'], ['epic', 'legendary', 'icone', 'buteur', 'passeur', 'joueur_debut'])),
                'user' => [
                    'coins' => $user->coins,
                    'money' => $user->money,
                    'free_packs' => $user->free_packs,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function claim(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->claimFreePack()) {
            return response()->json([
                'success' => false,
                'message' => "Vous ne pouvez pas encore récupérer de pack gratuit.",
            ], 400);
        }

        return response()->json([
            'success' => true,
            'free_packs' => $user->free_packs,
            'can_claim_free_pack' => $user->canClaimFreePack(),
            'time_until_next_claim' => $this->packService->getTimeUntilNextFreePack($user),
        ]);
    }
}
