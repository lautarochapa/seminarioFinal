<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductNutrient extends Model
{
    protected $fillable = [
        'product_id',
        'nutrient_id',
        'amount_per_100g',
        'amount_per_serving',
        'serving_size',
        'source',
        'status',
    ];

    protected $casts = [
        'amount_per_100g' => 'decimal:4',
        'amount_per_serving' => 'decimal:4',
        'serving_size' => 'decimal:4',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function nutrient()
    {
        return $this->belongsTo(Nutrient::class);
    }
}
