<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GeocodeCache extends Model
{
    protected $table = 'geocode_cache';

    const UPDATED_AT = null;

    protected $fillable = [
        'address',
        'latitude',
        'longitude',
        'provider',
        'raw_response',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'raw_response' => 'array',
        'created_at' => 'datetime',
    ];
}
