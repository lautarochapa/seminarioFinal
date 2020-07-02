<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Utensil extends Model
{

    public function recipes()
    {
        return $this->belongsToMany(Recipe::class);
    }
}
