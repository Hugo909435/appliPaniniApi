<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pack extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'price',
        'money_price', // ✅ AJOUTÉ
        'card_count',
        'is_active',
        'rarity_boosts',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'rarity_boosts' => 'array',
        'price' => 'integer',
        'money_price' => 'integer', // ✅ AJOUTÉ
        'card_count' => 'integer',
    ];

    protected $appends = ['image_url'];

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
