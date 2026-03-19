<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCard extends Model
{
    protected $fillable = [
        'user_id',
        'card_id',
        'quantity',
        'is_locked',
        'obtained_at',
    ];

    protected $casts = [
        'quantity'    => 'integer',
        'is_locked'   => 'boolean',
        'obtained_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
