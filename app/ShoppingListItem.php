<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ShoppingListItem extends Model
{
    protected $fillable = [
        'shopping_list_id', 'ingredient_id', 'product_id', 'selected_supermarket_product_id',
        'quantity', 'unit_id', 'estimated_price', 'actual_price', 'status', 'notes',
        'price_source', 'price_updated_at', 'supermarket_chain_id', 'supermarket_branch_id',
        'source_type', 'source_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4', 'estimated_price' => 'decimal:2', 'actual_price' => 'decimal:2',
        'price_updated_at' => 'datetime',
    ];

    public function shoppingList() { return $this->belongsTo(ShoppingList::class); }
    public function ingredient() { return $this->belongsTo(Ingredient::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function selectedSupermarketProduct() { return $this->belongsTo(SupermarketProduct::class, 'selected_supermarket_product_id'); }
    public function unit() { return $this->belongsTo(UnitMeasure::class, 'unit_id'); }
    public function alternatives() { return $this->hasMany(ShoppingListItemAlternative::class); }
    public function scans() { return $this->hasMany(ShoppingSessionScan::class); }
    public function supermarketChain() { return $this->belongsTo(SupermarketChain::class, 'supermarket_chain_id'); }
    public function supermarketBranch() { return $this->belongsTo(SupermarketBranch::class, 'supermarket_branch_id'); }
}
