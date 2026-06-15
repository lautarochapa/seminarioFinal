<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Budget extends Model
{
    use SoftDeletes;

    protected $fillable = ['family_group_id', 'year', 'month', 'total_amount', 'currency', 'status'];

    protected $casts = ['total_amount' => 'decimal:2'];

    public function familyGroup() { return $this->belongsTo(FamilyGroup::class); }
    public function categories() { return $this->hasMany(BudgetCategory::class); }
    public function movements() { return $this->hasMany(BudgetMovement::class); }
    public function alerts() { return $this->hasMany(BudgetAlert::class); }
    public function projections() { return $this->hasMany(BudgetProjection::class); }
}
