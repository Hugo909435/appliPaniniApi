<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rarity extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'color', 'drop_rate'];

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class, 'rarities_id');
    }

    protected function casts(): array
    {
        return [
            'drop_rate' => 'decimal:2',
        ];
    }
}
