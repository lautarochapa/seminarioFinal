<?php

namespace App\Repositories\FoodTags;

use App\Exceptions\Ingredients\IngredientException;
use App\FoodTag;

class FoodTagRepository
{
    public function paginate(array $filters, $publicOnly = false)
    {
        $query = FoodTag::query();

        if ($publicOnly) {
            $query->where('status', 'active');
        } elseif (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('code', 'ILIKE', $search)
                    ->orWhere('name', 'ILIKE', $search)
                    ->orWhere('description', 'ILIKE', $search)
                    ->orWhere('type', 'ILIKE', $search);
            });
        }

        if (! empty($filters['created_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }

        if (! empty($filters['created_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        $sortMap = [
            'id' => 'id',
            'code' => 'code',
            'name' => 'name',
            'type' => 'type',
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
        $tag = FoodTag::find($id);

        if (! $tag) {
            throw new IngredientException('FOOD_TAG_NOT_FOUND', 'El tag alimentario solicitado no existe.', 404);
        }

        return $tag;
    }

    public function findWithTrashedOrFail($id)
    {
        $tag = FoodTag::withTrashed()->find($id);

        if (! $tag) {
            throw new IngredientException('FOOD_TAG_NOT_FOUND', 'El tag alimentario solicitado no existe.', 404);
        }

        return $tag;
    }

    public function activeCodeExists($code, $exceptId = null)
    {
        $query = FoodTag::where('code', $code)
            ->where('status', 'active');

        if ($exceptId) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->exists();
    }

    public function create(array $data)
    {
        return FoodTag::create($data);
    }

    public function update(FoodTag $tag, array $data)
    {
        $tag->fill($data);
        $tag->save();

        return $tag->fresh();
    }
}
