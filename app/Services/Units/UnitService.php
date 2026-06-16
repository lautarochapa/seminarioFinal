<?php

namespace App\Services\Units;

use App\AuditLog;
use App\Exceptions\Units\UnitException;
use App\Repositories\Units\UnitRepository;
use App\UnitMeasure;
use Illuminate\Support\Facades\DB;

class UnitService
{
    const ALLOWED_TYPES = ['mass', 'volume', 'count', 'household', 'package'];

    private $units;

    public function __construct(UnitRepository $units)
    {
        $this->units = $units;
    }

    public function list(array $filters)
    {
        return $this->units->paginate($filters);
    }

    public function publicList(array $filters)
    {
        return $this->units->paginate($filters, true);
    }

    public function show($id)
    {
        return $this->units->findOrFail($id);
    }

    public function create($actorId, array $data, $ip, $userAgent)
    {
        $data = $this->prepare($data);

        if ($this->units->activeCodeExists($data['code'])) {
            throw new UnitException('UNIT_CODE_ALREADY_EXISTS', 'Ya existe una unidad activa con ese cÃ³digo.', 409);
        }

        return DB::transaction(function () use ($actorId, $data, $ip, $userAgent) {
            $unit = $this->units->create($data);
            $this->audit($actorId, 'unit.created', $unit->id, null, $this->payload($unit), $ip, $userAgent);

            return $unit;
        });
    }

    public function update($actorId, $id, array $data, $ip, $userAgent)
    {
        $unit = $this->units->findOrFail($id);
        $data = $this->prepare($data, false);

        if (array_key_exists('code', $data) && $this->units->activeCodeExists($data['code'], $unit->id)) {
            throw new UnitException('UNIT_CODE_ALREADY_EXISTS', 'Ya existe una unidad activa con ese cÃ³digo.', 409);
        }

        return DB::transaction(function () use ($actorId, $unit, $data, $ip, $userAgent) {
            $old = $this->payload($unit);
            $updated = $this->units->update($unit, $data);
            $new = $this->payload($updated);

            if ($old != $new) {
                $this->audit($actorId, 'unit.updated', $updated->id, $old, $new, $ip, $userAgent);
            }

            return $updated;
        });
    }

    public function delete($actorId, $id, $ip, $userAgent)
    {
        $unit = $this->units->findOrFail($id);

        return DB::transaction(function () use ($actorId, $unit, $ip, $userAgent) {
            $old = $this->payload($unit);
            $updated = $this->units->update($unit, ['status' => 'inactive']);
            $new = $this->payload($updated);
            $this->audit($actorId, 'unit.deleted', $updated->id, $old, $new, $ip, $userAgent);

            return $updated;
        });
    }

    public function restore($actorId, $id, $ip, $userAgent)
    {
        $unit = $this->units->findOrFail($id);

        if ($unit->status === 'active') {
            throw new UnitException('RESOURCE_NOT_DELETED', 'El recurso no estÃ¡ eliminado.', 409);
        }

        if ($this->units->activeCodeExists($unit->code, $unit->id)) {
            throw new UnitException('UNIT_CODE_ALREADY_EXISTS', 'Ya existe una unidad activa con ese cÃ³digo.', 409);
        }

        return DB::transaction(function () use ($actorId, $unit, $ip, $userAgent) {
            $old = $this->payload($unit);
            $updated = $this->units->update($unit, ['status' => 'active']);
            $new = $this->payload($updated);
            $this->audit($actorId, 'unit.restored', $updated->id, $old, $new, $ip, $userAgent);

            return $updated;
        });
    }

    private function prepare(array $data, $creating = true)
    {
        foreach (['code', 'name', 'type', 'symbol'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        if (array_key_exists('code', $data)) {
            $data['code'] = strtolower($data['code']);
        }

        if (array_key_exists('type', $data)) {
            $data['type'] = strtolower($data['type']);
        }

        if ($creating && ! array_key_exists('status', $data)) {
            $data['status'] = 'active';
        }

        return array_intersect_key($data, array_flip(['code', 'name', 'type', 'symbol', 'status']));
    }

    private function payload(UnitMeasure $unit)
    {
        return $unit->only(['code', 'name', 'type', 'symbol', 'status']);
    }

    private function audit($actorId, $action, $entityId, $old, $new, $ip, $userAgent)
    {
        AuditLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'entity_name' => 'unit_measures',
            'entity_id' => (string) $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
