<?php

namespace App\Repositories\Admin;

use App\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = User::withTrashed();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->whereRaw('name ILIKE ?', ["%{$term}%"])
                  ->orWhereRaw('email ILIKE ?', ["%{$term}%"])
                  ->orWhereRaw('username ILIKE ?', ["%{$term}%"]);
            });
        }

        if (empty($filters['include_deleted'])) {
            $query->whereNull('deleted_at');
        }

        $allowed = ['name', 'email', 'created_at', 'last_login_at'];
        $sort    = in_array($filters['sort'] ?? null, $allowed) ? $filters['sort'] : 'created_at';
        $order   = ($filters['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sort, $order);

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);
        $perPage = max($perPage, 1);

        return $query->paginate($perPage);
    }

    public function findOrFail(int $id): User
    {
        return User::findOrFail($id);
    }

    public function findWithTrashedOrFail(int $id): User
    {
        return User::withTrashed()->findOrFail($id);
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);
        return $user->fresh();
    }
}
