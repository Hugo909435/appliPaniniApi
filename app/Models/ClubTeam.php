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
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function matches(): HasMany
    {
        return $this->hasMany(ClubMatch::class);
    }
}
