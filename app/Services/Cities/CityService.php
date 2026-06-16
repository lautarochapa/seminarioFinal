<?php

namespace App\Services\Cities;

use App\AuditLog;
use App\Exceptions\Ingredients\IngredientException;
use App\Repositories\Cities\CityRepository;
use Illuminate\Support\Facades\DB;

class CityService
{
    private $cities;

    public function __construct(CityRepository $cities)
    {
        $this->cities = $cities;
    }

    public function list(array $filters)
    {
        return $this->cities->paginate($filters);
    }

    public function create(int $actorId, array $data, string $ip, string $userAgent)
    {
        $name     = $data['name'];
        $province = $data['province'] ?? '';
        $country  = $data['country'] ?? 'Argentina';

        if ($this->cities->existsDuplicate($name, $province, $country)) {
            throw new IngredientException('CITY_NAME_ALREADY_EXISTS', 'Ya existe una ciudad con ese nombre, provincia y país.', 409);
        }

        return DB::transaction(function () use ($actorId, $data, $name, $province, $country, $ip, $userAgent) {
            $city = $this->cities->create([
                'name'      => $name,
                'province'  => $province,
                'country'   => $country,
                'latitude'  => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'status'    => 'active',
            ]);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'city.created',
                'entity_name' => 'cities',
                'entity_id'   => (string) $city->id,
                'old_values'  => null,
                'new_values'  => [
                    'name'    => $city->name,
                    'province'=> $city->province,
                    'country' => $city->country,
                    'status'  => $city->status,
                ],
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $city;
        });
    }

    public function update(int $actorId, int $cityId, array $data, string $ip, string $userAgent)
    {
        $city = $this->cities->findOrFail($cityId);

        $name     = $data['name']     ?? $city->name;
        $province = $data['province'] ?? $city->province;
        $country  = $data['country']  ?? $city->country;

        if (($name !== $city->name || $province !== $city->province || $country !== $city->country)
            && $this->cities->existsDuplicate($name, $province, $country, $city->id)
        ) {
            throw new IngredientException('CITY_NAME_ALREADY_EXISTS', 'Ya existe una ciudad con ese nombre, provincia y país.', 409);
        }

        return DB::transaction(function () use ($actorId, $city, $data, $name, $province, $country, $ip, $userAgent) {
            $old = $this->auditPayload($city);

            $fillable = [];
            if (isset($data['name']))      $fillable['name']      = $name;
            if (isset($data['province']))  $fillable['province']  = $province;
            if (isset($data['country']))   $fillable['country']   = $country;
            if (array_key_exists('latitude',  $data)) $fillable['latitude']  = $data['latitude'];
            if (array_key_exists('longitude', $data)) $fillable['longitude'] = $data['longitude'];

            $updated = $this->cities->update($city, $fillable);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'city.updated',
                'entity_name' => 'cities',
                'entity_id'   => (string) $city->id,
                'old_values'  => $old,
                'new_values'  => $this->auditPayload($updated),
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $updated;
        });
    }

    public function deactivate(int $actorId, int $cityId, string $ip, string $userAgent)
    {
        $city = $this->cities->findOrFail($cityId);

        if ($city->status === 'inactive') {
            throw new IngredientException('RESOURCE_ALREADY_DELETED', 'La ciudad ya fue dada de baja.', 409);
        }

        return DB::transaction(function () use ($actorId, $city, $ip, $userAgent) {
            $old     = $this->auditPayload($city);
            $updated = $this->cities->deactivate($city);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'city.deactivated',
                'entity_name' => 'cities',
                'entity_id'   => (string) $city->id,
                'old_values'  => $old,
                'new_values'  => $this->auditPayload($updated),
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $updated;
        });
    }

    public function restore(int $actorId, int $cityId, string $ip, string $userAgent)
    {
        $city = $this->cities->findOrFail($cityId);

        if ($city->status === 'active') {
            throw new IngredientException('RESOURCE_NOT_DELETED', 'La ciudad ya se encuentra activa.', 409);
        }

        return DB::transaction(function () use ($actorId, $city, $ip, $userAgent) {
            $old     = $this->auditPayload($city);
            $updated = $this->cities->restore($city);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'city.restored',
                'entity_name' => 'cities',
                'entity_id'   => (string) $city->id,
                'old_values'  => $old,
                'new_values'  => $this->auditPayload($updated),
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $updated;
        });
    }

    public function catalog()
    {
        return $this->cities->allActive();
    }

    private function auditPayload($city)
    {
        return [
            'name'      => $city->name,
            'province'  => $city->province,
            'country'   => $city->country,
            'latitude'  => $city->latitude,
            'longitude' => $city->longitude,
            'status'    => $city->status,
        ];
    }
}
