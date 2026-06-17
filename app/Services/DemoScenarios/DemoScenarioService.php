<?php

namespace App\Services\DemoScenarios;

use App\AuditLog;
use App\DemoScenario;
use App\Repositories\DemoScenarios\DemoScenarioRepository;

class DemoScenarioService
{
    private $repo;

    public function __construct(DemoScenarioRepository $repo)
    {
        $this->repo = $repo;
    }

    public function listAll(array $filters): array
    {
        $paginator = $this->repo->paginateAll($filters);
        return $this->paginatedResponse($paginator);
    }

    public function listActive(array $filters): array
    {
        $paginator = $this->repo->paginateActive($filters);
        return $this->paginatedResponse($paginator);
    }

    public function show(int $id): array
    {
        return $this->format($this->repo->findOrFail($id));
    }

    public function showActive(int $id): array
    {
        return $this->format($this->repo->findActiveOrFail($id));
    }

    public function create(array $data, int $userId, string $ip, string $ua): array
    {
        $scenario = $this->repo->create($data);

        AuditLog::create([
            'user_id'     => $userId,
            'action'      => 'demo_scenario_created',
            'entity_name' => 'demo_scenarios',
            'entity_id'   => $scenario->id,
            'new_values'  => ['name' => $scenario->name, 'status' => $scenario->status],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $this->format($scenario);
    }

    public function update(int $id, array $data, int $userId, string $ip, string $ua): array
    {
        $scenario = $this->repo->findOrFail($id);
        $before   = ['name' => $scenario->name, 'status' => $scenario->status];

        $this->repo->update($scenario, $data);

        AuditLog::create([
            'user_id'     => $userId,
            'action'      => 'demo_scenario_updated',
            'entity_name' => 'demo_scenarios',
            'entity_id'   => $scenario->id,
            'old_values'  => $before,
            'new_values'  => ['name' => $scenario->name, 'status' => $scenario->status],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $this->format($scenario->fresh());
    }

    public function delete(int $id, int $userId, string $ip, string $ua): void
    {
        $scenario = $this->repo->findOrFail($id);

        $this->repo->softDelete($scenario);

        AuditLog::create([
            'user_id'     => $userId,
            'action'      => 'demo_scenario_deleted',
            'entity_name' => 'demo_scenarios',
            'entity_id'   => $id,
            'old_values'  => ['name' => $scenario->name],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);
    }

    private function paginatedResponse($paginator): array
    {
        return [
            'data'  => $paginator->map([$this, 'format'])->values()->toArray(),
            'meta'  => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
        ];
    }

    public function format(DemoScenario $s): array
    {
        return [
            'id'          => $s->id,
            'name'        => $s->name,
            'description' => $s->description,
            'route'       => $s->route,
            'status'      => $s->status,
            'created_at'  => $s->created_at ? $s->created_at->toIso8601String() : null,
            'updated_at'  => $s->updated_at ? $s->updated_at->toIso8601String() : null,
        ];
    }
}
