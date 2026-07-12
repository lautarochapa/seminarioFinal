<?php

namespace App\Http\Resources\Api\V1\BodyMeasurement;

use Illuminate\Http\Resources\Json\JsonResource;

class BodyMeasurementResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                       => $this->id,
            'measured_at'              => $this->measurement_date ? $this->measurement_date->format('Y-m-d') : null,
            'weight_kg'                => $this->weight_kg !== null ? (float) $this->weight_kg : null,
            'waist_cm'                 => $this->waist_cm !== null ? (float) $this->waist_cm : null,
            'blood_pressure_systolic'  => $this->blood_pressure_systolic,
            'blood_pressure_diastolic' => $this->blood_pressure_diastolic,
            'glucose_level'            => $this->glucose_level !== null ? (float) $this->glucose_level : null,
            'notes'                    => $this->notes,
            'created_at'               => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
