<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserObjective extends Model
{
    protected $fillable = [
        'user_id',
        'objective_id',
        'priority',
        'target_value',
        'target_unit',
        'target_date',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'target_value' => 'decimal:2',
        'target_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function objective()
    {
        return $this->belongsTo(Objective::class);
    }
}
