<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MealPlanPreference extends Model
{
    protected $fillable = ['family_group_id', 'user_id', 'avoid_repetition', 'respect_budget', 'respect_nutrition', 'respect_stock', 'preferred_mode', 'config_json'];

    protected $casts = [
        'avoid_repetition' => 'boolean',
        'respect_budget' => 'boolean',
        'respect_nutrition' => 'boolean',
        'respect_stock' => 'boolean',
        'config_json' => 'array',
    ];

    public function familyGroup()
    {
        return $this->belongsTo(FamilyGroup::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
