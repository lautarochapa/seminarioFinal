<?php

namespace App\Repositories\Objectives;

use App\Exceptions\Objectives\ObjectivesException;
use App\Objective;

class ObjectiveRepository
{
    public function paginate(array $filters)
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        $query = Objective::query();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
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
            'id' => 'id',
            'code' => 'code',
            'name' => 'name',
            'status' => 'status',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
        $sort = $sortMap[$filters['sort'] ?? 'id'] ?? 'id';
        $order = strtolower($filters['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function activeCatalog()
    {
        return Objective::where('status', 'active')->orderBy('name')->get();
    }

    public function findOrFail($id)
    {
        $objective = Objective::find($id);

        if (! $objective) {
            throw new ObjectivesException('OBJECTIVE_NOT_FOUND', 'El objetivo solicitado no existe.', 404);
        }

        return $objective;
    }

    public function findWithTrashedOrFail($id)
    {
        $objective = Objective::withTrashed()->find($id);

        if (! $objective) {
            throw new ObjectivesException('OBJECTIVE_NOT_FOUND', 'El objetivo solicitado no existe.', 404);
        }

        return $objective;
    }

    public function findActiveOrFail($id)
    {
        $objective = Objective::where('id', $id)->where('status', 'active')->first();

        if (! $objective) {
            throw new ObjectivesException('INVALID_USER_OBJECTIVE', 'El objetivo no está disponible.', 422);
        }

        return $objective;
    }

    public function existsByCode($code, $exceptId = null)
    {
        $query = Objective::withTrashed()->where('code', $code);

        if ($exceptId) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->exists();
    }

    public function create(array $data)
    {
        return Objective::create($data);
    }

    public function update(Objective $objective, array $data)
    {
        $objective->fill($data);
        $objective->save();

        return $objective->fresh();
    }
}
