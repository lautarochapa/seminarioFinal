<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupermarketChain extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'website_url',
        'status',
    ];

    public function branches()
    {
        return $this->hasMany(SupermarketBranch::class);
    }

    public function products()
    {
        return $this->hasMany(SupermarketProduct::class);
    }

    public function promotions()
    {
        return $this->hasMany(Promotion::class);
    }
}
