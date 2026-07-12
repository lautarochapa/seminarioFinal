<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    protected $fillable = ['key', 'name', 'description', 'enabled'];

    protected $casts = ['enabled' => 'boolean'];
}
