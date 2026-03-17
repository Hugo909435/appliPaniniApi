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
        'coins_earned',
        'xp_earned',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'week_start_date' => 'date',
            'awarded_at'      => 'datetime',
            'claimed_at'      => 'datetime',
            'coins_earned'    => 'integer',
            'xp_earned'       => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
