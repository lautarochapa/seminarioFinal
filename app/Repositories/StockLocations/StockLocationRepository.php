<?php

namespace App\Repositories\StockLocations;

use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\StockLocation;

class StockLocationRepository
{
    public function paginateForGroup(int $groupId, array $filters)
    {
        $query = StockLocation::where('family_group_id', $groupId)
            ->whereNull('deleted_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderBy('name')->paginate($perPage);
    }

    public function findInGroupOrFail(int $groupId, int $locationId): StockLocation
    {
        $location = StockLocation::where('family_group_id', $groupId)
            ->where('id', $locationId)
            ->first();

        if (! $location) {
            throw new FamilyGroupException('STOCK_LOCATION_NOT_FOUND', 'Ubicacion de stock no encontrada.', 404);
        }

        return $location;
    }

    public function activeNameExists(int $groupId, string $name, ?int $exceptId = null): bool
    {
        $query = StockLocation::where('family_group_id', $groupId)
            ->where('name', $name)
            ->whereNull('deleted_at');

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function create(array $data): StockLocation
    {
        return StockLocation::create($data);
    }

    public function update(StockLocation $location, array $data): StockLocation
    {
        $location->fill($data);
        $location->save();

        return $location->fresh();
    }

    public function delete(StockLocation $location): StockLocation
    {
        $location->delete();

        return StockLocation::withTrashed()->find($location->id);
    }
}
