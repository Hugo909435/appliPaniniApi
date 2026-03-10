<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserProfile;
use App\Models\Card;
use Illuminate\Database\Seeder;

class UserProfileSeeder extends Seeder
{
    public function run(): void
    {
        // R?cup?rer l'utilisateur 2
        $user = User::find(2);

        if (!$user) {
            $this->command->warn('Utilisateur 2 introuvable. Cr?ez-le d'abord.');
            return;
        }

        // Supprimer le profil existant s'il existe
        if ($user->profile) {
            $user->profile->delete();
        }

        $user->forceFill(['level' => 15])->save();

        // R?cup?rer des donn?es al?atoires pour remplir le profil
        $randomCard = $user->cards()->inRandomOrder()->first();

        if (!$randomCard) {
            $randomCard = Card::inRandomOrder()->first();
        }

        // R?cup?rer jusqu'? 5 cartes pour la vitrine
        $showcaseCards = $user->cards()
            ->inRandomOrder()
            ->limit(5)
            ->pluck('card_id')
            ->toArray();

        // Cr?er le profil
        $profile = UserProfile::create([
            'user_id' => $user->id,
            'bio' => 'Passionn? de football et collectionneur de cartes depuis toujours !',
            'profile_theme' => 'neon',
            'profile_frame' => 'gold',
            'favorite_player_card_id' => $randomCard?->id,
            'showcase_card_ids' => $showcaseCards,
            'experience' => 750,
            'total_packs_opened' => 42,
            'total_trades_completed' => 18,
            'login_streak' => 7,
            'last_login' => now(),
            'profile_public' => true,
            'show_collection' => true,
            'show_trades' => true,
            'show_stats' => true,
        ]);

        $this->command->info('Profil cr?? pour l'utilisateur : ' . $user->name);
        $this->command->table(
            ['Champ', 'Valeur'],
            [
                ['Niveau', $user->level ?? 1],
                ['XP', $profile->experience],
                ['Th?me', $profile->profile_theme],
                ['Cadre', $profile->profile_frame],
                ['Carte favorite', $randomCard?->name ?? 'Aucune'],
                ['Cartes en vitrine', count($showcaseCards)],
            ]
        );
    }
}

