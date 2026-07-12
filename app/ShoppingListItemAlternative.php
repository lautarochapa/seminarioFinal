<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ShoppingListItemAlternative extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['shopping_list_item_id', 'product_id', 'supermarket_product_id', 'price', 'reason', 'is_selected'];

    protected $casts = ['price' => 'decimal:2', 'is_selected' => 'boolean', 'created_at' => 'datetime'];

    public function shoppingListItem() { return $this->belongsTo(ShoppingListItem::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function supermarketProduct() { return $this->belongsTo(SupermarketProduct::class); }
}
