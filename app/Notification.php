<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'family_group_id', 'type', 'title', 'message', 'channel', 'read_at', 'sent_at', 'status', 'created_at'];

    protected $casts = ['read_at' => 'datetime', 'sent_at' => 'datetime', 'created_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function familyGroup() { return $this->belongsTo(FamilyGroup::class); }
}
