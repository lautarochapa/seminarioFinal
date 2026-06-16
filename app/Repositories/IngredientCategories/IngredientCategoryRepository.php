<?php

namespace App\Repositories\IngredientCategories;

use App\IngredientCategory;

class IngredientCategoryRepository
{
    public function paginate(array $filters)
    {
        $query = IngredientCategory::with('parent')->withCount(['children', 'ingredients']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('code', 'ILIKE', $search)
                    ->orWhere('name', 'ILIKE', $search)
                    ->orWhere('description', 'ILIKE', $search);
            });
        }

        $sortMap = [
            'id' => 'id',
            'code' => 'code',
            'name' => 'name',
            'status' => 'status',
            'sort_order' => 'sort_order',
            'created_at' => 'created_at',
        ];
        $sort = isset($sortMap[$filters['sort'] ?? null]) ? $sortMap[$filters['sort']] : 'sort_order';
        $order = (($filters['order'] ?? 'asc') === 'desc') ? 'desc' : 'asc';

        return $query->orderBy($sort, $order)
            ->orderBy('name')
            ->paginate(min((int) ($filters['per_page'] ?? 20), 100));
    }

    public function activeTree()
    {
        return IngredientCategory::with('activeChildren')
            ->whereNull('parent_id')
            ->where('status', 'active')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function findOrFail($id)
    {
        return IngredientCategory::with(['parent'])->findOrFail($id);
    }

    public function findWithTrashedOrFail($id)
    {
        return IngredientCategory::withTrashed()->with(['parent'])->findOrFail($id);
    }

    public function activeById($id)
    {
        return IngredientCategory::where('id', $id)
            ->where('status', 'active')
            ->where('is_active', true)
            ->first();
    }

    public function existsWithTrashed($id)
    {
        return IngredientCategory::withTrashed()->where('id', $id)->exists();
    }

    public function codeExists($code, $ignoreId = null)
    {
        $query = IngredientCategory::withTrashed()->where('code', $code);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    public function hasChildren($id)
    {
        return IngredientCategory::where('parent_id', $id)->exists();
    }
}
