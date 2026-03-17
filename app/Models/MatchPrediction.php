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
        'predicted_home_score',
        'predicted_away_score',
        'is_double',
        'rewarded_at',
    ];

    protected function casts(): array
    {
        return [
            'rewarded_at' => 'datetime',
            'predicted_home_score' => 'integer',
            'predicted_away_score' => 'integer',
            'is_double' => 'boolean',
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
