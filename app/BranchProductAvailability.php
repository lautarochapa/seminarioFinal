<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BranchProductAvailability extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'supermarket_branch_id',
        'supermarket_product_id',
        'is_available',
        'last_checked_at',
        'source',
        'status',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'last_checked_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(SupermarketBranch::class, 'supermarket_branch_id');
    }

    public function supermarketProduct()
    {
        return $this->belongsTo(SupermarketProduct::class);
    }
}
