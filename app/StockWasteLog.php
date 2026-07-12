<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StockWasteLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'family_group_id',
        'product_id',
        'stock_item_id',
        'quantity',
        'unit_id',
        'estimated_loss_amount',
        'expiration_date',
        'processed_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'estimated_loss_amount' => 'decimal:2',
        'expiration_date' => 'date',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function familyGroup()
    {
        return $this->belongsTo(FamilyGroup::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stockItem()
    {
        return $this->belongsTo(StockItem::class);
    }

    public function unit()
    {
        return $this->belongsTo(UnitMeasure::class, 'unit_id');
    }
}
