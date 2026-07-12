<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RecipeIngredient extends Model
{
    protected $fillable = ['recipe_id', 'ingredient_id', 'specific_product_id', 'quantity', 'unit_id', 'is_optional', 'notes', 'sort_order'];

    protected $casts = [
        'quantity' => 'decimal:4',
        'is_optional' => 'boolean',
    ];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function specificProduct()
    {
        return $this->belongsTo(Product::class, 'specific_product_id');
    }

    public function unit()
    {
        return $this->belongsTo(UnitMeasure::class, 'unit_id');
    }
}
