<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BudgetProjection extends Model
{
    public $timestamps = false;

    protected $fillable = ['budget_id', 'actual_spent', 'planned_reserved', 'real_available', 'projected_available', 'calculated_at', 'created_at'];

    protected $casts = [
        'actual_spent' => 'decimal:2',
        'planned_reserved' => 'decimal:2',
        'real_available' => 'decimal:2',
        'projected_available' => 'decimal:2',
        'calculated_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function budget() { return $this->belongsTo(Budget::class); }
}
