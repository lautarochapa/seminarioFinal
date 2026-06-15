<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'notification_type', 'app_enabled', 'email_enabled', 'push_enabled', 'frequency', 'status'];

    protected $casts = ['app_enabled' => 'boolean', 'email_enabled' => 'boolean', 'push_enabled' => 'boolean'];

    public function user() { return $this->belongsTo(User::class); }
}
