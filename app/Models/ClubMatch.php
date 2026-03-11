<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClubMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_team_id',
        'opponent_name',
        'location',
        'kickoff_at',
        'is_home',
        'home_score',
        'away_score',
        'result_outcome',
        'result_set_at',
    ];

    protected function casts(): array
    {
        return [
            'kickoff_at' => 'datetime',
            'is_home' => 'boolean',
            'home_score' => 'integer',
            'away_score' => 'integer',
            'result_set_at' => 'datetime',
        ];
    }

    public function clubTeam(): BelongsTo
    {
        return $this->belongsTo(ClubTeam::class);
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(MatchPrediction::class);
    }

    public function computeOutcome(): ?string
    {
        if ($this->home_score === null || $this->away_score === null) {
            return null;
        }
        if ($this->home_score > $this->away_score) return 'home';
        if ($this->home_score < $this->away_score) return 'away';
        return 'draw';
    }
}
