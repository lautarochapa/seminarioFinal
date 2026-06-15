<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductBarcode extends Model
{
    protected $fillable = [
        'product_id',
        'barcode',
        'type',
        'status',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
