<?php

namespace App\Repositories\RecipeTags;

use App\RecipeTag;

class RecipeTagRepository
{
    public function paginate(array $filters)
    {
        $query = RecipeTag::query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', $search)
                    ->orWhere('code', 'ILIKE', $search)
                    ->orWhere('description', 'ILIKE', $search);
            });
        }

        $sortMap = [
            'id'         => 'id',
            'code'       => 'code',
            'name'       => 'name',
            'type'       => 'type',
            'status'     => 'status',
            'created_at' => 'created_at',
        ];
        $sort  = isset($sortMap[$filters['sort'] ?? null]) ? $sortMap[$filters['sort']] : 'name';
        $order = (($filters['order'] ?? 'asc') === 'desc') ? 'desc' : 'asc';

        return $query->orderBy($sort, $order)
            ->paginate(min((int) ($filters['per_page'] ?? 20), 100));
    }

    public function active()
    {
        return RecipeTag::where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    public function findOrFail($id)
    {
        return RecipeTag::findOrFail($id);
    }

    public function codeExists($code, $ignoreId = null)
    {
        $query = RecipeTag::where('code', $code);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
