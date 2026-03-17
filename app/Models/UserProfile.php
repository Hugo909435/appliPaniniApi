<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'medal_slot_1_id',
        'medal_slot_2_id',
        'medal_slot_3_id',
        'favorite_player_card_id',
        'bio',
        'profile_theme',
        'profile_frame',
        'showcase_card_ids',
        'experience',
        'total_packs_opened',
        'total_trades_completed',
        'login_streak',
        'last_login',
        'active_title',
        'profile_public',
        'show_collection',
        'show_trades',
        'show_stats',
    ];

    protected $casts = [
        'showcase_card_ids' => 'array',
        'experience' => 'integer',
        'total_packs_opened' => 'integer',
        'total_trades_completed' => 'integer',
        'login_streak' => 'integer',
        'last_login' => 'datetime',
        'profile_public' => 'boolean',
        'show_collection' => 'boolean',
        'show_trades' => 'boolean',
        'show_stats' => 'boolean',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function medalSlot1()
    {
        return $this->belongsTo(Medal::class, 'medal_slot_1_id');
    }

    public function medalSlot2()
    {
        return $this->belongsTo(Medal::class, 'medal_slot_2_id');
    }

    public function medalSlot3()
    {
        return $this->belongsTo(Medal::class, 'medal_slot_3_id');
    }

    public function favoritePlayerCard()
    {
        return $this->belongsTo(Card::class, 'favorite_player_card_id');
    }

    public function showcaseCards()
    {
        if (empty($this->showcase_card_ids)) {
            return collect();
        }

        return Card::whereIn('id', $this->showcase_card_ids)
            ->get()
            ->sortBy(function($card) {
                return array_search($card->id, $this->showcase_card_ids);
            });
    }

    // Méthodes de gestion de la vitrine
    public function addToShowcase(int $cardId): bool
    {
        $showcase = $this->showcase_card_ids ?? [];

        if (count($showcase) >= 5) {
            return false;
        }

        if (in_array($cardId, $showcase)) {
            return false;
        }

        $showcase[] = $cardId;
        $this->showcase_card_ids = $showcase;
        $this->save();

        return true;
    }

    public function removeFromShowcase(int $cardId): bool
    {
        $showcase = $this->showcase_card_ids ?? [];

        if (!in_array($cardId, $showcase)) {
            return false;
        }

        $showcase = array_values(array_diff($showcase, [$cardId]));
        $this->showcase_card_ids = $showcase;
        $this->save();

        return true;
    }

    public function clearShowcase(): void
    {
        $this->showcase_card_ids = [];
        $this->save();
    }

    // Système d'expérience et niveau
    public function addExperience(int $amount): void
    {
        $this->experience += $amount;
        $this->checkLevelUp();
        $this->save();
    }

    private function checkLevelUp(): void
    {
        $this->loadMissing('user');
        if (!$this->user) {
            return;
        }
        $currentLevel = $this->user?->level ?? 1;
        $xpNeeded = $this->getXpForNextLevel($currentLevel);

        while ($this->experience >= $xpNeeded) {
            $currentLevel++;
            $this->experience -= $xpNeeded;
            $xpNeeded = $this->getXpForNextLevel($currentLevel);
            $this->grantLevelRewards($currentLevel);
        }

        if ($this->user && $this->user->level !== $currentLevel) {
            $this->user->forceFill(['level' => $currentLevel])->save();
        }
    }

    public function getXpForNextLevel(?int $level = null): int
    {
        $level = $level ?? ($this->user?->level ?? 1);
        return 100 + (max(1, $level) * 20);
    }

    public function getProgressToNextLevel(): float
    {
        $xpNeeded = $this->getXpForNextLevel();
        return $xpNeeded > 0 ? ($this->experience / $xpNeeded) * 100 : 0;
    }

    private function grantLevelRewards(int $level): void
    {
        // Every level up: +100 coins
        $this->user->addCoins(100);

        // Every 5 levels: +300 bonus coins
        if ($level % 5 === 0) {
            $this->user->addCoins(300);
        }

        // Every 10 levels: +1 pack (normal drop)
        if ($level % 10 === 0) {
            $this->user->increment('free_packs');
        }
    }

    // Login streak
    public function updateLoginStreak(): void
    {
        $now = now();

        if (!$this->last_login) {
            $this->login_streak = 1;
        } else {
            $daysSinceLastLogin = $this->last_login->diffInDays($now);

            if ($daysSinceLastLogin === 1) {
                $this->login_streak++;
            } elseif ($daysSinceLastLogin > 1) {
                $this->login_streak = 1;
            }
        }

        $this->last_login = $now;
        $this->save();
        $this->rewardStreak();
    }

    private function rewardStreak(): void
    {
        if ($this->login_streak === 7) {
            $this->user->addCoins(50);
            $this->addExperience(100);
        } elseif ($this->login_streak === 30) {
            $this->user->increment('free_packs');
            $this->addExperience(500);
        }
    }

    // Stats tracking
    public function incrementPacksOpened(): void
    {
        $this->increment('total_packs_opened');
        $this->addExperience(10);
    }

    public function incrementTradesCompleted(): void
    {
        $this->increment('total_trades_completed');
        $this->addExperience(25);
    }

    // Thèmes disponibles
    public function getAvailableThemes(): array
    {
        // Tous les thèmes disponibles pour tous les utilisateurs
        return [
            'classic',      // Blanc/Gris classique
            'dark',         // Mode sombre elegant
            'ocean',        // Bleu ocean
            'sunset',       // Orange/Rose coucher de soleil
            'forest',       // Vert foret
            'purple',       // Violet/Mauve
            'fire',         // Rouge/Orange feu
            'ice',          // Bleu glace
            'gold',         // Or luxueux
            'neon',         // Neon cyberpunk
            'midnight',     // Bleu nuit
            'rose',         // Rose pastel
        ];
    }

    // Cadres disponibles
    public function getAvailableFrames(): array
    {
        $frames = ['default'];
        $level = $this->user?->level ?? 1;

        if ($level >= 5) {
            $frames[] = 'bronze';
        }

        if ($level >= 15) {
            $frames[] = 'silver';
        }

        if ($level >= 30) {
            $frames[] = 'gold';
        }

        if ($this->user && $this->user->cards()->whereHas('rarity', fn($q) => $q->where('slug', 'legendary'))->count() >= 10) {
            $frames[] = 'legendary';
        }

        return $frames;
    }

    public function isComplete(): bool
    {
        return !empty($this->bio)
            && !is_null($this->favorite_player_card_id);
    }
}



