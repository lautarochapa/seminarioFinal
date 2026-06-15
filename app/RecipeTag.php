<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RecipeTag extends Model
{
    protected $fillable = ['code', 'name', 'description', 'type', 'status'];

    public function recipes()
    {
        return $this->belongsToMany(Recipe::class, 'recipe_tag_pivot')
            ->withPivot(['created_at']);
    }
}
