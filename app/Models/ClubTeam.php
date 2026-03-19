<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClubTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'short_name',
        'is_active',
        'logo',
        'primary_color',
        'theme_slug',
        'is_main_club',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_main_club' => 'boolean',
        ];
    }

    public function matches(): HasMany
    {
        return $this->hasMany(ClubMatch::class);
    }
}
