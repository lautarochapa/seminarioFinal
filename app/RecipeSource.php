<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RecipeSource extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['recipe_id', 'source_url', 'source_site', 'source_author', 'imported_at'];

    protected $casts = ['imported_at' => 'datetime', 'created_at' => 'datetime'];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }
}
