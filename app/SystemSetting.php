<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'description', 'is_public'];

    protected $casts = ['is_public' => 'boolean'];
}
