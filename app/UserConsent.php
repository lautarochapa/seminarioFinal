<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserConsent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'consent_type',
        'accepted',
        'accepted_at',
        'revoked_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'accepted' => 'boolean',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
