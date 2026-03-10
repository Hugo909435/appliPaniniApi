<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserChallenge extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'challenge_id',
        'progress',
        'completed_at',
        'claimed_at',
        'period_start',
        'period_end',
        'last_progress_at',
    ];

    protected $casts = [
        'progress' => 'integer',
        'completed_at' => 'datetime',
        'claimed_at' => 'datetime',
        'period_start' => 'date',
        'period_end' => 'date',
        'last_progress_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }
}
