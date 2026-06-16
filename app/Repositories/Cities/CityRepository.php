<?php

namespace App\Repositories\Cities;

use App\City;
use App\Exceptions\Ingredients\IngredientException;

class CityRepository
{
    public function paginate(array $filters)
    {
        $query = City::query();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', $search)
                    ->orWhere('province', 'ILIKE', $search)
                    ->orWhere('country', 'ILIKE', $search);
            });
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderBy('name', 'asc')
            ->orderBy('id', 'asc')
            ->paginate($perPage);
    }

    public function findOrFail($id)
    {
        $city = City::find($id);

        if (! $city) {
            throw new IngredientException('CITY_NOT_FOUND', 'La ciudad solicitada no existe.', 404);
        }

        return $city;
    }

    public function existsDuplicate(string $name, string $province, string $country, $exceptId = null)
    {
        $query = City::where('name', $name)
            ->where('province', $province)
            ->where('country', $country);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function create(array $data)
    {
        return City::create($data);
    }

    public function update(City $city, array $data)
    {
        $city->fill($data);
        $city->save();

        return $city->fresh();
    }

    public function deactivate(City $city)
    {
        $city->status = 'inactive';
        $city->save();

        return $city->fresh();
    }

    public function restore(City $city)
    {
        $city->status = 'active';
        $city->save();

        return $city->fresh();
    }

    public function allActive()
    {
        return City::where('status', 'active')
            ->orderBy('name', 'asc')
            ->get();
    }
}
