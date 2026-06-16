<?php

namespace App\Repositories\RecipeSearch;

use App\Recipe;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RecipeSearchRepository
{
    private const SORT_WHITELIST = [
        'name'              => 'name',
        'created_at'        => 'created_at',
        'difficulty'        => 'difficulty',
        'prep_time_minutes' => 'prep_time_minutes',
        'cook_time_minutes' => 'cook_time_minutes',
    ];

    public function search(array $filters, int $userId, bool $canManage): LengthAwarePaginator
    {
        $query = Recipe::with(['category:id,name', 'owner:id,name'])
            ->withCount(['tags', 'ingredients'])
            ->where('status', 'active')
            ->whereNull('deleted_at');

        // Visibility: public OR own OR admin
        if (!$canManage) {
            $query->where(function ($q) use ($userId) {
                $q->where('is_public', true)
                  ->orWhere('owner_user_id', $userId);
            });
        }

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ILIKE', $term)
                  ->orWhere('normalized_name', 'ILIKE', $term);
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

        if (!empty($filters['tag_code'])) {
            $query->whereHas('tags', function ($q) use ($filters) {
                $q->where('recipe_tags.code', $filters['tag_code']);
            });
        }

        if (!empty($filters['ingredient_id'])) {
            $query->whereHas('ingredients', function ($q) use ($filters) {
                $q->where('ingredient_id', (int) $filters['ingredient_id']);
            });
        }

        if (!empty($filters['exclude_ingredient_id'])) {
            $query->whereDoesntHave('ingredients', function ($q) use ($filters) {
                $q->where('ingredient_id', (int) $filters['exclude_ingredient_id']);
            });
        }

        if (!empty($filters['difficulty'])) {
            $query->where('difficulty', $filters['difficulty']);
        }

        if (!empty($filters['max_prep_time'])) {
            $query->where('prep_time_minutes', '<=', (int) $filters['max_prep_time']);
        }

        if (!empty($filters['max_cook_time'])) {
            $query->where('cook_time_minutes', '<=', (int) $filters['max_cook_time']);
        }

        if (!empty($filters['max_total_time'])) {
            $max = (int) $filters['max_total_time'];
            $query->whereRaw('(COALESCE(prep_time_minutes, 0) + COALESCE(cook_time_minutes, 0)) <= ?', [$max]);
        }

        if (!empty($filters['source_type'])) {
            $query->where('source_type', $filters['source_type']);
        }

        if (isset($filters['is_official']) && $filters['is_official'] !== '') {
            $query->where('is_official', (bool) $filters['is_official']);
        }

        $sort  = self::SORT_WHITELIST[$filters['sort'] ?? ''] ?? 'created_at';
        $order = (($filters['order'] ?? 'desc') === 'asc') ? 'asc' : 'desc';

        $query->orderBy($sort, $order);

        if ($sort !== 'name') {
            $query->orderBy('name', 'asc');
        }

        return $query->paginate(min((int) ($filters['per_page'] ?? 20), 100), ['*'], 'page', (int) ($filters['page'] ?? 1));
    }
}
