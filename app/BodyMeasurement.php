<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BodyMeasurement extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'weight_kg',
        'waist_cm',
        'blood_pressure_systolic',
        'blood_pressure_diastolic',
        'glucose_level',
        'measurement_date',
        'notes',
    ];

    protected $casts = [
        'weight_kg' => 'decimal:2',
        'waist_cm' => 'decimal:2',
        'glucose_level' => 'decimal:2',
        'measurement_date' => 'date',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
