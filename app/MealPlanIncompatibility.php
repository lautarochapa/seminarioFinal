<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MealPlanIncompatibility extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['meal_plan_id', 'meal_plan_item_id', 'user_id', 'incompatibility_type', 'message', 'severity', 'status'];

    protected $casts = ['created_at' => 'datetime'];

    public function mealPlan()
    {
        return $this->belongsTo(MealPlan::class);
    }

    public function mealPlanItem()
    {
        return $this->belongsTo(MealPlanItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
