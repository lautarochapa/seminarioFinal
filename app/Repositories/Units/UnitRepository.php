<?php

namespace App\Repositories\Units;

use App\Exceptions\Units\UnitException;
use App\UnitMeasure;

class UnitRepository
{
    public function paginate(array $filters, $publicOnly = false)
    {
        $query = UnitMeasure::query();

        if ($publicOnly) {
            $query->where('status', 'active');
        } elseif (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
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
                    ->orWhere('symbol', 'ILIKE', $search)
                    ->orWhere('type', 'ILIKE', $search);
            });
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
        $sort = $sortMap[$filters['sort'] ?? 'code'] ?? 'code';
        $order = strtolower($filters['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderBy($sort, $order)->orderBy('id')->paginate($perPage);
    }

    public function findOrFail($id)
    {
        $unit = UnitMeasure::find($id);

        if (! $unit) {
            throw new UnitException('UNIT_NOT_FOUND', 'La unidad solicitada no existe.', 404);
        }

        return $unit;
    }

    public function activeById($id)
    {
        return UnitMeasure::where('id', $id)->where('status', 'active')->first();
    }

    public function activeCodeExists($code, $exceptId = null)
    {
        $query = UnitMeasure::where('code', $code)->where('status', 'active');

        if ($exceptId) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->exists();
    }

    public function create(array $data)
    {
        return UnitMeasure::create($data);
    }

    public function update(UnitMeasure $unit, array $data)
    {
        $unit->fill($data);
        $unit->save();

        return $unit->fresh();
    }
}
