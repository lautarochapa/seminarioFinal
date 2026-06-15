<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Objective extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'category',
        'status',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_objectives')
            ->withPivot(['priority', 'target_value', 'target_unit', 'target_date', 'is_active', 'notes'])
            ->withTimestamps();
    }
}
