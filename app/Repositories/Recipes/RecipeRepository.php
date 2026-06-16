<?php

namespace App\Repositories\Recipes;

use App\Recipe;

class RecipeRepository
{
    public function paginate(array $filters)
    {
        $query = Recipe::with(['category', 'owner'])
            ->withCount(['tags', 'ingredients'])
            ->where('status', 'active');

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', $search)
                    ->orWhere('normalized_name', 'ILIKE', $search);
            });
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (!empty($filters['tag_id'])) {
            $query->whereHas('tags', function ($q) use ($filters) {
                $q->where('recipe_tags.id', (int) $filters['tag_id']);
            });
        }

        if (!empty($filters['source_type'])) {
            $query->where('source_type', $filters['source_type']);
        }

        if (!empty($filters['owner_user_id'])) {
            $query->where('owner_user_id', (int) $filters['owner_user_id']);
        }

        $sortMap = [
            'id'         => 'id',
            'name'       => 'name',
            'created_at' => 'created_at',
            'difficulty' => 'difficulty',
        ];
        $sort  = isset($sortMap[$filters['sort'] ?? null]) ? $sortMap[$filters['sort']] : 'created_at';
        $order = (($filters['order'] ?? 'desc') === 'asc') ? 'asc' : 'desc';

        return $query->orderBy($sort, $order)
            ->paginate(min((int) ($filters['per_page'] ?? 20), 100));
    }

    public function findOrFail($id)
    {
        return Recipe::with([
            'category',
            'tags',
            'ingredients.ingredient',
            'ingredients.unit',
            'steps',
            'images',
            'owner',
            'sources',
        ])->findOrFail($id);
    }

    public function adminPaginate(array $filters)
    {
        $query = Recipe::with(['category', 'owner'])
            ->withCount(['tags', 'ingredients'])
            ->withTrashed();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', $search)
                    ->orWhere('normalized_name', 'ILIKE', $search);
            });
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (!empty($filters['tag_id'])) {
            $query->whereHas('tags', function ($q) use ($filters) {
                $q->where('recipe_tags.id', (int) $filters['tag_id']);
            });
        }

        if (!empty($filters['source_type'])) {
            $query->where('source_type', $filters['source_type']);
        }

        if (array_key_exists('is_official', $filters) && $filters['is_official'] !== '') {
            $query->where('is_official', (bool) $filters['is_official']);
        }

        if (!empty($filters['owner_user_id'])) {
            $query->where('owner_user_id', (int) $filters['owner_user_id']);
        }

        $sortMap = [
            'id'         => 'id',
            'name'       => 'name',
            'created_at' => 'created_at',
            'difficulty' => 'difficulty',
            'status'     => 'status',
        ];
        $sort  = isset($sortMap[$filters['sort'] ?? null]) ? $sortMap[$filters['sort']] : 'created_at';
        $order = (($filters['order'] ?? 'desc') === 'asc') ? 'asc' : 'desc';

        return $query->orderBy($sort, $order)
            ->paginate(min((int) ($filters['per_page'] ?? 20), 100));
    }

    public function findWithTrashedOrFail($id)
    {
        return Recipe::withTrashed()->with([
            'category',
            'tags',
            'ingredients.ingredient',
            'ingredients.unit',
            'steps',
            'images',
            'owner',
            'sources',
        ])->findOrFail($id);
    }
}
