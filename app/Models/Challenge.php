<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Challenge extends Model
{
    use HasFactory;

    const TYPE_PERMANENT = 'permanent';
    const TYPE_TIMED = 'timed';
    const TYPE_DAILY = 'daily';

    const METRIC_LOGIN_STREAK = 'login_streak';
    const METRIC_PACKS_OPENED = 'packs_opened';
    const METRIC_TRADES_COMPLETED = 'trades_completed';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'type',
        'metric',
        'target',
        'reward_xp',
        'reward_coins',
        'reward_medal_id',
        'reward_card_id',
        'reward_card_quantity',
        'start_at',
        'end_at',
        'is_active',
    ];

    protected $casts = [
        'target' => 'integer',
        'reward_xp' => 'integer',
        'reward_coins' => 'integer',
        'reward_card_quantity' => 'integer',
        'is_active' => 'boolean',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function userChallenges()
    {
        return $this->hasMany(UserChallenge::class);
    }
    public function rewardMedal()
    {
        return $this->belongsTo(Medal::class, 'reward_medal_id');
    }

    public function rewardCard()
    {
        return $this->belongsTo(Card::class, 'reward_card_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeTimed($query)
    {
        return $query->where('type', self::TYPE_TIMED);
    }

    public function scopeDaily($query)
    {
        return $query->where('type', self::TYPE_DAILY);
    }

    public function scopePermanent($query)
    {
        return $query->where('type', self::TYPE_PERMANENT);
    }
}
