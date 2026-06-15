<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IngredientCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'parent_id',
        'description',
        'status',
    ];

    public function parent()
    {
        return $this->belongsTo(IngredientCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(IngredientCategory::class, 'parent_id');
    }

    public function ingredients()
    {
        return $this->hasMany(Ingredient::class, 'category_id');
    }
}
