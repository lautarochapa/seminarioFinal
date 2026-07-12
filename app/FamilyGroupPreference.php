<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FamilyGroupPreference extends Model
{
    protected $fillable = [
        'family_group_id',
        'default_budget_mode',
        'default_shopping_mode',
        'default_recipe_priority_mode',
        'allow_auto_stock_discount',
    ];

    protected $casts = [
        'allow_auto_stock_discount' => 'boolean',
    ];

    public function familyGroup()
    {
        return $this->belongsTo(FamilyGroup::class);
    }
}
