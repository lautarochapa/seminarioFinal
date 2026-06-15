<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
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
        return $this->belongsToMany(User::class, 'user_roles')->withPivot('created_at');
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')->withPivot('created_at');
    }
}
