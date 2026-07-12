<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RecipeImage extends Model
{
    protected $fillable = ['recipe_id', 'image_url', 'source', 'is_primary', 'status'];

    protected $casts = ['is_primary' => 'boolean'];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }
}
