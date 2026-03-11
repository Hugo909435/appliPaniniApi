<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchPrediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_match_id',
        'user_id',
        'predicted_outcome',
        'rewarded_at',
    ];

    protected function casts(): array
    {
        return [
            'rewarded_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(ClubMatch::class, 'club_match_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
