<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{

    public function products()
    {
        return $this->hasManyThrough(Product::class, Supply::class);
    }

    public function children() {
        return $this->hasMany(Category::class,'padre')->withCount(['products' => function ($query) {
            $query->withFilters();
        }]);
    }

    public function allchildren() {
        return $this->children()->with('allchildren')->with(['supplies' => function($query){
            $query->withCount('products');
        }]);
    }



    public function allchildren2() {
        return $this->allchildren()->sum('products_count');
    }
    
    public function parent() {
        return $this->belongsTo(Category::class,'padre');
    }


    public function supplies()
    {
        return $this->hasMany(Supply::class);
    }



}