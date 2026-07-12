<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FamilyGroupMember extends Model
{
    protected $fillable = [
        'family_group_id',
        'user_id',
        'role_in_group',
        'status',
        'joined_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    public function familyGroup()
    {
        return $this->belongsTo(FamilyGroup::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
