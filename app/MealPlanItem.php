<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MealPlanItem extends Model
{
    use SoftDeletes;

    protected $fillable = ['meal_plan_id', 'date', 'meal_type_id', 'recipe_id', 'free_meal_description', 'is_eating_out', 'servings_total', 'notes', 'status'];

    protected $casts = [
        'date' => 'date',
        'is_eating_out' => 'boolean',
        'servings_total' => 'decimal:2',
    ];

    public function mealPlan()
    {
        return $this->belongsTo(MealPlan::class);
    }

    public function mealType()
    {
        return $this->belongsTo(MealType::class);
    }

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function portions()
    {
        return $this->hasMany(MealPlanItemPortion::class);
    }

    public function incompatibilities()
    {
        return $this->hasMany(MealPlanIncompatibility::class);
    }

    public function consumptionLogs()
    {
        return $this->hasMany(MealConsumptionLog::class);
    }
}
