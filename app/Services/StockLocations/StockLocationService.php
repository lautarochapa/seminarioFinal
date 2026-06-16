<?php

namespace App\Services\StockLocations;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Repositories\FamilyGroup\FamilyGroupMemberRepository;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\StockLocations\StockLocationRepository;
use App\StockLocation;
use Illuminate\Support\Facades\DB;

class StockLocationService
{
    private $groups;
    private $members;
    private $locations;

    public function __construct(
        FamilyGroupRepository $groups,
        FamilyGroupMemberRepository $members,
        StockLocationRepository $locations
    ) {
        $this->groups = $groups;
        $this->members = $members;
        $this->locations = $locations;
    }

    public function list(int $groupId, int $userId, array $filters)
    {
        $this->groups->findOrFailForUser($groupId, $userId);

        return $this->locations->paginateForGroup($groupId, $filters);
    }

    public function create(int $groupId, int $userId, array $data, string $ip, string $ua): StockLocation
    {
        $this->groups->findOrFailForUser($groupId, $userId);
        $this->requireAdminOrOwner($groupId, $userId);

        $data = $this->prepare($data);

        if ($this->locations->activeNameExists($groupId, $data['name'])) {
            throw new FamilyGroupException('STOCK_LOCATION_NAME_ALREADY_EXISTS', 'Ya existe una ubicacion con ese nombre.', 409);
        }

        return DB::transaction(function () use ($groupId, $userId, $data, $ip, $ua) {
            $location = $this->locations->create([
                'family_group_id' => $groupId,
                'name' => $data['name'],
                'type' => $data['type'] ?? null,
                'status' => $data['status'] ?? 'active',
            ]);

            $this->audit($userId, 'stock-location.created', $location->id, null, $this->payload($location), $ip, $ua);

            return $location;
        });
    }

    public function update(int $groupId, int $locationId, int $userId, array $data, string $ip, string $ua): StockLocation
    {
        $this->groups->findOrFailForUser($groupId, $userId);
        $this->requireAdminOrOwner($groupId, $userId);
        $location = $this->locations->findInGroupOrFail($groupId, $locationId);
        $data = $this->prepare($data, false);

        if (array_key_exists('name', $data) && $this->locations->activeNameExists($groupId, $data['name'], $location->id)) {
            throw new FamilyGroupException('STOCK_LOCATION_NAME_ALREADY_EXISTS', 'Ya existe una ubicacion con ese nombre.', 409);
        }

        return DB::transaction(function () use ($location, $userId, $data, $ip, $ua) {
            $old = $this->payload($location);
            $updated = $this->locations->update($location, $data);
            $new = $this->payload($updated);

            if ($old != $new) {
                $this->audit($userId, 'stock-location.updated', $updated->id, $old, $new, $ip, $ua);
            }

            return $updated;
        });
    }

    public function delete(int $groupId, int $locationId, int $userId, string $ip, string $ua): StockLocation
    {
        $this->groups->findOrFailForUser($groupId, $userId);
        $this->requireAdminOrOwner($groupId, $userId);
        $location = $this->locations->findInGroupOrFail($groupId, $locationId);

        return DB::transaction(function () use ($location, $userId, $ip, $ua) {
            $old = $this->payload($location);
            $location->status = 'inactive';
            $location->save();
            $deleted = $this->locations->delete($location);
            $new = $this->payload($deleted);

            $this->audit($userId, 'stock-location.deleted', $deleted->id, $old, $new, $ip, $ua);

            return $deleted;
        });
    }

    private function requireAdminOrOwner(int $groupId, int $userId): void
    {
        $membership = $this->members->findMembership($groupId, $userId);

        if (! $membership || ! in_array($membership->role_in_group, ['owner', 'admin'])) {
            throw new FamilyGroupException('FAMILY_GROUP_ACCESS_DENIED', 'Se requiere rol de propietario o administrador.', 403);
        }
    }

    private function prepare(array $data, bool $creating = true): array
    {
        foreach (['name', 'type', 'status'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $value = preg_replace('/\s+/', ' ', trim($data[$field]));
                $data[$field] = $value === '' ? null : $value;
            }
        }

        return array_filter(array_intersect_key($data, array_flip(['name', 'type', 'status'])), function ($value) {
            return $value !== null;
        });
    }

    private function payload(StockLocation $location): array
    {
        return [
            'family_group_id' => $location->family_group_id,
            'name' => $location->name,
            'type' => $location->type,
            'status' => $location->status,
            'deleted_at' => $location->deleted_at ? (string) $location->deleted_at : null,
        ];
    }

    private function audit($actorId, $action, $entityId, $old, $new, $ip, $ua): void
    {
        AuditLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'entity_name' => 'stock_locations',
            'entity_id' => (string) $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $ua,
        ]);
    }
}
