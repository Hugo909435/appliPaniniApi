<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    protected $fillable = ['user_id', 'started_at', 'ended_at', 'duration_seconds'];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_seconds' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ferme la session et calcule la durée.
     * Les sessions abandonnées (app tuée sans logout) sont fermées
     * après 30 min d'inactivité par le contrôleur de stats.
     */
    public function close(): void
    {
        if ($this->ended_at) {
            return;
        }

        $this->ended_at = now();
        $this->duration_seconds = (int) $this->started_at->diffInSeconds($this->ended_at);
        $this->save();
    }
}
