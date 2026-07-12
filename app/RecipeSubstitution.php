<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RecipeSubstitution extends Model
{
    protected $fillable = ['recipe_id', 'source_ingredient_id', 'target_ingredient_id', 'reason', 'status'];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function sourceIngredient()
    {
        return $this->belongsTo(Ingredient::class, 'source_ingredient_id');
    }

    public function targetIngredient()
    {
        return $this->belongsTo(Ingredient::class, 'target_ingredient_id');
    }
}
