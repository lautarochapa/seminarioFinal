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
        return $this->belongsTo(Supply::class, 'supply_id')->select(array('id', 'nombre', 'medida', 'category_id'));;
    }


    public function scopeWithFilters($query)
    {
        return $query->when(count(request()->input('brands', [])), function ($query) {
                $query->whereIn('brand_id', request()->input('brands'));
            })
            ->when(count(request()->input('supplies', [])), function ($query) {
                $query->whereIn('supply_id', request()->input('supplies'));
            });
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
