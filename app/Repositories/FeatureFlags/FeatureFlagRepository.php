<?php

namespace App\Repositories\FeatureFlags;

use App\FeatureFlag;

class FeatureFlagRepository
{
    public function all(array $filters = [])
    {
        $query = FeatureFlag::query();

        if (!empty($filters['key'])) {
            $query->where('key', 'ILIKE', '%'.$filters['key'].'%');
        }

        if (!empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('key', 'ILIKE', $search)
                    ->orWhere('name', 'ILIKE', $search)
                    ->orWhere('description', 'ILIKE', $search);
            });
        }

        return $query->orderBy('key')->get();
    }

    public function findByKey($key)
    {
        return FeatureFlag::where('key', $key)->first();
    }

    public function update(FeatureFlag $flag, array $data)
    {
        $flag->fill($data);
        $flag->save();

        return $flag->fresh();
    }
}
