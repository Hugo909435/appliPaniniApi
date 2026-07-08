<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

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
        // Avoid mass-assigning privileged fields: roles must be set explicitly.
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

        // UPDATE conditionnel atomique : garantit qu'une seule requête concurrente
        // peut réclamer le pack de la semaine (empêche la double réclamation).
        $startOfWeek = now()->startOfWeek(\Carbon\Carbon::MONDAY);

        $affected = static::whereKey($this->getKey())
            ->where('free_packs', '<', self::MAX_FREE_PACKS)
            ->where(function ($q) use ($startOfWeek) {
                $q->whereNull('last_free_pack_claimed_at')
                  ->orWhere('last_free_pack_claimed_at', '<', $startOfWeek);
            })
            ->update([
                'free_packs'                => DB::raw('free_packs + 1'),
                'last_free_pack_claimed_at' => now(),
            ]);

        if ($affected === 0) {
            return false;
        }

        $this->refresh();
        return true;
    }

    public function useFreePack(): bool
    {
        // Décrément conditionnel atomique : évite la course où deux requêtes
        // consomment le même pack gratuit.
        $affected = static::whereKey($this->getKey())
            ->where('free_packs', '>', 0)
            ->decrement('free_packs');

        if ($affected === 0) {
            return false;
        }

        $this->free_packs = max(0, (int) $this->free_packs - 1);
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
        if ($amount <= 0) {
            return true;
        }

        // Débit conditionnel atomique : la ligne n'est décrémentée que si le solde
        // est suffisant, dans une seule requête SQL. Empêche le double achat en
        // conditions concurrentes (solde négatif / pack gratuit).
        $affected = static::whereKey($this->getKey())
            ->where('coins', '>=', $amount)
            ->decrement('coins', $amount);

        if ($affected === 0) {
            return false;
        }

        $this->coins = (int) $this->coins - $amount;
        return true;
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





