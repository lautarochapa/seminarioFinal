<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProfessionalUserLink extends Model
{
    protected $fillable = [
        'user_id',
        'professional_user_id',
        'can_view_profile',
        'can_view_stock',
        'can_view_meal_plans',
        'can_edit_meal_plans',
        'can_view_reports',
        'status',
        'granted_at',
        'revoked_at',
    ];

    protected $casts = [
        'can_view_profile' => 'boolean',
        'can_view_stock' => 'boolean',
        'can_view_meal_plans' => 'boolean',
        'can_edit_meal_plans' => 'boolean',
        'can_view_reports' => 'boolean',
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function professional()
    {
        return $this->belongsTo(User::class, 'professional_user_id');
    }
}
