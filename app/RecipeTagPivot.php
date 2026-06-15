<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RecipeTagPivot extends Model
{
    protected $table = 'recipe_tag_pivot';
    public $timestamps = false;

    protected $fillable = ['recipe_id', 'recipe_tag_id', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];
}
