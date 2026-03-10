<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoneyTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'money_package_id',
        'type',
        'amount',
        'price',
        'stripe_payment_id',
        'status',
        'description',
    ];

    protected $casts = [
        'amount' => 'integer',
        'price' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(MoneyPackage::class, 'money_package_id');
    }
}
