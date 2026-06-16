<?php

namespace App\Repositories\HealthPreferences;

use App\Exceptions\HealthPreferences\HealthPreferenceException;

class HealthPreferenceRepository
{
    public function paginate(array $type, array $filters)
    {
        $model = $type['model'];
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        $query = $model::query();

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

        return $query->orderBy('id')->paginate($perPage);
    }

    public function activeCatalog(array $type)
    {
        $model = $type['model'];

        return $model::where('status', 'active')->orderBy('name')->get();
    }

    public function findOrFail(array $type, $id)
    {
        $model = $type['model'];
        $item = $model::find($id);

        if (! $item) {
            throw new HealthPreferenceException('HEALTH_PREFERENCE_NOT_FOUND', 'El recurso solicitado no existe.', 404);
        }

        return $item;
    }

    public function findWithTrashedOrFail(array $type, $id)
    {
        $model = $type['model'];
        $query = $type['uses_soft_delete'] ? $model::withTrashed() : $model::query();
        $item = $query->find($id);

        if (! $item) {
            throw new HealthPreferenceException('HEALTH_PREFERENCE_NOT_FOUND', 'El recurso solicitado no existe.', 404);
        }

        return $item;
    }

    public function findActiveOrFail(array $type, $id)
    {
        $model = $type['model'];
        $item = $model::where('id', $id)->where('status', 'active')->first();

        if (! $item) {
            throw new HealthPreferenceException('INVALID_HEALTH_PREFERENCE', 'El recurso no está disponible.', 422);
        }

        return $item;
    }

    public function codeExists(array $type, $code, $exceptId = null)
    {
        $model = $type['model'];
        $query = $type['uses_soft_delete'] ? $model::withTrashed()->where('code', $code) : $model::where('code', $code);

        if ($exceptId) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->exists();
    }
}
