<?php

namespace App\Repositories\Admin;

use App\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RoleRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Role::with('permissions');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->whereRaw('code ILIKE ?', ["%{$term}%"])
                  ->orWhereRaw('name ILIKE ?', ["%{$term}%"]);
            });
        }

        $allowed = ['code', 'name'];
        $sort    = in_array($filters['sort'] ?? null, $allowed) ? $filters['sort'] : 'name';
        $order   = ($filters['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $query->orderBy($sort, $order);

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);
        $perPage = max($perPage, 1);

        return $query->paginate($perPage);
    }

    public function findOrFail(int $id): Role
    {
        return Role::with('permissions')->findOrFail($id);
    }

    public function create(array $data): Role
    {
        return Role::create($data);
    }

    public function update(Role $role, array $data): Role
    {
        $role->update($data);
        return $role->fresh('permissions');
    }
}
