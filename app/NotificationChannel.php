<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class NotificationChannel extends Model
{
    protected $fillable = ['code', 'name', 'description', 'status'];
}
