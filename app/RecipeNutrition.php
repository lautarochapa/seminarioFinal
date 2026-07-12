<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RecipeNutrition extends Model
{
    protected $table = 'recipe_nutrition';

    protected $fillable = [
        'recipe_id',
        'calories_total',
        'calories_per_serving',
        'protein_total',
        'protein_per_serving',
        'carbohydrates_total',
        'carbohydrates_per_serving',
        'fat_total',
        'fat_per_serving',
        'sodium_total',
        'sodium_per_serving',
        'sugar_total',
        'sugar_per_serving',
        'fiber_total',
        'fiber_per_serving',
        'calculation_status',
        'calculated_at',
    ];

    protected $casts = [
        'calculated_at'             => 'datetime',
        'calories_total'            => 'float',
        'calories_per_serving'      => 'float',
        'protein_total'             => 'float',
        'protein_per_serving'       => 'float',
        'carbohydrates_total'       => 'float',
        'carbohydrates_per_serving' => 'float',
        'fat_total'                 => 'float',
        'fat_per_serving'           => 'float',
        'sodium_total'              => 'float',
        'sodium_per_serving'        => 'float',
        'sugar_total'               => 'float',
        'sugar_per_serving'         => 'float',
        'fiber_total'               => 'float',
        'fiber_per_serving'         => 'float',
    ];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }
}
