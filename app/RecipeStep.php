<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RecipeStep extends Model
{
    protected $fillable = ['recipe_id', 'step_number', 'description', 'estimated_minutes'];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }
}
