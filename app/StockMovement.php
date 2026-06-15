<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'family_group_id',
        'stock_item_id',
        'product_id',
        'movement_type',
        'quantity',
        'unit_id',
        'reason',
        'related_recipe_id',
        'related_purchase_id',
        'related_meal_plan_item_id',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
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

    public function unit()
    {
        return $this->belongsTo(UnitMeasure::class, 'unit_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
