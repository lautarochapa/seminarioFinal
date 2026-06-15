<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DietaryRestriction extends Model
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
        return $this->belongsToMany(User::class, 'user_dietary_restrictions')
            ->withPivot(['notes', 'created_at']);
    }
}
