<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MealPlan extends Model
{
    use SoftDeletes;

    protected $fillable = ['family_group_id', 'created_by', 'period_type', 'start_date', 'end_date', 'status', 'mode', 'config_json', 'approved_at'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'config_json' => 'array',
        'approved_at' => 'datetime',
    ];

    public function familyGroup()
    {
        return $this->belongsTo(FamilyGroup::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(MealPlanItem::class);
    }

    public function suggestions()
    {
        return $this->hasMany(MealPlanSuggestion::class);
    }

    public function incompatibilities()
    {
        return $this->hasMany(MealPlanIncompatibility::class);
    }
}
