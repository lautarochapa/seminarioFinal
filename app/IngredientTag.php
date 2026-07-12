<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class IngredientTag extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ingredient_id',
        'food_tag_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function foodTag()
    {
        return $this->belongsTo(FoodTag::class);
    }
}
