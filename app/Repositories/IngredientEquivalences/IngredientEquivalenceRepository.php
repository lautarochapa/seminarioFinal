<?php

namespace App\Repositories\IngredientEquivalences;

use App\Exceptions\Ingredients\IngredientException;
use App\IngredientEquivalence;

class IngredientEquivalenceRepository
{
    public function paginate(array $filters)
    {
        $query = IngredientEquivalence::with(['sourceIngredient.category', 'sourceIngredient.baseUnit', 'targetIngredient.category', 'targetIngredient.baseUnit']);

        foreach (['status', 'equivalence_type'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        foreach (['source_ingredient_id', 'target_ingredient_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, (int) $filters[$field]);
            }
        }

        if (! empty($filters['created_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }

        if (! empty($filters['created_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('equivalence_type', 'ILIKE', $search)
                    ->orWhere('reason', 'ILIKE', $search);
            });
        }

        $sortMap = [
            'id' => 'id',
            'equivalence_type' => 'equivalence_type',
            'conversion_factor' => 'conversion_factor',
            'status' => 'status',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
        $sort = $sortMap[$filters['sort'] ?? 'id'] ?? 'id';
        $order = strtolower($filters['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderBy($sort, $order)->orderBy('id')->paginate($perPage);
    }

    public function findOrFail($id)
    {
        $equivalence = IngredientEquivalence::with(['sourceIngredient.category', 'sourceIngredient.baseUnit', 'targetIngredient.category', 'targetIngredient.baseUnit'])->find($id);

        if (! $equivalence) {
            throw new IngredientException('INGREDIENT_EQUIVALENCE_NOT_FOUND', 'La equivalencia solicitada no existe.', 404);
        }

        return $equivalence;
    }

    public function activeDuplicateExists($sourceId, $targetId, $type, $exceptId = null)
    {
        $query = IngredientEquivalence::where('source_ingredient_id', $sourceId)
            ->where('target_ingredient_id', $targetId)
            ->where('status', 'active');

        if ($type === null || $type === '') {
            $query->whereNull('equivalence_type');
        } else {
            $query->where('equivalence_type', $type);
        }

        if ($exceptId) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->exists();
    }

    public function create(array $data)
    {
        return IngredientEquivalence::create($data)->fresh(['sourceIngredient.category', 'sourceIngredient.baseUnit', 'targetIngredient.category', 'targetIngredient.baseUnit']);
    }

    public function update(IngredientEquivalence $equivalence, array $data)
    {
        $equivalence->fill($data);
        $equivalence->save();

        return $equivalence->fresh(['sourceIngredient.category', 'sourceIngredient.baseUnit', 'targetIngredient.category', 'targetIngredient.baseUnit']);
    }
}
