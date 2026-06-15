<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use SoftDeletes;

    protected $fillable = ['family_group_id', 'shopping_list_id', 'supermarket_branch_id', 'user_id', 'payment_method_id', 'purchase_date', 'estimated_total', 'actual_total', 'status'];

    protected $casts = ['purchase_date' => 'date', 'estimated_total' => 'decimal:2', 'actual_total' => 'decimal:2'];

    public function familyGroup() { return $this->belongsTo(FamilyGroup::class); }
    public function shoppingList() { return $this->belongsTo(ShoppingList::class); }
    public function branch() { return $this->belongsTo(SupermarketBranch::class, 'supermarket_branch_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function paymentMethod() { return $this->belongsTo(PaymentMethod::class); }
    public function items() { return $this->hasMany(PurchaseItem::class); }
    public function budgetMovements() { return $this->hasMany(BudgetMovement::class, 'related_purchase_id'); }
}
