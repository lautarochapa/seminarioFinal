<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ImportedRecipeCandidate extends Model
{
    protected $fillable = [
        'source_url',
        'source_site',
        'raw_title',
        'raw_description',
        'raw_ingredients_json',
        'raw_steps_json',
        'raw_image_url',
        'parsed_recipe_json',
        'status',
        'reviewed_by',
        'reviewed_at',
        'created_recipe_id',
    ];

    protected $casts = [
        'raw_ingredients_json' => 'array',
        'raw_steps_json' => 'array',
        'parsed_recipe_json' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function createdRecipe()
    {
        return $this->belongsTo(Recipe::class, 'created_recipe_id');
    }
}
