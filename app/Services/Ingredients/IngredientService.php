<?php

namespace App\Services\Ingredients;

use App\AuditLog;
use App\Exceptions\Ingredients\IngredientException;
use App\Ingredient;
use App\IngredientCategory;
use App\Repositories\Ingredients\IngredientRepository;
use App\UnitMeasure;
use Illuminate\Support\Facades\DB;

class IngredientService
{
    private $ingredients;

    public function __construct(IngredientRepository $ingredients)
    {
        $this->ingredients = $ingredients;
    }

    public function list(array $filters)
    {
        return $this->ingredients->paginate($filters);
    }

    public function publicList(array $filters)
    {
        return $this->ingredients->paginate($filters, true);
    }

    public function show($id)
    {
        return $this->ingredients->findOrFail($id);
    }

    public function publicShow($id)
    {
        return $this->ingredients->findPublicOrFail($id);
    }

    public function create($actorId, array $data, $ip, $userAgent)
    {
        $data = $this->prepare($data, true);
        $this->assertRelations($data);

        if ($this->ingredients->activeNameExists($data['normalized_name'])) {
            throw new IngredientException('INGREDIENT_NAME_ALREADY_EXISTS', 'Ya existe un ingrediente activo con ese nombre.', 409);
        }

        return DB::transaction(function () use ($actorId, $data, $ip, $userAgent) {
            $ingredient = $this->ingredients->create($data);
            $this->audit($actorId, 'ingredient.created', $ingredient->id, null, $this->auditPayload($ingredient), $ip, $userAgent);

            return $ingredient;
        });
    }

    public function update($actorId, $id, array $data, $ip, $userAgent)
    {
        $ingredient = $this->ingredients->findOrFail($id);
        $data = $this->prepare($data, false, $ingredient);
        $this->assertRelations($data);

        if (array_key_exists('normalized_name', $data) && $this->ingredients->activeNameExists($data['normalized_name'], $ingredient->id)) {
            throw new IngredientException('INGREDIENT_NAME_ALREADY_EXISTS', 'Ya existe un ingrediente activo con ese nombre.', 409);
        }

        return DB::transaction(function () use ($actorId, $ingredient, $data, $ip, $userAgent) {
            $old = $this->auditPayload($ingredient);
            $updated = $this->ingredients->update($ingredient, $data);
            $new = $this->auditPayload($updated);

            if ($old != $new) {
                $this->audit($actorId, 'ingredient.updated', $updated->id, $old, $new, $ip, $userAgent);
            }

            return $updated;
        });
    }

    public function delete($actorId, $id, $ip, $userAgent)
    {
        $ingredient = $this->ingredients->findOrFail($id);

        return DB::transaction(function () use ($actorId, $ingredient, $ip, $userAgent) {
            $old = $this->auditPayload($ingredient);
            $ingredient->status = 'inactive';
            $ingredient->save();
            $ingredient->delete();
            $deleted = $this->ingredients->findWithTrashedOrFail($ingredient->id);
            $new = $this->auditPayload($deleted);

            $this->audit($actorId, 'ingredient.deleted', $deleted->id, $old, $new, $ip, $userAgent);

            return $deleted;
        });
    }

    public function restore($actorId, $id, $ip, $userAgent)
    {
        $ingredient = $this->ingredients->findWithTrashedOrFail($id);

        if (! $ingredient->trashed()) {
            throw new IngredientException('RESOURCE_NOT_DELETED', 'El recurso no estÃ¡ eliminado.', 409);
        }

        if ($this->ingredients->activeNameExists($ingredient->normalized_name, $ingredient->id)) {
            throw new IngredientException('INGREDIENT_NAME_ALREADY_EXISTS', 'Ya existe un ingrediente activo con ese nombre.', 409);
        }

        return DB::transaction(function () use ($actorId, $ingredient, $ip, $userAgent) {
            $old = $this->auditPayload($ingredient);
            $ingredient->restore();
            $ingredient->status = 'active';
            $ingredient->save();
            $restored = $ingredient->fresh(['category', 'baseUnit']);
            $new = $this->auditPayload($restored);

            $this->audit($actorId, 'ingredient.restored', $restored->id, $old, $new, $ip, $userAgent);

            return $restored;
        });
    }

    public function nutrition($id)
    {
        return $this->ingredients->nutrition($this->publicShow($id));
    }

    public function equivalences($id)
    {
        return $this->ingredients->equivalences($this->publicShow($id));
    }

    private function prepare(array $data, $creating, Ingredient $ingredient = null)
    {
        foreach (['name', 'description'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        if ($creating || array_key_exists('name', $data)) {
            $name = $data['name'] ?? ($ingredient ? $ingredient->name : null);
            $data['normalized_name'] = $this->normalizeName($name);
        }

        foreach (['is_generic', 'is_preparation', 'is_supplement'] as $flag) {
            if (array_key_exists($flag, $data)) {
                $data[$flag] = filter_var($data[$flag], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return array_intersect_key($data, array_flip([
            'name',
            'normalized_name',
            'category_id',
            'base_unit_id',
            'description',
            'is_generic',
            'is_preparation',
            'is_supplement',
            'status',
        ]));
    }

    private function normalizeName($name)
    {
        $name = strtolower(trim((string) $name));
        $name = preg_replace('/[^a-z0-9]+/', '_', $name);

        return trim($name, '_');
    }

    private function assertRelations(array $data)
    {
        if (array_key_exists('category_id', $data) && $data['category_id'] !== null) {
            $category = IngredientCategory::where('id', $data['category_id'])->where('status', 'active')->first();
            if (! $category) {
                throw new IngredientException('INGREDIENT_CATEGORY_NOT_FOUND', 'La categorÃ­a indicada no existe o no estÃ¡ activa.', 422);
            }
        }

        if (array_key_exists('base_unit_id', $data) && $data['base_unit_id'] !== null) {
            $unit = UnitMeasure::where('id', $data['base_unit_id'])->where('status', 'active')->first();
            if (! $unit) {
                throw new IngredientException('INGREDIENT_UNIT_NOT_FOUND', 'La unidad indicada no existe o no estÃ¡ activa.', 422);
            }
        }
    }

    private function auditPayload(Ingredient $ingredient)
    {
        return [
            'name' => $ingredient->name,
            'normalized_name' => $ingredient->normalized_name,
            'category_id' => $ingredient->category_id,
            'base_unit_id' => $ingredient->base_unit_id,
            'description' => $ingredient->description,
            'is_generic' => (bool) $ingredient->is_generic,
            'is_preparation' => (bool) $ingredient->is_preparation,
            'is_supplement' => (bool) $ingredient->is_supplement,
            'status' => $ingredient->status,
            'deleted_at' => $ingredient->deleted_at ? (string) $ingredient->deleted_at : null,
        ];
    }

    private function audit($actorId, $action, $entityId, $old, $new, $ip, $userAgent)
    {
        AuditLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'entity_name' => 'ingredients',
            'entity_id' => (string) $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
