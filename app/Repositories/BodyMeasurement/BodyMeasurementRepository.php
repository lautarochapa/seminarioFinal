<?php

namespace App\Repositories\BodyMeasurement;

use App\BodyMeasurement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BodyMeasurementRepository
{
    public function listForUser(int $userId, array $filters = []): LengthAwarePaginator
    {
        $query = BodyMeasurement::where('user_id', $userId)
            ->orderBy('measurement_date', 'desc')
            ->orderBy('id', 'desc');

        if (!empty($filters['date_from'])) {
            $query->where('measurement_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('measurement_date', '<=', $filters['date_to']);
        }

        $perPage = isset($filters['per_page']) ? min((int) $filters['per_page'], 100) : 20;
        return $query->paginate($perPage);
    }

    public function findByIdForUser(int $id, int $userId): ?BodyMeasurement
    {
        return BodyMeasurement::where('id', $id)->where('user_id', $userId)->first();
    }

    public function create(array $data): BodyMeasurement
    {
        return BodyMeasurement::create($data);
    }

    public function update(BodyMeasurement $measurement, array $data): BodyMeasurement
    {
        $measurement->fill($data)->save();
        return $measurement;
    }

    public function delete(BodyMeasurement $measurement): void
    {
        $measurement->delete();
    }
}
