<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'module',
        'action',
        'description',
        'status',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions')->withPivot('created_at');
    }
}
