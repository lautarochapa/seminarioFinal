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
        }])->with(['supplies' => function($query){
            $query->withCount('products');
        }]);;
    }

    public function allchildren() {
        return $this->children()->with('allchildren');
    }



    public function allchildren2() {
        return $this->allchildren()->sum('products_count');
    }



// Post model
public function comments()
{
  return $this->hasMany('Comment');
}
 
public function commentsCount()
{
  return $this->comments()
    ->selectRaw('post_id, count(*) as aggregate')
    ->groupBy('post_id');
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