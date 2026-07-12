<?php

namespace App\Repositories\Admin;

use App\LoginLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LoginLogRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = LoginLog::with('user');

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (!empty($filters['email'])) {
            $query->whereRaw('email ILIKE ?', ["%{$filters['email']}%"]);
        }

        if (isset($filters['success']) && $filters['success'] !== null && $filters['success'] !== '') {
            $query->where('success', filter_var($filters['success'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['ip'])) {
            $query->whereRaw('ip_address ILIKE ?', ["%{$filters['ip']}%"]);
        }

        if (!empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->whereRaw('email ILIKE ?', ["%{$term}%"])
                  ->orWhereRaw('ip_address ILIKE ?', ["%{$term}%"])
                  ->orWhereRaw('user_agent ILIKE ?', ["%{$term}%"])
                  ->orWhereRaw('failure_reason ILIKE ?', ["%{$term}%"]);
            });
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $allowed = ['id', 'created_at', 'user_id', 'email', 'success'];
        $sort    = in_array($filters['sort'] ?? null, $allowed) ? $filters['sort'] : 'created_at';
        $order   = ($filters['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sort, $order);

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);
        $perPage = max($perPage, 1);

        return $query->paginate($perPage);
    }
}
