<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MealConsumptionLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['meal_plan_item_id', 'user_id', 'consumed', 'portion_factor', 'notes'];

    protected $casts = [
        'consumed' => 'boolean',
        'portion_factor' => 'decimal:4',
        'created_at' => 'datetime',
    ];

    public function mealPlanItem()
    {
        return $this->belongsTo(MealPlanItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
