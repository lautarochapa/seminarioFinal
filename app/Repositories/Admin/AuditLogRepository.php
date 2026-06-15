<?php

namespace App\Repositories\Admin;

use App\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AuditLogRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = AuditLog::with('user');

        if (!empty($filters['entity_name'])) {
            $query->where('entity_name', $filters['entity_name']);
        }

        if (!empty($filters['entity_id'])) {
            $query->where('entity_id', (string) $filters['entity_id']);
        }

        if (!empty($filters['resource'])) {
            $query->where('entity_name', $filters['resource']);
        }

        if (!empty($filters['resource_id'])) {
            $query->where('entity_id', (string) $filters['resource_id']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->whereRaw('action ILIKE ?', ["%{$term}%"])
                  ->orWhereRaw('entity_name ILIKE ?', ["%{$term}%"])
                  ->orWhereRaw('ip_address ILIKE ?', ["%{$term}%"])
                  ->orWhereRaw('user_agent ILIKE ?', ["%{$term}%"]);
            });
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $sortMap = [
            'id'         => 'id',
            'created_at' => 'created_at',
            'action'     => 'action',
            'resource'   => 'entity_name',
            'user_id'    => 'user_id',
        ];
        $sortKey = $filters['sort'] ?? null;
        $sort    = $sortMap[$sortKey] ?? 'created_at';
        $order   = ($filters['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sort, $order);

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);
        $perPage = max($perPage, 1);

        return $query->paginate($perPage);
    }
}
