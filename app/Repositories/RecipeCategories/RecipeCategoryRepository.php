<?php

namespace App\Repositories\RecipeCategories;

use App\RecipeCategory;

class RecipeCategoryRepository
{
    public function paginate(array $filters)
    {
        $query = RecipeCategory::with('parent')->withCount(['children', 'recipes']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', $search)
                    ->orWhere('description', 'ILIKE', $search);
            });
        }

        $sortMap = [
            'id'         => 'id',
            'name'       => 'name',
            'status'     => 'status',
            'created_at' => 'created_at',
        ];
        $sort  = isset($sortMap[$filters['sort'] ?? null]) ? $sortMap[$filters['sort']] : 'name';
        $order = (($filters['order'] ?? 'asc') === 'desc') ? 'desc' : 'asc';

        return $query->orderBy($sort, $order)
            ->paginate(min((int) ($filters['per_page'] ?? 20), 100));
    }

    public function activeTree()
    {
        return RecipeCategory::with('activeChildren')
            ->whereNull('parent_id')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    public function findOrFail($id)
    {
        return RecipeCategory::with(['parent'])->findOrFail($id);
    }

    public function findWithTrashedOrFail($id)
    {
        return RecipeCategory::withTrashed()->with(['parent'])->findOrFail($id);
    }

    public function activeById($id)
    {
        return RecipeCategory::where('id', $id)
            ->where('status', 'active')
            ->first();
    }

    public function existsWithTrashed($id)
    {
        return RecipeCategory::withTrashed()->where('id', $id)->exists();
    }

    public function nameExists($name, $ignoreId = null)
    {
        $query = RecipeCategory::withTrashed()->where('name', $name);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    public function hasChildren($id)
    {
        return RecipeCategory::where('parent_id', $id)->exists();
    }
}
