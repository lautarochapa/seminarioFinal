<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{


    public function childrens() {
        return $this->hasMany('Category','padre');
    }
    public function parent() {
        return $this->belongsTo('Category','padre');
    }


    public function products()
    {
        return $this->belongsToMany(Product::class);
    }

    public function supplies()
    {
        return $this->hasMany('App\Supply');
    }

}
