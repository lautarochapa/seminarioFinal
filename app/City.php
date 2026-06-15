<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $fillable = [
        'name',
        'province',
        'country',
        'latitude',
        'longitude',
        'status',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function branches()
    {
        return $this->hasMany(SupermarketBranch::class);
    }

    public function familyGroups()
    {
        return $this->hasMany(FamilyGroup::class);
    }

    public function scrapingSources()
    {
        return $this->hasMany(ScrapingSource::class);
    }
}
