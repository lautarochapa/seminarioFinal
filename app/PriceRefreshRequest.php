<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PriceRefreshRequest extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'supermarket_chain_id',
        'supermarket_branch_id',
        'reason',
        'status',
        'requested_at',
        'processed_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function chain()
    {
        return $this->belongsTo(SupermarketChain::class, 'supermarket_chain_id');
    }

    public function branch()
    {
        return $this->belongsTo(SupermarketBranch::class, 'supermarket_branch_id');
    }
}
