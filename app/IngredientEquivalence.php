<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class IngredientEquivalence extends Model
{
    protected $fillable = [
        'source_ingredient_id',
        'target_ingredient_id',
        'equivalence_type',
        'conversion_factor',
        'reason',
        'status',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:4',
    ];

    public function sourceIngredient()
    {
        return $this->belongsTo(Ingredient::class, 'source_ingredient_id');
    }

    public function targetIngredient()
    {
        return $this->belongsTo(Ingredient::class, 'target_ingredient_id');
    }
}
