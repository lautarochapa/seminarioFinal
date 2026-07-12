<?php

namespace App\Http\Requests\Api\V1\BodyMeasurement;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBodyMeasurementRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'measured_at'              => 'sometimes|nullable|date',
            'weight_kg'                => 'sometimes|nullable|numeric|min:0.01',
            'waist_cm'                 => 'sometimes|nullable|numeric|min:0.01',
            'blood_pressure_systolic'  => 'sometimes|nullable|integer|min:1',
            'blood_pressure_diastolic' => 'sometimes|nullable|integer|min:1',
            'glucose_level'            => 'sometimes|nullable|numeric|min:0.01',
            'notes'                    => 'sometimes|nullable|string|max:1000',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->errors()->isEmpty()) {
                return;
            }

            // Reject protected fields
            foreach (['user_id', 'created_by', 'updated_by', 'deleted_by', 'status', 'deleted_at'] as $field) {
                if ($this->has($field)) {
                    $validator->errors()->add($field, "El campo {$field} no está permitido.");
                }
            }

            // Pressure must be provided together
            $hasSystolic  = $this->has('blood_pressure_systolic')  && $this->input('blood_pressure_systolic')  !== null;
            $hasDiastolic = $this->has('blood_pressure_diastolic') && $this->input('blood_pressure_diastolic') !== null;

            if ($hasSystolic !== $hasDiastolic) {
                $validator->errors()->add('blood_pressure_diastolic', 'Sistólica y diastólica deben enviarse juntas.');
            } elseif ($hasSystolic && $hasDiastolic) {
                if ((int) $this->input('blood_pressure_systolic') <= (int) $this->input('blood_pressure_diastolic')) {
                    $validator->errors()->add('blood_pressure_systolic', 'La presión sistólica debe ser mayor que la diastólica.');
                }
            }
        });
    }

    public function validated()
    {
        $data = parent::validated();

        // Map measured_at → measurement_date
        if (array_key_exists('measured_at', $data)) {
            $data['measurement_date'] = $data['measured_at'];
            unset($data['measured_at']);
        }

        return $data;
    }
}
