<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Allergy extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_allergies')
            ->withPivot(['severity', 'notes', 'created_at']);
    }
}
