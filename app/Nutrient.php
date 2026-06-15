<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Nutrient extends Model
{
    protected $fillable = [
        'code',
        'name',
        'unit_id',
        'description',
        'status',
    ];

    public function unit()
    {
        return $this->belongsTo(UnitMeasure::class, 'unit_id');
    }

    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'ingredient_nutrients')
            ->withPivot(['amount_per_100g', 'source', 'status'])
            ->withTimestamps();
    }
}
