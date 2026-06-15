<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MealPlanSuggestion extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['family_group_id', 'meal_plan_id', 'recipe_id', 'suggestion_reason', 'score', 'status'];

    protected $casts = ['score' => 'decimal:4', 'created_at' => 'datetime'];

    public function familyGroup()
    {
        return $this->belongsTo(FamilyGroup::class);
    }

    public function mealPlan()
    {
        return $this->belongsTo(MealPlan::class);
    }

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }
}
