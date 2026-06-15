<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UnitConversion extends Model
{
    protected $fillable = [
        'from_unit_id',
        'to_unit_id',
        'ingredient_id',
        'factor',
        'notes',
        'status',
    ];

    protected $casts = [
        'factor' => 'decimal:8',
    ];

    public function fromUnit()
    {
        return $this->belongsTo(UnitMeasure::class, 'from_unit_id');
    }

    public function toUnit()
    {
        return $this->belongsTo(UnitMeasure::class, 'to_unit_id');
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
