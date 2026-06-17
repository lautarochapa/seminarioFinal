<?php

namespace App\Services\MealTypes;

use App\AuditLog;
use App\Exceptions\MealTypes\MealTypeException;
use App\MealType;
use App\Repositories\MealTypes\MealTypeRepository;
use App\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class MealTypeService
{
    private MealTypeRepository $repo;

    public function __construct(MealTypeRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repo->paginate($filters);
    }

    public function show(int $id): MealType
    {
        return $this->findOrFail($id);
    }

    public function catalog(): Collection
    {
        return $this->repo->activeCatalog();
    }

    public function create(User $user, array $input, string $ip, string $ua): MealType
    {
        if ($this->repo->codeExists($input['code'])) {
            throw MealTypeException::codeAlreadyExists();
        }
        if ($this->repo->nameExists($input['name'])) {
            throw MealTypeException::nameAlreadyExists();
        }

        $mt = $this->repo->create([
            'code'       => $input['code'],
            'name'       => $input['name'],
            'sort_order' => $input['sort_order'] ?? 0,
            'status'     => $input['status'] ?? 'active',
        ]);

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'meal_type_created',
            'entity_name' => 'meal_types',
            'entity_id'   => $mt->id,
            'old_values'  => null,
            'new_values'  => ['code' => $mt->code, 'name' => $mt->name, 'sort_order' => $mt->sort_order, 'status' => $mt->status],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $mt;
    }

    public function update(User $user, int $id, array $input, string $ip, string $ua): MealType
    {
        $mt = $this->findOrFail($id);

        if (isset($input['code']) && $this->repo->codeExists($input['code'], $mt->id)) {
            throw MealTypeException::codeAlreadyExists();
        }
        if (isset($input['name']) && $this->repo->nameExists($input['name'], $mt->id)) {
            throw MealTypeException::nameAlreadyExists();
        }

        $allowed = ['code', 'name', 'sort_order', 'status'];
        $fields  = array_intersect_key($input, array_flip($allowed));
        $old     = array_intersect_key($mt->toArray(), $fields);

        $this->repo->update($mt, $fields);

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'meal_type_updated',
            'entity_name' => 'meal_types',
            'entity_id'   => $mt->id,
            'old_values'  => $old,
            'new_values'  => $fields,
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $mt->fresh();
    }

    public function destroy(User $user, int $id, string $ip, string $ua): MealType
    {
        $mt = $this->findOrFail($id);

        if ($mt->status === 'inactive') {
            throw MealTypeException::alreadyInactive();
        }

        $this->repo->setStatus($mt, 'inactive');

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'meal_type_deleted',
            'entity_name' => 'meal_types',
            'entity_id'   => $mt->id,
            'old_values'  => ['status' => 'active'],
            'new_values'  => ['status' => 'inactive'],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $mt->fresh();
    }

    public function restore(User $user, int $id, string $ip, string $ua): MealType
    {
        $mt = $this->findOrFail($id);

        if ($mt->status === 'active') {
            throw MealTypeException::alreadyActive();
        }

        $this->repo->setStatus($mt, 'active');

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'meal_type_restored',
            'entity_name' => 'meal_types',
            'entity_id'   => $mt->id,
            'old_values'  => ['status' => 'inactive'],
            'new_values'  => ['status' => 'active'],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $mt->fresh();
    }

    private function findOrFail(int $id): MealType
    {
        try {
            return $this->repo->findOrFail($id);
        } catch (\RuntimeException $e) {
            throw MealTypeException::notFound();
        }
    }
}
