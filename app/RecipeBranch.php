<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RecipeBranch extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['original_recipe_id', 'branched_recipe_id', 'user_id', 'reason'];

    protected $casts = ['created_at' => 'datetime'];

    public function originalRecipe()
    {
        return $this->belongsTo(Recipe::class, 'original_recipe_id');
    }

    public function branchedRecipe()
    {
        return $this->belongsTo(Recipe::class, 'branched_recipe_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
