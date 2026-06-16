<?php

namespace App\Services\Supermarkets;

use App\AuditLog;
use App\Exceptions\Ingredients\IngredientException;
use App\Repositories\Supermarkets\SupermarketBranchRepository;
use App\SupermarketBranch;
use Illuminate\Support\Facades\DB;

class SupermarketBranchService
{
    private $branches;

    public function __construct(SupermarketBranchRepository $branches)
    {
        $this->branches = $branches;
    }

    public function list(array $filters)
    {
        return $this->branches->paginate($filters);
    }

    public function show(int $id)
    {
        return $this->branches->findOrFail($id);
    }

    public function create(int $actorId, array $data, string $ip, string $userAgent)
    {
        return DB::transaction(function () use ($actorId, $data, $ip, $userAgent) {
            $branch = $this->branches->create([
                'supermarket_chain_id' => $data['supermarket_chain_id'],
                'city_id'              => $data['city_id'],
                'name'                 => $data['name'],
                'address'              => $data['address'],
                'latitude'             => $data['latitude'] ?? null,
                'longitude'            => $data['longitude'] ?? null,
                'phone'                => $data['phone'] ?? null,
                'opening_hours'        => $data['opening_hours'] ?? null,
                'delivery_available'   => $data['delivery_available'] ?? false,
                'pickup_available'     => $data['pickup_available'] ?? false,
                'status'               => 'active',
            ]);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'branch.created',
                'entity_name' => 'supermarket_branches',
                'entity_id'   => (string) $branch->id,
                'old_values'  => null,
                'new_values'  => $this->auditPayload($branch),
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $branch;
        });
    }

    public function update(int $actorId, int $id, array $data, string $ip, string $userAgent)
    {
        $branch = $this->branches->findOrFail($id);

        return DB::transaction(function () use ($actorId, $branch, $data, $ip, $userAgent) {
            $old      = $this->auditPayload($branch);
            $fillable = array_intersect_key($data, array_flip([
                'supermarket_chain_id', 'city_id', 'name', 'address',
                'latitude', 'longitude', 'phone', 'opening_hours',
                'delivery_available', 'pickup_available',
            ]));
            $updated = $this->branches->update($branch, $fillable);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'branch.updated',
                'entity_name' => 'supermarket_branches',
                'entity_id'   => (string) $branch->id,
                'old_values'  => $old,
                'new_values'  => $this->auditPayload($updated),
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $updated;
        });
    }

    public function delete(int $actorId, int $id, string $ip, string $userAgent)
    {
        $branch = $this->branches->findOrFail($id);

        return DB::transaction(function () use ($actorId, $branch, $ip, $userAgent) {
            $old     = $this->auditPayload($branch);
            $deleted = $this->branches->softDelete($branch);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'branch.deleted',
                'entity_name' => 'supermarket_branches',
                'entity_id'   => (string) $deleted->id,
                'old_values'  => $old,
                'new_values'  => $this->auditPayload($deleted),
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $deleted;
        });
    }

    public function restore(int $actorId, int $id, string $ip, string $userAgent)
    {
        $branch = $this->branches->findWithTrashedOrFail($id);

        if (! $branch->trashed()) {
            throw new IngredientException('RESOURCE_NOT_DELETED', 'La sucursal no esta eliminada.', 409);
        }

        return DB::transaction(function () use ($actorId, $branch, $ip, $userAgent) {
            $old      = $this->auditPayload($branch);
            $restored = $this->branches->restore($branch);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'branch.restored',
                'entity_name' => 'supermarket_branches',
                'entity_id'   => (string) $restored->id,
                'old_values'  => $old,
                'new_values'  => $this->auditPayload($restored),
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $restored;
        });
    }

    public function catalog(array $filters = [])
    {
        return $this->branches->allActive($filters);
    }

    public function catalogShow(int $id)
    {
        return $this->branches->findActiveOrFail($id);
    }

    public function nearby(float $lat, float $lng, float $radius)
    {
        $branches = $this->branches->allActiveWithCoordinates();

        return $branches
            ->map(function ($branch) use ($lat, $lng) {
                $branch->distance_km = $this->haversine(
                    $lat, $lng,
                    (float) $branch->latitude,
                    (float) $branch->longitude
                );
                return $branch;
            })
            ->filter(function ($branch) use ($radius) {
                return $branch->distance_km <= $radius;
            })
            ->sortBy('distance_km')
            ->values();
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadius * $c, 4);
    }

    private function auditPayload(SupermarketBranch $branch): array
    {
        return [
            'name'                 => $branch->name,
            'supermarket_chain_id' => $branch->supermarket_chain_id,
            'city_id'              => $branch->city_id,
            'address'              => $branch->address,
            'status'               => $branch->status,
            'deleted_at'           => $branch->deleted_at ? (string) $branch->deleted_at : null,
        ];
    }
}
