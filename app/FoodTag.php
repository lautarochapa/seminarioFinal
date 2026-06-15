<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FoodTag extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'status',
    ];

    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'ingredient_tags')
            ->withPivot(['created_at']);
    }
}
