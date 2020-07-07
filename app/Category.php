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
        return $this->children()->with('allchildren', ['supplies' => function($query){
            $query->withCount('products');
        }]);
    }
    public function parent() {
        return $this->belongsTo(Category::class,'padre');
    }


    public function supplies()
    {
        return $this->hasMany(Supply::class);
    }

}
/*
Tutorial::withCount('chapters')
->with(['chapters' => function($query){
    $query->withCount('videos');
}])
->all();
*/