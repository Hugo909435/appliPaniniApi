<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trade extends Model
{
    protected $fillable = [
        'proposer_id',
        'receiver_id',
        'status',
        'proposer_confirmed',
        'receiver_confirmed',
        'flagged',
        'flag_reason',
        'expires_at',
        'completed_at',
        'club_team_id',
        'offered_card_id',
        'requested_card_id',
    ];

    protected $casts = [
        'proposer_confirmed' => 'boolean',
        'receiver_confirmed' => 'boolean',
        'flagged'            => 'boolean',
        'expires_at'         => 'datetime',
        'completed_at'       => 'datetime',
        'club_team_id'       => 'integer',
    ];

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposer_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function offeredCard(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'offered_card_id');
    }

    public function requestedCard(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'requested_card_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TradeItem::class);
    }

    public function isPending(): bool   { return $this->status === 'pending'; }
    public function isAccepted(): bool  { return $this->status === 'accepted'; }
    public function isCompleted(): bool { return $this->status === 'completed'; }
    public function isActive(): bool    { return in_array($this->status, ['pending', 'accepted']); }

    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('proposer_id', $userId)->orWhere('receiver_id', $userId);
        });
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'accepted']);
    }
}
