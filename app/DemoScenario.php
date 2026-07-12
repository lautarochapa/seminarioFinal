<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DemoScenario extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'description', 'route', 'demo_user_id', 'status'];

    public function demoUser() { return $this->belongsTo(User::class, 'demo_user_id'); }
}
