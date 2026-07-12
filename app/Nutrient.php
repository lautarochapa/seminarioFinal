<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Nutrient extends Model
{
    protected $fillable = [
        'code',
        'name',
        'unit_id',
        'description',
        'status',
    ];

    public function unit()
    {
        return $this->belongsTo(UnitMeasure::class, 'unit_id');
    }

    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'ingredient_nutrients')
            ->withPivot(['amount_per_100g', 'source', 'status'])
            ->withTimestamps();
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_nutrients')
            ->withPivot(['amount_per_100g', 'amount_per_serving', 'serving_size', 'source', 'status'])
            ->withTimestamps();
    }

    public function ingredientNutrients()
    {
        return $this->hasMany(IngredientNutrient::class);
    }

    public function productNutrients()
    {
        return $this->hasMany(ProductNutrient::class);
    }
}
