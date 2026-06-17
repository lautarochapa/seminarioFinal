<?php

namespace App\Repositories\SystemSettings;

use App\SystemSetting;

class SystemSettingRepository
{
    public function paginate(array $filters)
    {
        $query = SystemSetting::query();

        if (!empty($filters['key'])) {
            $query->where('key', 'ILIKE', '%'.$filters['key'].'%');
        }

        if (!empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('key', 'ILIKE', $search)
                    ->orWhere('description', 'ILIKE', $search);
            });
        }

        $sortMap = [
            'id' => 'id',
            'key' => 'key',
            'type' => 'type',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];

        $sort = $sortMap[$filters['sort'] ?? 'key'] ?? 'key';
        $order = strtolower($filters['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function findByKey($key)
    {
        return SystemSetting::where('key', $key)->first();
    }

    public function update(SystemSetting $setting, array $data)
    {
        $setting->fill($data);
        $setting->save();

        return $setting->fresh();
    }
}
