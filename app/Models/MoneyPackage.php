<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MoneyPackage extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'amount',
        'price',
        'currency',
        'bonus',
        'is_popular',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'integer',
        'price' => 'integer',
        'bonus' => 'integer',
        'is_popular' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function getPriceInEurosAttribute(): float
    {
        return $this->price / 100;
    }

    public function getTotalMoneyAttribute(): int
    {
        return $this->amount + $this->bonus;
    }
}
