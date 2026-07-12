<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RecipeReviewLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['recipe_id', 'imported_recipe_candidate_id', 'reviewed_by', 'action', 'comments'];

    protected $casts = ['created_at' => 'datetime'];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function importedRecipeCandidate()
    {
        return $this->belongsTo(ImportedRecipeCandidate::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
