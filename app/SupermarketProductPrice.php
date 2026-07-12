<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SupermarketProductPrice extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'supermarket_product_id',
        'price',
        'unit_price',
        'currency',
        'price_type',
        'promotion_id',
        'scraped_at',
        'valid_from',
        'valid_to',
        'source',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'unit_price' => 'decimal:4',
        'scraped_at' => 'datetime',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function supermarketProduct()
    {
        return $this->belongsTo(SupermarketProduct::class);
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }
}
