<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IngredientCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'parent_id',
        'description',
        'sort_order',
        'is_active',
        'status',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(IngredientCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(IngredientCategory::class, 'parent_id');
    }

    public function activeChildren()
    {
        return $this->hasMany(IngredientCategory::class, 'parent_id')
            ->where('status', 'active')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->with('activeChildren');
    }

    public function ingredients()
    {
        return $this->hasMany(Ingredient::class, 'category_id');
    }

    public function budgetCategories()
    {
        return $this->hasMany(BudgetCategory::class);
    }
}
