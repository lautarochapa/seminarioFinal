<?php

namespace App\Repositories\DemoScenarios;

use App\DemoScenario;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

class DemoScenarioRepository
{
    public function paginateAll(array $filters): LengthAwarePaginator
    {
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));
        $page    = max(1, (int) ($filters['page'] ?? 1));

        $query = DemoScenario::orderBy('name');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['name'])) {
            $query->where('name', 'ILIKE', '%' . $filters['name'] . '%');
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function paginateActive(array $filters): LengthAwarePaginator
    {
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));
        $page    = max(1, (int) ($filters['page'] ?? 1));

        return DemoScenario::where('status', 'active')
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function findOrFail(int $id): DemoScenario
    {
        $scenario = DemoScenario::find($id);
        if (!$scenario) {
            throw (new ModelNotFoundException)->setModel(DemoScenario::class, $id);
        }
        return $scenario;
    }

    public function findActiveOrFail(int $id): DemoScenario
    {
        $scenario = DemoScenario::where('status', 'active')->find($id);
        if (!$scenario) {
            throw (new ModelNotFoundException)->setModel(DemoScenario::class, $id);
        }
        return $scenario;
    }

    public function create(array $data): DemoScenario
    {
        return DemoScenario::create([
            'name'         => $data['name'],
            'description'  => $data['description'] ?? null,
            'route'        => $data['route']        ?? null,
            'demo_user_id' => null,
            'status'       => $data['status']       ?? 'active',
        ]);
    }

    public function update(DemoScenario $scenario, array $data): void
    {
        $allowed = ['name', 'description', 'route', 'status'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $scenario->{$field} = $data[$field];
            }
        }
        $scenario->save();
    }

    public function softDelete(DemoScenario $scenario): void
    {
        $scenario->delete();
    }
}
