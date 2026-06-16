<?php

namespace App\Services\BodyMeasurement;

use App\AuditLog;
use App\BodyMeasurement;
use App\Exceptions\BodyMeasurement\BodyMeasurementException;
use App\Repositories\BodyMeasurement\BodyMeasurementRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BodyMeasurementService
{
    private $repo;

    public function __construct(BodyMeasurementRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(int $actorId, array $filters): LengthAwarePaginator
    {
        return $this->repo->listForUser($actorId, $filters);
    }

    public function create(int $actorId, array $data, string $ip, string $ua): BodyMeasurement
    {
        $data['user_id'] = $actorId;

        $measurement = $this->repo->create($data);

        AuditLog::create([
            'user_id'     => $actorId,
            'action'      => 'body-measurement.created',
            'entity_name' => 'body_measurements',
            'entity_id'   => (string) $measurement->id,
            'old_values'  => [],
            'new_values'  => $data,
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $measurement;
    }

    public function update(int $id, int $actorId, array $data, string $ip, string $ua): BodyMeasurement
    {
        $measurement = $this->repo->findByIdForUser($id, $actorId);

        if (!$measurement) {
            throw new BodyMeasurementException('BODY_MEASUREMENT_NOT_FOUND', 'Medición no encontrada.', 404);
        }

        $fields  = ['weight_kg', 'waist_cm', 'blood_pressure_systolic', 'blood_pressure_diastolic', 'glucose_level', 'measurement_date', 'notes'];
        $before  = [];
        $after   = [];
        $changed = false;

        foreach ($data as $k => $v) {
            if (in_array($k, $fields) && $measurement->$k != $v) {
                $before[$k] = $measurement->$k;
                $after[$k]  = $v;
                $changed    = true;
            }
        }

        $measurement = $this->repo->update($measurement, $data);

        if ($changed) {
            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'body-measurement.updated',
                'entity_name' => 'body_measurements',
                'entity_id'   => (string) $id,
                'old_values'  => $before,
                'new_values'  => $after,
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);
        }

        return $measurement;
    }

    public function delete(int $id, int $actorId, string $ip, string $ua): BodyMeasurement
    {
        $measurement = $this->repo->findByIdForUser($id, $actorId);

        if (!$measurement) {
            throw new BodyMeasurementException('BODY_MEASUREMENT_NOT_FOUND', 'Medición no encontrada.', 404);
        }

        $snapshot = clone $measurement;
        $this->repo->delete($measurement);

        AuditLog::create([
            'user_id'     => $actorId,
            'action'      => 'body-measurement.deleted',
            'entity_name' => 'body_measurements',
            'entity_id'   => (string) $id,
            'old_values'  => $snapshot->only(['weight_kg', 'waist_cm', 'blood_pressure_systolic', 'blood_pressure_diastolic', 'glucose_level', 'measurement_date', 'notes']),
            'new_values'  => [],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $snapshot;
    }
}
