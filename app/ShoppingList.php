<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShoppingList extends Model
{
    use SoftDeletes;

    protected $fillable = ['family_group_id', 'meal_plan_id', 'created_by', 'source_type', 'status', 'estimated_total', 'selected_supermarket_branch_id', 'optimization_mode'];

    protected $casts = ['estimated_total' => 'decimal:2'];

    public function familyGroup() { return $this->belongsTo(FamilyGroup::class); }
    public function mealPlan() { return $this->belongsTo(MealPlan::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function selectedBranch() { return $this->belongsTo(SupermarketBranch::class, 'selected_supermarket_branch_id'); }
    public function items() { return $this->hasMany(ShoppingListItem::class); }
    public function sessions() { return $this->hasMany(ShoppingSession::class); }
    public function purchases() { return $this->hasMany(Purchase::class); }
    public function budgetMovements() { return $this->hasMany(BudgetMovement::class, 'related_shopping_list_id'); }
}
