<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{    

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function liists()
    {
        return $this->belongsToMany(Liist::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function recipes()
    {
        return $this->belongsToMany(Recipe::class);
    }

    public function diets()
    {
        return $this->belongsToMany(Diet::class);
    }
}
