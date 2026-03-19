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
        'money_price',
        'card_count',
        'is_active',
        'club_team_id',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'price'       => 'integer',
        'money_price' => 'integer',
        'card_count'  => 'integer',
        'club_team_id'=> 'integer',
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
