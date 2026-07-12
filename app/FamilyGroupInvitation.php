<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FamilyGroupInvitation extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'family_group_id',
        'invited_email',
        'invited_user_id',
        'invited_by',
        'token',
        'status',
        'expires_at',
        'accepted_at',
        'rejected_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function familyGroup()
    {
        return $this->belongsTo(FamilyGroup::class);
    }

    public function invitedUser()
    {
        return $this->belongsTo(User::class, 'invited_user_id');
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
