<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictionWeeklyBonus extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'week_start_date',
        'awarded_at',
    ];

    protected function casts(): array
    {
        return [
            'week_start_date' => 'date',
            'awarded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
