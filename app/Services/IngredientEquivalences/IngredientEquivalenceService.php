<?php

namespace App\Services\IngredientEquivalences;

use App\AuditLog;
use App\Exceptions\Ingredients\IngredientException;
use App\Ingredient;
use App\IngredientEquivalence;
use App\Repositories\IngredientEquivalences\IngredientEquivalenceRepository;
use Illuminate\Support\Facades\DB;

class IngredientEquivalenceService
{
    private $equivalences;

    public function __construct(IngredientEquivalenceRepository $equivalences)
    {
        $this->equivalences = $equivalences;
    }

    public function list(array $filters)
    {
        return $this->equivalences->paginate($filters);
    }

    public function show($id)
    {
        return $this->equivalences->findOrFail($id);
    }

    public function create($actorId, array $data, $ip, $userAgent)
    {
        $data = $this->prepare($data);
        $this->assertValid($data);

        if ($this->equivalences->activeDuplicateExists($data['source_ingredient_id'], $data['target_ingredient_id'], $data['equivalence_type'] ?? null)) {
            throw new IngredientException('INGREDIENT_EQUIVALENCE_ALREADY_EXISTS', 'Ya existe una equivalencia activa para esa combinación.', 409);
        }

        return DB::transaction(function () use ($actorId, $data, $ip, $userAgent) {
            $equivalence = $this->equivalences->create($data);
            $this->audit($actorId, 'ingredient-equivalence.created', $equivalence->id, null, $this->auditPayload($equivalence), $ip, $userAgent);

            return $equivalence;
        });
    }

    public function update($actorId, $id, array $data, $ip, $userAgent)
    {
        $equivalence = $this->equivalences->findOrFail($id);
        $data = $this->prepare($data);
        $merged = array_merge($this->rawPayload($equivalence), $data);
        $this->assertValid($merged);

        if ($this->equivalences->activeDuplicateExists($merged['source_ingredient_id'], $merged['target_ingredient_id'], $merged['equivalence_type'] ?? null, $equivalence->id)) {
            throw new IngredientException('INGREDIENT_EQUIVALENCE_ALREADY_EXISTS', 'Ya existe una equivalencia activa para esa combinación.', 409);
        }

        return DB::transaction(function () use ($actorId, $equivalence, $data, $ip, $userAgent) {
            $old = $this->auditPayload($equivalence);
            $updated = $this->equivalences->update($equivalence, $data);
            $new = $this->auditPayload($updated);

            if ($old != $new) {
                $this->audit($actorId, 'ingredient-equivalence.updated', $updated->id, $old, $new, $ip, $userAgent);
            }

            return $updated;
        });
    }

    public function delete($actorId, $id, $ip, $userAgent)
    {
        $equivalence = $this->equivalences->findOrFail($id);

        return DB::transaction(function () use ($actorId, $equivalence, $ip, $userAgent) {
            $old = $this->auditPayload($equivalence);
            $updated = $this->equivalences->update($equivalence, ['status' => 'inactive']);
            $new = $this->auditPayload($updated);

            $this->audit($actorId, 'ingredient-equivalence.deleted', $updated->id, $old, $new, $ip, $userAgent);

            return $updated;
        });
    }

    public function restore($actorId, $id, $ip, $userAgent)
    {
        $equivalence = $this->equivalences->findOrFail($id);

        if ($equivalence->status === 'active') {
            throw new IngredientException('RESOURCE_NOT_DELETED', 'El recurso no está eliminado.', 409);
        }

        $data = $this->rawPayload($equivalence);
        $this->assertValid($data);

        if ($this->equivalences->activeDuplicateExists($data['source_ingredient_id'], $data['target_ingredient_id'], $data['equivalence_type'] ?? null, $equivalence->id)) {
            throw new IngredientException('INGREDIENT_EQUIVALENCE_ALREADY_EXISTS', 'Ya existe una equivalencia activa para esa combinación.', 409);
        }

        return DB::transaction(function () use ($actorId, $equivalence, $ip, $userAgent) {
            $old = $this->auditPayload($equivalence);
            $updated = $this->equivalences->update($equivalence, ['status' => 'active']);
            $new = $this->auditPayload($updated);

            $this->audit($actorId, 'ingredient-equivalence.restored', $updated->id, $old, $new, $ip, $userAgent);

            return $updated;
        });
    }

    private function prepare(array $data)
    {
        foreach (['equivalence_type', 'reason', 'status'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        if (array_key_exists('equivalence_type', $data) && $data['equivalence_type'] === '') {
            $data['equivalence_type'] = null;
        }

        return array_intersect_key($data, array_flip([
            'source_ingredient_id',
            'target_ingredient_id',
            'equivalence_type',
            'conversion_factor',
            'reason',
            'status',
        ]));
    }

    private function assertValid(array $data)
    {
        if ((int) $data['source_ingredient_id'] === (int) $data['target_ingredient_id']) {
            throw new IngredientException('INGREDIENT_EQUIVALENCE_SAME_INGREDIENT', 'El ingrediente origen y destino no pueden ser iguales.', 422);
        }

        $this->assertActiveIngredient($data['source_ingredient_id']);
        $this->assertActiveIngredient($data['target_ingredient_id']);
    }

    private function assertActiveIngredient($id)
    {
        $ingredient = Ingredient::where('id', $id)->where('status', 'active')->first();

        if (! $ingredient) {
            throw new IngredientException('INGREDIENT_INVALID', 'El ingrediente indicado no existe o no está activo.', 422);
        }
    }

    private function rawPayload(IngredientEquivalence $equivalence)
    {
        return [
            'source_ingredient_id' => $equivalence->source_ingredient_id,
            'target_ingredient_id' => $equivalence->target_ingredient_id,
            'equivalence_type' => $equivalence->equivalence_type,
            'conversion_factor' => $equivalence->conversion_factor,
            'reason' => $equivalence->reason,
            'status' => $equivalence->status,
        ];
    }

    private function auditPayload(IngredientEquivalence $equivalence)
    {
        return $this->rawPayload($equivalence);
    }

    private function audit($actorId, $action, $entityId, $old, $new, $ip, $userAgent)
    {
        AuditLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'entity_name' => 'ingredient_equivalences',
            'entity_id' => (string) $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
