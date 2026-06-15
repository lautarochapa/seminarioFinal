<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LoginLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'email',
        'success',
        'ip_address',
        'user_agent',
        'failure_reason',
    ];

    protected $casts = [
        'success' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
