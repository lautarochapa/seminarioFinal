<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserHealthCondition extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'health_condition_id',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function healthCondition()
    {
        return $this->belongsTo(HealthCondition::class);
    }
}
