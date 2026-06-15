<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UnitMeasure extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'symbol',
        'status',
    ];

    public function ingredients()
    {
        return $this->hasMany(Ingredient::class, 'base_unit_id');
    }

    public function nutrients()
    {
        return $this->hasMany(Nutrient::class, 'unit_id');
    }

    public function conversionsFrom()
    {
        return $this->hasMany(UnitConversion::class, 'from_unit_id');
    }

    public function conversionsTo()
    {
        return $this->hasMany(UnitConversion::class, 'to_unit_id');
    }
}
