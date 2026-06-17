<?php

namespace App\Services\PersonalReports;

use App\BodyMeasurement;
use App\UserObjective;

class PersonalReportService
{
    const UNIT_TO_FIELD = [
        'kg' => 'weight_kg',
        'cm' => 'waist_cm',
    ];

    public function bodyProgress(int $userId, array $filters): array
    {
        $query = BodyMeasurement::where('user_id', $userId)
            ->orderBy('measurement_date');

        if (!empty($filters['date_from'])) {
            $query->whereDate('measurement_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('measurement_date', '<=', $filters['date_to']);
        }

        $measurements = $query->get([
            'id', 'measurement_date', 'weight_kg', 'waist_cm',
            'blood_pressure_systolic', 'blood_pressure_diastolic', 'glucose_level', 'notes',
        ]);

        if ($measurements->isEmpty()) {
            return [
                'total_measurements' => 0,
                'series'             => [],
                'summary'            => null,
            ];
        }

        $first   = $measurements->first();
        $current = $measurements->last();

        $summary = [];

        foreach (['weight_kg', 'waist_cm', 'glucose_level'] as $field) {
            $initial = $first->{$field} !== null ? (float) $first->{$field} : null;
            $latest  = $current->{$field} !== null ? (float) $current->{$field} : null;
            $variation = ($initial !== null && $latest !== null) ? round($latest - $initial, 2) : null;

            $unit = $field === 'weight_kg' ? 'kg' : ($field === 'waist_cm' ? 'cm' : 'mg/dL');

            $summary[$field] = [
                'initial'   => $initial,
                'current'   => $latest,
                'variation' => $variation,
                'unit'      => $unit,
            ];
        }

        return [
            'total_measurements' => $measurements->count(),
            'date_from'          => $first->measurement_date->toDateString(),
            'date_to'            => $current->measurement_date->toDateString(),
            'summary'            => $summary,
            'series'             => $measurements->map(function ($m) {
                return [
                    'date'                     => $m->measurement_date->toDateString(),
                    'weight_kg'                => $m->weight_kg !== null ? (float) $m->weight_kg : null,
                    'waist_cm'                 => $m->waist_cm !== null ? (float) $m->waist_cm : null,
                    'blood_pressure_systolic'  => $m->blood_pressure_systolic,
                    'blood_pressure_diastolic' => $m->blood_pressure_diastolic,
                    'glucose_level'            => $m->glucose_level !== null ? (float) $m->glucose_level : null,
                ];
            })->values()->toArray(),
        ];
    }

    public function objectivesProgress(int $userId, array $filters): array
    {
        $objectives = UserObjective::where('user_id', $userId)
            ->where('is_active', true)
            ->with('objective')
            ->orderBy('created_at')
            ->get();

        if ($objectives->isEmpty()) {
            return ['total' => 0, 'objectives' => []];
        }

        $latestMeasurement = BodyMeasurement::where('user_id', $userId)
            ->orderByDesc('measurement_date')
            ->first();

        $result = $objectives->map(function ($uo) use ($userId, $latestMeasurement) {
            $targetValue = $uo->target_value !== null ? (float) $uo->target_value : null;
            $targetUnit  = $uo->target_unit;

            $currentValue = $this->resolveCurrentValue($uo, $latestMeasurement);
            $initialValue = $this->resolveInitialValue($userId, $uo);

            $progressPercent = $this->computeProgress($initialValue, $currentValue, $targetValue);

            $hasData = $currentValue !== null;

            return [
                'user_objective_id'  => $uo->id,
                'objective_code'     => $uo->objective ? $uo->objective->code : null,
                'objective_name'     => $uo->objective ? $uo->objective->name : null,
                'target_value'       => $targetValue,
                'target_unit'        => $targetUnit,
                'target_date'        => $uo->target_date ? $uo->target_date->toDateString() : null,
                'initial_value'      => $initialValue,
                'current_value'      => $currentValue,
                'progress_percent'   => $progressPercent,
                'has_data'           => $hasData,
                'is_active'          => $uo->is_active,
                'notes'              => $uo->notes,
            ];
        })->values()->toArray();

        return ['total' => count($result), 'objectives' => $result];
    }

    private function resolveCurrentValue(UserObjective $uo, $latestMeasurement): ?float
    {
        if ($latestMeasurement === null) {
            return null;
        }

        $field = $uo->target_unit ? (self::UNIT_TO_FIELD[$uo->target_unit] ?? null) : null;

        if ($field === null) {
            return null;
        }

        return $latestMeasurement->{$field} !== null ? (float) $latestMeasurement->{$field} : null;
    }

    private function resolveInitialValue(int $userId, UserObjective $uo): ?float
    {
        $field = $uo->target_unit ? (self::UNIT_TO_FIELD[$uo->target_unit] ?? null) : null;

        if ($field === null) {
            return null;
        }

        $first = BodyMeasurement::where('user_id', $userId)
            ->whereDate('measurement_date', '>=', $uo->created_at->toDateString())
            ->whereNotNull($field)
            ->orderBy('measurement_date')
            ->value($field);

        return $first !== null ? (float) $first : null;
    }

    private function computeProgress(?float $initial, ?float $current, ?float $target): ?float
    {
        if ($initial === null || $current === null || $target === null) {
            return null;
        }

        $range = abs($target - $initial);
        if ($range == 0) {
            return 100.0;
        }

        return round((abs($current - $initial) / $range) * 100, 2);
    }
}
