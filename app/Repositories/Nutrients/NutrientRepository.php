<?php

namespace App\Repositories\Nutrients;

use App\Exceptions\Nutrients\NutrientException;
use App\Nutrient;

class NutrientRepository
{
    public function paginate(array $filters)
    {
        $query = Nutrient::with(['unit']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['unit_id'])) {
            $query->where('unit_id', (int) $filters['unit_id']);
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
                $q->where('code', 'ILIKE', $search)
                    ->orWhere('name', 'ILIKE', $search)
                    ->orWhere('description', 'ILIKE', $search);
            });
        }

        $sortMap = [
            'id'         => 'id',
            'code'       => 'code',
            'name'       => 'name',
            'status'     => 'status',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
        $sort    = $sortMap[$filters['sort'] ?? 'name'] ?? 'name';
        $order   = strtolower($filters['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderBy($sort, $order)->orderBy('id')->paginate($perPage);
    }

    public function findOrFail($id)
    {
        $nutrient = Nutrient::with(['unit'])->find($id);

        if (! $nutrient) {
            throw new NutrientException('NUTRIENT_NOT_FOUND', 'El nutriente solicitado no existe.', 404);
        }

        return $nutrient;
    }

    public function findActiveOrFail($id)
    {
        $nutrient = Nutrient::with(['unit'])->where('id', $id)->where('status', 'active')->first();

        if (! $nutrient) {
            throw new NutrientException('NUTRIENT_NOT_FOUND', 'El nutriente solicitado no existe o no está activo.', 404);
        }

        return $nutrient;
    }

    public function activeCodeExists($normalizedCode, $exceptId = null)
    {
        $query = Nutrient::where('code', $normalizedCode)->where('status', 'active');

        if ($exceptId) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->exists();
    }

    public function create(array $data)
    {
        return Nutrient::create($data)->fresh(['unit']);
    }

    public function update(Nutrient $nutrient, array $data)
    {
        $nutrient->fill($data);
        $nutrient->save();

        return $nutrient->fresh(['unit']);
    }
}
