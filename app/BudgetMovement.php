<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BudgetMovement extends Model
{
    public $timestamps = false;

    protected $fillable = ['budget_id', 'movement_type', 'amount', 'related_purchase_id', 'related_shopping_list_id', 'description', 'created_at'];

    protected $casts = ['amount' => 'decimal:2', 'created_at' => 'datetime'];

    public function budget() { return $this->belongsTo(Budget::class); }
    public function purchase() { return $this->belongsTo(Purchase::class, 'related_purchase_id'); }
    public function shoppingList() { return $this->belongsTo(ShoppingList::class, 'related_shopping_list_id'); }
}
