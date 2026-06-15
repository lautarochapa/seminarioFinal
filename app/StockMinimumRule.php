<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StockMinimumRule extends Model
{
    protected $fillable = [
        'family_group_id',
        'product_id',
        'ingredient_id',
        'minimum_quantity',
        'unit_id',
        'status',
    ];

    protected $casts = [
        'minimum_quantity' => 'decimal:4',
    ];

    public function familyGroup()
    {
        return $this->belongsTo(FamilyGroup::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function unit()
    {
        return $this->belongsTo(UnitMeasure::class, 'unit_id');
    }
}
