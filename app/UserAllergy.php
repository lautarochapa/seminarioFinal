<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserAllergy extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'allergy_id',
        'severity',
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

    public function allergy()
    {
        return $this->belongsTo(Allergy::class);
    }
}
