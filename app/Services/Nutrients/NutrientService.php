<?php

namespace App\Services\Nutrients;

use App\AuditLog;
use App\Exceptions\Nutrients\NutrientException;
use App\Nutrient;
use App\Repositories\Nutrients\NutrientRepository;
use App\UnitMeasure;
use Illuminate\Support\Facades\DB;

class NutrientService
{
    private $nutrients;

    public function __construct(NutrientRepository $nutrients)
    {
        $this->nutrients = $nutrients;
    }

    public function list(array $filters)
    {
        return $this->nutrients->paginate($filters);
    }

    public function show($id)
    {
        return $this->nutrients->findActiveOrFail($id);
    }

    public function create($actorId, array $data, $ip, $userAgent)
    {
        $data = $this->prepare($data, true);
        $this->assertUnit($data);

        if ($this->nutrients->activeCodeExists($data['code'])) {
            throw new NutrientException('NUTRIENT_CODE_DUPLICATE', 'Ya existe un nutriente activo con ese código.', 409);
        }

        return DB::transaction(function () use ($actorId, $data, $ip, $userAgent) {
            $nutrient = $this->nutrients->create($data);
            $this->audit($actorId, 'nutrient.created', $nutrient->id, null, $this->auditPayload($nutrient), $ip, $userAgent);

            return $nutrient;
        });
    }

    public function update($actorId, $id, array $data, $ip, $userAgent)
    {
        $nutrient = $this->nutrients->findActiveOrFail($id);
        $data     = $this->prepare($data, false, $nutrient);
        $this->assertUnit($data);

        if (array_key_exists('code', $data) && $this->nutrients->activeCodeExists($data['code'], $nutrient->id)) {
            throw new NutrientException('NUTRIENT_CODE_DUPLICATE', 'Ya existe un nutriente activo con ese código.', 409);
        }

        return DB::transaction(function () use ($actorId, $nutrient, $data, $ip, $userAgent) {
            $old     = $this->auditPayload($nutrient);
            $updated = $this->nutrients->update($nutrient, $data);
            $new     = $this->auditPayload($updated);

            if ($old != $new) {
                $this->audit($actorId, 'nutrient.updated', $updated->id, $old, $new, $ip, $userAgent);
            }

            return $updated;
        });
    }

    public function delete($actorId, $id, $ip, $userAgent)
    {
        $nutrient = $this->nutrients->findActiveOrFail($id);

        return DB::transaction(function () use ($actorId, $nutrient, $ip, $userAgent) {
            $old             = $this->auditPayload($nutrient);
            $nutrient->status = 'inactive';
            $nutrient->save();
            $deleted         = $this->nutrients->findOrFail($nutrient->id);
            $new             = $this->auditPayload($deleted);

            $this->audit($actorId, 'nutrient.deleted', $deleted->id, $old, $new, $ip, $userAgent);

            return $deleted;
        });
    }

    public function restore($actorId, $id, $ip, $userAgent)
    {
        $nutrient = $this->nutrients->findOrFail($id);

        if ($nutrient->status !== 'inactive') {
            throw new NutrientException('RESOURCE_NOT_DELETED', 'El nutriente no está eliminado.', 409);
        }

        if ($this->nutrients->activeCodeExists($nutrient->code, $nutrient->id)) {
            throw new NutrientException('NUTRIENT_RESTORE_CONFLICT', 'Ya existe un nutriente activo con el mismo código. No se puede restaurar.', 409);
        }

        return DB::transaction(function () use ($actorId, $nutrient, $ip, $userAgent) {
            $old             = $this->auditPayload($nutrient);
            $nutrient->status = 'active';
            $nutrient->save();
            $restored        = $this->nutrients->findOrFail($nutrient->id);
            $new             = $this->auditPayload($restored);

            $this->audit($actorId, 'nutrient.restored', $restored->id, $old, $new, $ip, $userAgent);

            return $restored;
        });
    }

    private function prepare(array $data, $creating, Nutrient $nutrient = null)
    {
        foreach (['code', 'name', 'description'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        if ($creating || array_key_exists('code', $data)) {
            $rawCode      = $data['code'] ?? ($nutrient ? $nutrient->code : '');
            $data['code'] = $this->normalizeCode($rawCode);
        }

        return array_intersect_key($data, array_flip(['code', 'name', 'unit_id', 'description', 'status']));
    }

    private function normalizeCode($code)
    {
        $code = strtolower(trim((string) $code));
        $code = preg_replace('/[^a-z0-9]+/', '_', $code);

        return trim($code, '_');
    }

    private function assertUnit(array $data)
    {
        if (! array_key_exists('unit_id', $data)) {
            return;
        }

        $unit = UnitMeasure::where('id', $data['unit_id'])->where('status', 'active')->first();

        if (! $unit) {
            throw new NutrientException('NUTRIENT_INVALID_UNIT', 'La unidad indicada no existe o no está activa.', 422);
        }
    }

    private function auditPayload(Nutrient $nutrient)
    {
        return [
            'code'        => $nutrient->code,
            'name'        => $nutrient->name,
            'unit_id'     => $nutrient->unit_id,
            'description' => $nutrient->description,
            'status'      => $nutrient->status,
        ];
    }

    private function audit($actorId, $action, $entityId, $old, $new, $ip, $userAgent)
    {
        AuditLog::create([
            'user_id'     => $actorId,
            'action'      => $action,
            'entity_name' => 'nutrients',
            'entity_id'   => (string) $entityId,
            'old_values'  => $old,
            'new_values'  => $new,
            'ip_address'  => $ip,
            'user_agent'  => $userAgent,
        ]);
    }
}
