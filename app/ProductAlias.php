<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductAlias extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'alias',
        'normalized_alias',
        'source',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
