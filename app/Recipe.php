<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{

    public function utensils()
    {
        return $this->belongsToMany(Utensil::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }
}
