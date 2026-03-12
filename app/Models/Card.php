<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Card extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'club_team_id',
        'positions_id',
        'rarities_id',
        'image',
        'attack',
        'defense',
        'speed',
        'stamina',
        'number',
        'is_exclusive',
    ];

    protected $appends = [
        'position_name',
        'rarity_slug',
        'rarity_label',
        'rarity_color',
        'overall',
        'image_url',
    ];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        if (str_starts_with($this->image, 'http') || str_starts_with($this->image, '/')) {
            return $this->image;
        }

        return '/assets/players/' . $this->image;
    }

    // Relations (noms simples pour utiliser avec ->with())

    public function clubTeam(): BelongsTo
    {
        return $this->belongsTo(ClubTeam::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'positions_id');
    }

    public function rarity(): BelongsTo
    {
        return $this->belongsTo(Rarity::class, 'rarities_id');
    }

    public function getPositionNameAttribute(): ?string
    {
        return $this->position?->name;
    }

    public function getRaritySlugAttribute(): ?string
    {
        return $this->rarity?->slug;
    }

    public function getRarityLabelAttribute(): ?string
    {
        return $this->rarity?->name;
    }

    public function getRarityColorAttribute(): ?string
    {
        return $this->rarity?->color;
    }

    public function getOverallAttribute(): int
    {
        return round(($this->attack + $this->defense + $this->speed + $this->stamina) / 4);
    }

    protected function casts(): array
    {
        return [
            'attack' => 'integer',
            'defense' => 'integer',
            'speed' => 'integer',
            'stamina' => 'integer',
            'number' => 'integer',
            'is_exclusive' => 'boolean',
        ];
    }
}


