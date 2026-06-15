<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecipeCategory extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'parent_id', 'description', 'status'];

    public function parent()
    {
        return $this->belongsTo(RecipeCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(RecipeCategory::class, 'parent_id');
    }

    public function recipes()
    {
        return $this->hasMany(Recipe::class, 'category_id');
    }
}
