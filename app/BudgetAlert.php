<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BudgetAlert extends Model
{
    public $timestamps = false;

    protected $fillable = ['budget_id', 'alert_type', 'message', 'severity', 'status', 'read_at', 'created_at'];

    protected $casts = ['read_at' => 'datetime', 'created_at' => 'datetime'];

    public function budget() { return $this->belongsTo(Budget::class); }
}
