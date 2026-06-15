<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FamilyGroup extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'owner_user_id',
        'city_id',
        'default_address',
        'default_latitude',
        'default_longitude',
        'status',
    ];

    protected $casts = [
        'default_latitude' => 'decimal:7',
        'default_longitude' => 'decimal:7',
        'deleted_at' => 'datetime',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members()
    {
        return $this->hasMany(FamilyGroupMember::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'family_group_members')
            ->withPivot(['role_in_group', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function invitations()
    {
        return $this->hasMany(FamilyGroupInvitation::class);
    }

    public function preferences()
    {
        return $this->hasOne(FamilyGroupPreference::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
