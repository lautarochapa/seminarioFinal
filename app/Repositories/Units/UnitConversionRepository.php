<?php

namespace App\Repositories\Units;

use App\Exceptions\Units\UnitException;
use App\UnitConversion;

class UnitConversionRepository
{
    public function paginate(array $filters)
    {
        $query = UnitConversion::with(['fromUnit', 'toUnit', 'ingredient']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        foreach (['from_unit_id', 'to_unit_id', 'ingredient_id'] as $field) {
            if (array_key_exists($field, $filters) && $filters[$field] !== '' && $filters[$field] !== null) {
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
            $search = '%' . $filters['search'] . '%';
            $query->where('notes', 'ILIKE', $search);
        }

        $sortMap = [
            'id' => 'id',
            'factor' => 'factor',
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
        $conversion = UnitConversion::with(['fromUnit', 'toUnit', 'ingredient'])->find($id);

        if (! $conversion) {
            throw new UnitException('UNIT_CONVERSION_NOT_FOUND', 'La conversiÃ³n solicitada no existe.', 404);
        }

        return $conversion;
    }

    public function activeDuplicateExists($fromUnitId, $toUnitId, $ingredientId = null, $exceptId = null)
    {
        $query = UnitConversion::where('from_unit_id', $fromUnitId)
            ->where('to_unit_id', $toUnitId)
            ->where('status', 'active');

        is_null($ingredientId)
            ? $query->whereNull('ingredient_id')
            : $query->where('ingredient_id', $ingredientId);

        if ($exceptId) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->exists();
    }

    public function create(array $data)
    {
        return UnitConversion::create($data)->fresh(['fromUnit', 'toUnit', 'ingredient']);
    }

    public function update(UnitConversion $conversion, array $data)
    {
        $conversion->fill($data);
        $conversion->save();

        return $conversion->fresh(['fromUnit', 'toUnit', 'ingredient']);
    }
}
