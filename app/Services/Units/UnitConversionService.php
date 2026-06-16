<?php

namespace App\Services\Units;

use App\AuditLog;
use App\Exceptions\Units\UnitException;
use App\Ingredient;
use App\Repositories\Units\UnitConversionRepository;
use App\Repositories\Units\UnitRepository;
use App\UnitConversion;
use Illuminate\Support\Facades\DB;

class UnitConversionService
{
    private $conversions;
    private $units;

    public function __construct(UnitConversionRepository $conversions, UnitRepository $units)
    {
        $this->conversions = $conversions;
        $this->units = $units;
    }

    public function list(array $filters)
    {
        return $this->conversions->paginate($filters);
    }

    public function show($id)
    {
        return $this->conversions->findOrFail($id);
    }

    public function create($actorId, array $data, $ip, $userAgent)
    {
        $data = $this->prepare($data);
        $this->assertValid($data);

        if ($this->conversions->activeDuplicateExists($data['from_unit_id'], $data['to_unit_id'], $data['ingredient_id'] ?? null)) {
            throw new UnitException('UNIT_CONVERSION_ALREADY_EXISTS', 'Ya existe una conversiÃ³n activa con esa combinaciÃ³n.', 409);
        }

        return DB::transaction(function () use ($actorId, $data, $ip, $userAgent) {
            $conversion = $this->conversions->create($data);
            $this->audit($actorId, 'unit-conversion.created', $conversion->id, null, $this->payload($conversion), $ip, $userAgent);

            return $conversion;
        });
    }

    public function update($actorId, $id, array $data, $ip, $userAgent)
    {
        $conversion = $this->conversions->findOrFail($id);
        $data = $this->prepare($data, false);
        $merged = array_merge($conversion->only(['from_unit_id', 'to_unit_id', 'ingredient_id', 'factor', 'notes', 'status']), $data);
        $this->assertValid($merged);

        if ($this->conversions->activeDuplicateExists($merged['from_unit_id'], $merged['to_unit_id'], $merged['ingredient_id'] ?? null, $conversion->id)) {
            throw new UnitException('UNIT_CONVERSION_ALREADY_EXISTS', 'Ya existe una conversiÃ³n activa con esa combinaciÃ³n.', 409);
        }

        return DB::transaction(function () use ($actorId, $conversion, $data, $ip, $userAgent) {
            $old = $this->payload($conversion);
            $updated = $this->conversions->update($conversion, $data);
            $new = $this->payload($updated);

            if ($old != $new) {
                $this->audit($actorId, 'unit-conversion.updated', $updated->id, $old, $new, $ip, $userAgent);
            }

            return $updated;
        });
    }

    public function delete($actorId, $id, $ip, $userAgent)
    {
        $conversion = $this->conversions->findOrFail($id);

        return DB::transaction(function () use ($actorId, $conversion, $ip, $userAgent) {
            $old = $this->payload($conversion);
            $updated = $this->conversions->update($conversion, ['status' => 'inactive']);
            $new = $this->payload($updated);
            $this->audit($actorId, 'unit-conversion.deleted', $updated->id, $old, $new, $ip, $userAgent);

            return $updated;
        });
    }

    public function restore($actorId, $id, $ip, $userAgent)
    {
        $conversion = $this->conversions->findOrFail($id);

        if ($conversion->status === 'active') {
            throw new UnitException('RESOURCE_NOT_DELETED', 'El recurso no estÃ¡ eliminado.', 409);
        }

        if ($this->conversions->activeDuplicateExists($conversion->from_unit_id, $conversion->to_unit_id, $conversion->ingredient_id, $conversion->id)) {
            throw new UnitException('UNIT_CONVERSION_ALREADY_EXISTS', 'Ya existe una conversiÃ³n activa con esa combinaciÃ³n.', 409);
        }

        return DB::transaction(function () use ($actorId, $conversion, $ip, $userAgent) {
            $old = $this->payload($conversion);
            $updated = $this->conversions->update($conversion, ['status' => 'active']);
            $new = $this->payload($updated);
            $this->audit($actorId, 'unit-conversion.restored', $updated->id, $old, $new, $ip, $userAgent);

            return $updated;
        });
    }

    private function prepare(array $data, $creating = true)
    {
        if (array_key_exists('notes', $data) && is_string($data['notes'])) {
            $data['notes'] = trim($data['notes']);
        }

        if ($creating && ! array_key_exists('status', $data)) {
            $data['status'] = 'active';
        }

        return array_intersect_key($data, array_flip([
            'from_unit_id',
            'to_unit_id',
            'ingredient_id',
            'factor',
            'notes',
            'status',
        ]));
    }

    private function assertValid(array $data)
    {
        if ((int) $data['from_unit_id'] === (int) $data['to_unit_id']) {
            throw new UnitException('UNIT_CONVERSION_SAME_UNIT', 'La unidad origen y destino no pueden ser iguales.', 422);
        }

        if (! $this->units->activeById($data['from_unit_id']) || ! $this->units->activeById($data['to_unit_id'])) {
            throw new UnitException('UNIT_NOT_FOUND', 'La unidad indicada no existe o no estÃ¡ activa.', 422);
        }

        if (array_key_exists('ingredient_id', $data) && $data['ingredient_id'] !== null) {
            $ingredient = Ingredient::where('id', $data['ingredient_id'])->where('status', 'active')->first();
            if (! $ingredient) {
                throw new UnitException('INGREDIENT_INVALID', 'El ingrediente indicado no existe o no estÃ¡ activo.', 422);
            }
        }
    }

    private function payload(UnitConversion $conversion)
    {
        return [
            'from_unit_id' => $conversion->from_unit_id,
            'to_unit_id' => $conversion->to_unit_id,
            'ingredient_id' => $conversion->ingredient_id,
            'factor' => (string) $conversion->factor,
            'notes' => $conversion->notes,
            'status' => $conversion->status,
        ];
    }

    private function audit($actorId, $action, $entityId, $old, $new, $ip, $userAgent)
    {
        AuditLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'entity_name' => 'unit_conversions',
            'entity_id' => (string) $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
