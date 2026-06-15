<?php

namespace App\Repositories\Admin;

use App\Permission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PermissionRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Permission::query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['module'])) {
            $query->where('module', $filters['module']);
        }

        if (!empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->whereRaw('code ILIKE ?', ["%{$term}%"])
                  ->orWhereRaw('description ILIKE ?', ["%{$term}%"]);
            });
        }

        $allowed = ['code', 'module', 'action'];
        $sort    = in_array($filters['sort'] ?? null, $allowed) ? $filters['sort'] : 'module';
        $order   = ($filters['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $query->orderBy($sort, $order);

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);
        $perPage = max($perPage, 1);

        return $query->paginate($perPage);
    }
}
