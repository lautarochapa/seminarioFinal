<?php

namespace App\Repositories\Ingredients;

use App\Exceptions\Ingredients\IngredientException;
use App\Ingredient;

class IngredientRepository
{
    public function paginate(array $filters, $publicOnly = false)
    {
        $query = Ingredient::with(['category', 'baseUnit']);

        if ($publicOnly) {
            $query->where('status', 'active');
        }

        if (! empty($filters['status']) && ! $publicOnly) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (! empty($filters['base_unit_id'])) {
            $query->where('base_unit_id', (int) $filters['base_unit_id']);
        }

        foreach (['is_generic', 'is_preparation', 'is_supplement'] as $flag) {
            if (array_key_exists($flag, $filters) && $filters[$flag] !== null && $filters[$flag] !== '') {
                $query->where($flag, filter_var($filters[$flag], FILTER_VALIDATE_BOOLEAN));
            }
        }

        if (! empty($filters['created_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }

        if (! empty($filters['created_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', $search)
                    ->orWhere('normalized_name', 'ILIKE', $search)
                    ->orWhere('description', 'ILIKE', $search);
            });
        }

        $sortMap = [
            'id' => 'id',
            'name' => 'name',
            'normalized_name' => 'normalized_name',
            'status' => 'status',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
        $sort = $sortMap[$filters['sort'] ?? 'name'] ?? 'name';
        $order = strtolower($filters['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderBy($sort, $order)->orderBy('id')->paginate($perPage);
    }

    public function findOrFail($id)
    {
        $ingredient = Ingredient::with(['category', 'baseUnit'])->find($id);

        if (! $ingredient) {
            throw new IngredientException('INGREDIENT_NOT_FOUND', 'El ingrediente solicitado no existe.', 404);
        }

        return $ingredient;
    }

    public function findWithTrashedOrFail($id)
    {
        $ingredient = Ingredient::withTrashed()->with(['category', 'baseUnit'])->find($id);

        if (! $ingredient) {
            throw new IngredientException('INGREDIENT_NOT_FOUND', 'El ingrediente solicitado no existe.', 404);
        }

        return $ingredient;
    }

    public function findPublicOrFail($id)
    {
        $ingredient = Ingredient::with(['category', 'baseUnit'])
            ->where('id', $id)
            ->where('status', 'active')
            ->first();

        if (! $ingredient) {
            throw new IngredientException('INGREDIENT_NOT_FOUND', 'El ingrediente solicitado no existe.', 404);
        }

        return $ingredient;
    }

    public function activeNameExists($normalizedName, $exceptId = null)
    {
        $query = Ingredient::where('normalized_name', $normalizedName)
            ->where('status', 'active');

        if ($exceptId) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->exists();
    }

    public function create(array $data)
    {
        return Ingredient::create($data)->fresh(['category', 'baseUnit']);
    }

    public function update(Ingredient $ingredient, array $data)
    {
        $ingredient->fill($data);
        $ingredient->save();

        return $ingredient->fresh(['category', 'baseUnit']);
    }

    public function nutrition(Ingredient $ingredient)
    {
        return $ingredient->nutrientValues()
            ->with(['nutrient.unit'])
            ->where('status', 'active')
            ->orderBy('id')
            ->get();
    }

    public function equivalences(Ingredient $ingredient)
    {
        return $ingredient->equivalencesFrom()
            ->with(['targetIngredient.category', 'targetIngredient.baseUnit'])
            ->whereHas('targetIngredient', function ($query) {
                $query->where('status', 'active');
            })
            ->where('status', 'active')
            ->orderBy('id')
            ->get();
    }
}
