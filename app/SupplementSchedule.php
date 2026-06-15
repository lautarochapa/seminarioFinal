<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SupplementSchedule extends Model
{
    protected $fillable = ['user_supplement_id', 'time_of_day', 'days_of_week', 'reminder_enabled', 'status'];

    protected $casts = ['days_of_week' => 'array', 'reminder_enabled' => 'boolean'];

    public function userSupplement() { return $this->belongsTo(UserSupplement::class); }
}
