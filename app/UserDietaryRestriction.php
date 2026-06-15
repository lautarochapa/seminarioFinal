<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserDietaryRestriction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'dietary_restriction_id',
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

    public function dietaryRestriction()
    {
        return $this->belongsTo(DietaryRestriction::class);
    }
}
