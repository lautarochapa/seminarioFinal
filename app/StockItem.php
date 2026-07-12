<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'family_group_id',
        'product_id',
        'stock_location_id',
        'quantity',
        'unit_id',
        'purchase_date',
        'expiration_date',
        'opened_at',
        'is_open',
        'estimated_purchase_price',
        'status',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'purchase_date' => 'date',
        'expiration_date' => 'date',
        'opened_at' => 'datetime',
        'is_open' => 'boolean',
        'estimated_purchase_price' => 'decimal:2',
    ];

    public function familyGroup()
    {
        return $this->belongsTo(FamilyGroup::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function location()
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function unit()
    {
        return $this->belongsTo(UnitMeasure::class, 'unit_id');
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function alerts()
    {
        return $this->hasMany(StockAlert::class);
    }
}
