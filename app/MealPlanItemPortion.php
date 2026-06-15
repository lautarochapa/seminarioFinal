<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MealPlanItemPortion extends Model
{
    protected $fillable = ['meal_plan_item_id', 'user_id', 'portion_factor', 'servings', 'notes'];

    protected $casts = [
        'portion_factor' => 'decimal:4',
        'servings' => 'decimal:2',
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
