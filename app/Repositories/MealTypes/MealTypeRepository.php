<?php

namespace App\Repositories\MealTypes;

use App\MealType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class MealTypeRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = MealType::query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', $search)
                  ->orWhere('code', 'ILIKE', $search);
            });
        }

        $sortMap = ['id' => 'id', 'name' => 'name', 'sort_order' => 'sort_order', 'code' => 'code', 'status' => 'status'];
        $sort    = $sortMap[$filters['sort'] ?? ''] ?? 'sort_order';
        $order   = ($filters['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);
        return $query->orderBy($sort, $order)->orderBy('name', 'asc')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): MealType
    {
        $mt = MealType::find($id);
        if (!$mt) {
            throw new \RuntimeException('MEAL_TYPE_NOT_FOUND');
        }
        return $mt;
    }

    public function activeCatalog(): Collection
    {
        return MealType::where('status', 'active')
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();
    }

    public function codeExists(string $code, ?int $ignoreId = null): bool
    {
        $q = MealType::where('code', $code);
        if ($ignoreId) {
            $q->where('id', '!=', $ignoreId);
        }
        return $q->exists();
    }

    public function nameExists(string $name, ?int $ignoreId = null): bool
    {
        $q = MealType::where('name', $name);
        if ($ignoreId) {
            $q->where('id', '!=', $ignoreId);
        }
        return $q->exists();
    }

    public function create(array $data): MealType
    {
        return MealType::create($data);
    }

    public function update(MealType $mt, array $data): void
    {
        $mt->fill($data);
        $mt->save();
    }

    public function setStatus(MealType $mt, string $status): void
    {
        $mt->status = $status;
        $mt->save();
    }
}
