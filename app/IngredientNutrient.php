<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class IngredientNutrient extends Model
{
    protected $fillable = [
        'ingredient_id',
        'nutrient_id',
        'amount_per_100g',
        'source',
        'status',
    ];

    protected $casts = [
        'amount_per_100g' => 'decimal:4',
    ];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function nutrient()
    {
        return $this->belongsTo(Nutrient::class);
    }
}
