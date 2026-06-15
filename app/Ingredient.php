<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingredient extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'normalized_name',
        'category_id',
        'base_unit_id',
        'description',
        'is_generic',
        'is_preparation',
        'is_supplement',
        'status',
    ];

    protected $casts = [
        'is_generic' => 'boolean',
        'is_preparation' => 'boolean',
        'is_supplement' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(IngredientCategory::class, 'category_id');
    }

    public function baseUnit()
    {
        return $this->belongsTo(UnitMeasure::class, 'base_unit_id');
    }

    public function nutrients()
    {
        return $this->belongsToMany(Nutrient::class, 'ingredient_nutrients')
            ->withPivot(['amount_per_100g', 'source', 'status'])
            ->withTimestamps();
    }

    public function nutrientValues()
    {
        return $this->hasMany(IngredientNutrient::class);
    }

    public function conversions()
    {
        return $this->hasMany(UnitConversion::class);
    }

    public function equivalencesFrom()
    {
        return $this->hasMany(IngredientEquivalence::class, 'source_ingredient_id');
    }

    public function equivalencesTo()
    {
        return $this->hasMany(IngredientEquivalence::class, 'target_ingredient_id');
    }

    public function tags()
    {
        return $this->belongsToMany(FoodTag::class, 'ingredient_tags')
            ->withPivot(['created_at']);
    }
}
