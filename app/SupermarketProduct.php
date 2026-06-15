<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SupermarketProduct extends Model
{
    protected $fillable = [
        'product_id',
        'supermarket_chain_id',
        'supermarket_branch_id',
        'external_product_id',
        'external_sku',
        'source_url',
        'source_name',
        'source_image_url',
        'last_seen_at',
        'last_scraped_at',
        'scrape_status',
        'status',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'last_scraped_at' => 'datetime',
    ];

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

    public function prices()
    {
        return $this->hasMany(SupermarketProductPrice::class);
    }

    public function availability()
    {
        return $this->hasMany(BranchProductAvailability::class);
    }
}
