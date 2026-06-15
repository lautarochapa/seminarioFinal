<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RecipeCostSnapshot extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'recipe_id',
        'family_group_id',
        'supermarket_chain_id',
        'supermarket_branch_id',
        'estimated_total_cost',
        'estimated_cost_per_serving',
        'calculated_at',
    ];

    protected $casts = [
        'estimated_total_cost' => 'decimal:2',
        'estimated_cost_per_serving' => 'decimal:2',
        'calculated_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function familyGroup()
    {
        return $this->belongsTo(FamilyGroup::class);
    }
}
