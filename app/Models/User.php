<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, Billable;

    const MAX_FREE_PACKS = 1;

    protected $fillable = [
        'name',
        'email',
        'password',
        'coins',
        'money',
        'level',
        'free_packs',
        'last_free_pack_claimed_at',
        'club_team_id',
        'is_super_admin',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'coins' => 'integer',
            'money' => 'integer',
            'level' => 'integer',
            'free_packs' => 'integer',
            'last_free_pack_claimed_at' => 'datetime',
            'is_super_admin' => 'boolean',
            'status' => 'string',
        ];
    }

    public function clubTeam()
    {
        return $this->belongsTo(ClubTeam::class);
    }

    // ========== RELATIONS ==========

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function cards(): BelongsToMany
    {
        return $this->belongsToMany(Card::class, 'user_cards')
            ->withPivot('quantity', 'obtained_at')
            ->withTimestamps();
    }

    public function userCards(): HasMany
    {
        return $this->hasMany(UserCard::class);
    }

    public function matchPredictions(): HasMany
    {
        return $this->hasMany(MatchPrediction::class);
    }

    public function predictionWeeklyBonuses(): HasMany
    {
        return $this->hasMany(PredictionWeeklyBonus::class);
    }

    // ========== ROLES ==========

    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }

    public function assignRole(string $slug): void
    {
        $role = Role::where('slug', $slug)->first();
        if ($role && !$this->roles->contains($role->id)) {
            $this->roles()->attach($role->id);
        }
    }

    public function removeRole(string $slug): void
    {
        $role = Role::where('slug', $slug)->first();
        if ($role) {
            $this->roles()->detach($role->id);
        }
    }

    // ========== FREE PACKS ==========

    public function canClaimFreePack(): bool
    {
        if ($this->free_packs >= self::MAX_FREE_PACKS) {
            return false;
        }

        if (!$this->last_free_pack_claimed_at) {
            return true;
        }

        // Weekly check: can claim if last claimed before the start of the current week
        return $this->last_free_pack_claimed_at->lt(
            now()->startOfWeek(\Carbon\Carbon::MONDAY)
        );
    }

    public function claimFreePack(): bool
    {
        if (!$this->canClaimFreePack()) {
            return false;
        }

        $this->increment('free_packs');
        $this->update(['last_free_pack_claimed_at' => now()]);

        return true;
    }

    public function useFreePack(): bool
    {
        if ($this->free_packs <= 0) {
            return false;
        }

        $this->decrement('free_packs');
        return true;
    }

    public function hasFreePacks(): bool
    {
        return $this->free_packs > 0;
    }

    // ========== COINS ==========

    public function addCoins(int $amount): void
    {
        $this->increment('coins', $amount);
    }

    public function removeCoins(int $amount): bool
    {
        if ($this->coins < $amount) {
            return false;
        }

        $this->decrement('coins', $amount);
        return true;
    }

    public function moneyTransactions()
    {
        return $this->hasMany(MoneyTransaction::class);
    }


    // ========== CARDS ==========

    public function addCard(Card $card): UserCard
    {
        $userCard = UserCard::where('user_id', $this->id)
            ->where('card_id', $card->id)
            ->first();

        if ($userCard) {
            $userCard->increment('quantity');
            return $userCard;
        }

        return UserCard::create([
            'user_id' => $this->id,
            'card_id' => $card->id,
            'quantity' => 1,
        ]);
    }
    // Médailles (récompenses de challenges)
    public function medals()
    {
        return $this->belongsToMany(Medal::class, 'medal_user')
            ->withPivot('earned_at')
            ->withTimestamps();
    }

    public function friendshipsSent()
    {
        return $this->hasMany(Friendship::class, 'requester_id');
    }

    public function friendshipsReceived()
    {
        return $this->hasMany(Friendship::class, 'addressee_id');
    }


    public function profile()
    {
        return $this->hasOne(UserProfile::class)->withDefault([
            'experience' => 0,
            'profile_theme' => 'classic',
            'profile_frame' => 'default',
        ]);
    }

// Auto-création du profil
    protected static function booted()
    {
        static::created(function ($user) {
            $user->profile()->create([
                'experience' => 0,
            ]);

            if (is_null($user->level)) {
                $user->forceFill(['level' => 1])->saveQuietly();
            }
        });
    }

}





