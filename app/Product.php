<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{    


    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id')->select(array('id', 'nombre', 'padre'));;
    }

    public function supply()
    {
        return $this->belongsTo(Supply::class, 'supply_id')->select(array('id', 'nombre', 'medida', 'cantidad', 'category_id'));;
    }

//****+* */





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
