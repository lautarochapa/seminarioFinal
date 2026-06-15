<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupermarketBranch extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'supermarket_chain_id',
        'city_id',
        'name',
        'address',
        'latitude',
        'longitude',
        'phone',
        'opening_hours',
        'delivery_available',
        'pickup_available',
        'status',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'delivery_available' => 'boolean',
        'pickup_available' => 'boolean',
    ];

    public function chain()
    {
        return $this->belongsTo(SupermarketChain::class, 'supermarket_chain_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function products()
    {
        return $this->hasMany(SupermarketProduct::class);
    }

    public function promotions()
    {
        return $this->hasMany(Promotion::class);
    }

    public function availability()
    {
        return $this->hasMany(BranchProductAvailability::class);
    }
}
