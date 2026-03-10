<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trade extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'creator_id',
        'offered_card_id',
        'requested_card_id',
        'responder_id',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    // Relations
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function responder()
    {
        return $this->belongsTo(User::class, 'responder_id');
    }

    public function offeredCard()
    {
        return $this->belongsTo(Card::class, 'offered_card_id');
    }

    public function requestedCard()
    {
        return $this->belongsTo(Card::class, 'requested_card_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where(function($q) use ($userId) {
            $q->where('creator_id', $userId)
                ->orWhere('responder_id', $userId);
        });
    }

    // Vérifier si l'utilisateur peut accepter l'échange
    public function canAccept(User $user): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        // Vérifier que l'utilisateur possède la carte demandée
        $hasRequestedCard = $user->cards()
            ->where('card_id', $this->requested_card_id)
            ->exists();

        // Vérifier que ce n'est pas le créateur
        return $hasRequestedCard && $this->creator_id !== $user->id;
    }

    // Vérifier si l'utilisateur possède déjà la carte offerte
    public function userHasOfferedCard(User $user): bool
    {
        return $user->cards()
            ->where('card_id', $this->offered_card_id)
            ->exists();
    }
}

