<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StockAlert extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'family_group_id',
        'stock_item_id',
        'product_id',
        'alert_type',
        'message',
        'severity',
        'status',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function familyGroup()
    {
        return $this->belongsTo(FamilyGroup::class);
    }

    public function stockItem()
    {
        return $this->belongsTo(StockItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
