<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RecipeCookLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'family_group_id', 'recipe_id', 'meal_plan_item_id', 'servings', 'cooked_at', 'stock_discounted', 'notes'];

    protected $casts = [
        'cooked_at' => 'datetime',
        'stock_discounted' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function familyGroup()
    {
        return $this->belongsTo(FamilyGroup::class);
    }

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }
}
