<?php

namespace App\Services\Nutrients;

use App\AuditLog;
use App\Exceptions\Nutrients\NutrientException;
use App\IngredientNutrient;
use App\Nutrient;
use App\Repositories\Nutrients\IngredientNutrientRepository;
use Illuminate\Support\Facades\DB;

class IngredientNutrientService
{
    private $repo;

    public function __construct(IngredientNutrientRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list($ingredientId)
    {
        $this->repo->findIngredientOrFail($ingredientId);

        return $this->repo->listForIngredient($ingredientId);
    }

    public function attach($actorId, $ingredientId, array $data, $ip, $userAgent)
    {
        $ingredient = $this->repo->findIngredientOrFail($ingredientId);

        $nutrient = Nutrient::where('id', $data['nutrient_id'])->where('status', 'active')->first();
        if (! $nutrient) {
            throw new NutrientException('NUTRIENT_NOT_FOUND', 'El nutriente indicado no existe o no está activo.', 422);
        }

        if ($this->repo->activeRelationExists($ingredientId, $data['nutrient_id'])) {
            throw new NutrientException('NUTRIENT_RELATION_DUPLICATE', 'Ya existe una relación activa entre este ingrediente y nutriente.', 409);
        }

        $payload = [
            'ingredient_id'   => (int) $ingredientId,
            'nutrient_id'     => (int) $data['nutrient_id'],
            'amount_per_100g' => $data['amount_per_100g'],
            'source'          => $data['source'] ?? null,
            'status'          => $data['status'] ?? 'active',
        ];

        return DB::transaction(function () use ($actorId, $payload, $ip, $userAgent) {
            $relation = $this->repo->create($payload);
            $this->audit($actorId, 'ingredient_nutrient.created', $relation->id, null, $this->auditPayload($relation), $ip, $userAgent);

            return $relation;
        });
    }

    public function update($actorId, $ingredientId, $nutrientId, array $data, $ip, $userAgent)
    {
        $this->repo->findIngredientOrFail($ingredientId);

        $relation = $this->repo->findRelationOrFail($ingredientId, $nutrientId);

        $payload = array_intersect_key($data, array_flip(['amount_per_100g', 'source', 'status']));

        return DB::transaction(function () use ($actorId, $relation, $payload, $ip, $userAgent) {
            $old     = $this->auditPayload($relation);
            $updated = $this->repo->update($relation, $payload);
            $new     = $this->auditPayload($updated);

            if ($old != $new) {
                $this->audit($actorId, 'ingredient_nutrient.updated', $updated->id, $old, $new, $ip, $userAgent);
            }

            return $updated;
        });
    }

    private function auditPayload(IngredientNutrient $relation)
    {
        return [
            'ingredient_id'   => $relation->ingredient_id,
            'nutrient_id'     => $relation->nutrient_id,
            'amount_per_100g' => $relation->amount_per_100g,
            'source'          => $relation->source,
            'status'          => $relation->status,
        ];
    }

    private function audit($actorId, $action, $entityId, $old, $new, $ip, $userAgent)
    {
        AuditLog::create([
            'user_id'     => $actorId,
            'action'      => $action,
            'entity_name' => 'ingredient_nutrients',
            'entity_id'   => (string) $entityId,
            'old_values'  => $old,
            'new_values'  => $new,
            'ip_address'  => $ip,
            'user_agent'  => $userAgent,
        ]);
    }
}
