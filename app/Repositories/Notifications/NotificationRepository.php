<?php

namespace App\Repositories\Notifications;

use App\Exceptions\Notifications\NotificationException;
use App\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationRepository
{
    public function paginateForUser(int $userId, array $filters): LengthAwarePaginator
    {
        $query = Notification::where('user_id', $userId)
            ->orderByDesc('created_at');

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);
        $perPage = max($perPage, 1);

        return $query->paginate($perPage);
    }

    public function unreadCountForUser(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function findForUser(int $userId, int $notificationId): Notification
    {
        $notification = Notification::where('user_id', $userId)
            ->where('id', $notificationId)
            ->first();

        if (!$notification) {
            throw new NotificationException('NOTIFICATION_NOT_FOUND', 'La notificacion no existe.', 404);
        }

        return $notification;
    }

    public function markAsRead(Notification $notification): Notification
    {
        $notification->update(['read_at' => now(), 'status' => 'read']);
        return $notification->fresh();
    }

    public function markAllAsRead(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'status' => 'read']);
    }
}
