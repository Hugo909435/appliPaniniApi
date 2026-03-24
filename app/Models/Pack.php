<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pack extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'price',
        'card_count',
        'is_active',
        'club_team_id',
        'rarity_boosts',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'price'         => 'integer',
        'card_count'    => 'integer',
        'club_team_id'  => 'integer',
        'rarity_boosts' => 'array',
    ];

    protected $appends = ['image_url'];

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        if (str_starts_with($this->image, 'http') || str_starts_with($this->image, '/')) {
            return $this->image;
        }

        return '/assets/packs/' . $this->image;
    }
}
