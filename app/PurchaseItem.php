<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $fillable = ['purchase_id', 'product_id', 'quantity', 'unit_id', 'unit_price', 'total_price', 'expiration_date', 'created_stock_item_id'];

    protected $casts = ['quantity' => 'decimal:4', 'unit_price' => 'decimal:2', 'total_price' => 'decimal:2', 'expiration_date' => 'date'];

    public function purchase() { return $this->belongsTo(Purchase::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function unit() { return $this->belongsTo(UnitMeasure::class, 'unit_id'); }
    public function createdStockItem() { return $this->belongsTo(StockItem::class, 'created_stock_item_id'); }
}
