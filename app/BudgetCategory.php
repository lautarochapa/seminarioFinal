<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BudgetCategory extends Model
{
    protected $fillable = ['budget_id', 'product_category_id', 'ingredient_category_id', 'amount', 'status'];

    protected $casts = ['amount' => 'decimal:2'];

    public function budget() { return $this->belongsTo(Budget::class); }
    public function productCategory() { return $this->belongsTo(ProductCategory::class); }
    public function ingredientCategory() { return $this->belongsTo(IngredientCategory::class); }
}
