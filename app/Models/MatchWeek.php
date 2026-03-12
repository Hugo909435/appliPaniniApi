<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchWeek extends Model
{
    protected $fillable = ['number', 'start_date', 'end_date', 'label'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function matches(): HasMany
    {
        return $this->hasMany(ClubMatch::class);
    }
}
