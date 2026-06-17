<?php

namespace App\Services\Notifications;

use App\AuditLog;
use App\Exceptions\Notifications\NotificationException;
use App\Repositories\Notifications\NotificationPreferenceRepository;
use App\Repositories\Notifications\NotificationRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    const VALID_TYPES = [
        'vencimientos',
        'bajo_stock',
        'menu',
        'presupuesto',
        'scraping',
        'compras',
    ];

    const VALID_STATUSES  = ['pending', 'sent', 'read'];
    const VALID_CHANNELS  = ['app', 'email', 'push'];
    const VALID_FREQUENCIES = ['immediate', 'daily', 'weekly'];

    private $repo;
    private $prefRepo;

    public function __construct(NotificationRepository $repo, NotificationPreferenceRepository $prefRepo)
    {
        $this->repo     = $repo;
        $this->prefRepo = $prefRepo;
    }

    public function list(int $userId, array $filters): LengthAwarePaginator
    {
        $this->validateListFilters($filters);
        return $this->repo->paginateForUser($userId, $filters);
    }

    public function unreadCount(int $userId): int
    {
        return $this->repo->unreadCountForUser($userId);
    }

    public function markAsRead(int $userId, int $notificationId, string $ip, string $ua): array
    {
        $notification = $this->repo->findForUser($userId, $notificationId);

        if ($notification->read_at !== null) {
            throw new NotificationException('NOTIFICATION_ALREADY_READ', 'La notificacion ya fue leida.', 409);
        }

        return DB::transaction(function () use ($notification, $userId, $ip, $ua) {
            $updated = $this->repo->markAsRead($notification);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'notification.read',
                'entity_name' => 'notifications',
                'entity_id'   => (string) $updated->id,
                'old_values'  => ['status' => $notification->status, 'read_at' => null],
                'new_values'  => ['status' => 'read', 'read_at' => (string) $updated->read_at],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $this->format($updated);
        });
    }

    public function markAllAsRead(int $userId, string $ip, string $ua): int
    {
        return DB::transaction(function () use ($userId, $ip, $ua) {
            $count = $this->repo->markAllAsRead($userId);

            if ($count > 0) {
                AuditLog::create([
                    'user_id'     => $userId,
                    'action'      => 'notification.read_all',
                    'entity_name' => 'notifications',
                    'entity_id'   => null,
                    'old_values'  => null,
                    'new_values'  => ['marked_count' => $count],
                    'ip_address'  => $ip,
                    'user_agent'  => $ua,
                ]);
            }

            return $count;
        });
    }

    public function getPreferences(int $userId): array
    {
        $existing = $this->prefRepo->getForUser($userId)->keyBy('notification_type');

        return array_map(function ($type) use ($existing) {
            if ($existing->has($type)) {
                $pref = $existing->get($type);
                return [
                    'notification_type' => $type,
                    'app_enabled'       => $pref->app_enabled,
                    'email_enabled'     => $pref->email_enabled,
                    'push_enabled'      => $pref->push_enabled,
                    'frequency'         => $pref->frequency,
                ];
            }
            return [
                'notification_type' => $type,
                'app_enabled'       => true,
                'email_enabled'     => false,
                'push_enabled'      => false,
                'frequency'         => 'immediate',
            ];
        }, self::VALID_TYPES);
    }

    public function updatePreferences(int $userId, array $updates, string $ip, string $ua): array
    {
        return DB::transaction(function () use ($userId, $updates, $ip, $ua) {
            foreach ($updates as $item) {
                $type    = $item['notification_type'];
                $allowed = ['app_enabled', 'email_enabled', 'push_enabled', 'frequency'];
                $data    = array_intersect_key($item, array_flip($allowed));

                if (!empty($data)) {
                    $this->prefRepo->upsert($userId, $type, $data);
                }
            }

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'notification_preference.update',
                'entity_name' => 'notification_preferences',
                'entity_id'   => null,
                'old_values'  => null,
                'new_values'  => ['updated_types' => array_column($updates, 'notification_type')],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $this->getPreferences($userId);
        });
    }

    private function validateListFilters(array $filters): void
    {
        if (!empty($filters['type']) && !in_array($filters['type'], self::VALID_TYPES)) {
            throw new NotificationException('NOTIFICATION_TYPE_INVALID', 'Tipo de notificacion no valido.', 422);
        }

        if (!empty($filters['status']) && !in_array($filters['status'], self::VALID_STATUSES)) {
            throw new NotificationException('NOTIFICATION_STATUS_INVALID', 'Estado de notificacion no valido.', 422);
        }

        if (!empty($filters['channel']) && !in_array($filters['channel'], self::VALID_CHANNELS)) {
            throw new NotificationException('NOTIFICATION_CHANNEL_INVALID', 'Canal de notificacion no valido.', 422);
        }
    }

    private function format($n): array
    {
        return [
            'id'              => $n->id,
            'type'            => $n->type,
            'title'           => $n->title,
            'message'         => $n->message,
            'channel'         => $n->channel,
            'status'          => $n->status,
            'read_at'         => $n->read_at,
            'sent_at'         => $n->sent_at,
            'created_at'      => $n->created_at,
            'family_group_id' => $n->family_group_id,
        ];
    }
}
