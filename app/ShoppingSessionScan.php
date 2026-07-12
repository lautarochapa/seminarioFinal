<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ShoppingSessionScan extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['shopping_session_id', 'barcode', 'product_id', 'shopping_list_item_id', 'quantity', 'price', 'scan_result'];

    protected $casts = ['quantity' => 'decimal:4', 'price' => 'decimal:2', 'created_at' => 'datetime'];

    public function shoppingSession() { return $this->belongsTo(ShoppingSession::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function shoppingListItem() { return $this->belongsTo(ShoppingListItem::class); }
}
