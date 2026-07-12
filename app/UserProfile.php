<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'birth_date',
        'gender',
        'height_cm',
        'current_weight_kg',
        'target_weight_kg',
        'activity_level',
        'meals_per_day',
        'uses_app_for_health',
        'uses_app_for_budget',
        'uses_app_for_organization',
        'notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'current_weight_kg' => 'decimal:2',
        'target_weight_kg' => 'decimal:2',
        'uses_app_for_health' => 'boolean',
        'uses_app_for_budget' => 'boolean',
        'uses_app_for_organization' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
