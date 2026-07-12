<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserPrioritySetting extends Model
{
    protected $fillable = [
        'user_id',
        'health_weight',
        'budget_weight',
        'time_weight',
        'stock_usage_weight',
        'preferred_mode',
    ];

    protected $casts = [
        'health_weight' => 'decimal:2',
        'budget_weight' => 'decimal:2',
        'time_weight' => 'decimal:2',
        'stock_usage_weight' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
