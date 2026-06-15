<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ScrapingSource extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'base_url',
        'city_id',
        'is_active',
        'status',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function jobs()
    {
        return $this->hasMany(ScrapingJob::class, 'source_id');
    }

    public function candidates()
    {
        return $this->hasMany(ScrapedProductCandidate::class, 'source_id');
    }
}
