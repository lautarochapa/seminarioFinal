<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MealType extends Model
{
    protected $fillable = ['code', 'name', 'sort_order', 'status'];

    public function mealPlanItems()
    {
        return $this->hasMany(MealPlanItem::class);
    }
}
