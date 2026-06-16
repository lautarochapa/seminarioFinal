<?php

namespace App\Repositories\HealthPreferences;

use App\Exceptions\HealthPreferences\HealthPreferenceException;

class UserHealthPreferenceRepository
{
    public function listForUser(array $type, $userId)
    {
        $model = $type['relation_model'];

        return $model::with($type['relation_name'])
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get();
    }

    public function findForUserOrFail(array $type, $userId, $id)
    {
        $model = $type['relation_model'];
        $relation = $model::with($type['relation_name'])
            ->where('user_id', $userId)
            ->where('id', $id)
            ->first();

        if (! $relation) {
            throw new HealthPreferenceException('USER_HEALTH_PREFERENCE_NOT_FOUND', 'La relación solicitada no existe.', 404);
        }

        return $relation;
    }

    public function existsForUser(array $type, $userId, $catalogId)
    {
        $model = $type['relation_model'];

        return $model::where('user_id', $userId)
            ->where($type['relation_fk'], $catalogId)
            ->exists();
    }

    public function create(array $type, array $data)
    {
        $model = $type['relation_model'];

        return $model::create($data)->load($type['relation_name']);
    }

    public function delete($relation)
    {
        return $relation->delete();
    }
}
