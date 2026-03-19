<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeItem extends Model
{
    protected $fillable = ['trade_id', 'owner_id', 'card_id', 'quantity'];

    public function trade(): BelongsTo  { return $this->belongsTo(Trade::class); }
    public function owner(): BelongsTo  { return $this->belongsTo(User::class, 'owner_id'); }
    public function card(): BelongsTo   { return $this->belongsTo(Card::class); }
}
