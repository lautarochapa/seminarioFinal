<?php

namespace App\Services\Promotions;

use App\AuditLog;
use App\Exceptions\Ingredients\IngredientException;
use App\Repositories\Promotions\PromotionRepository;
use Illuminate\Support\Facades\DB;

class PromotionService
{
    private $repo;

    public function __construct(PromotionRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(array $filters)
    {
        return $this->repo->paginate($filters);
    }

    public function show(int $id)
    {
        return $this->repo->findOrFail($id);
    }

    public function create(int $actorId, array $data, string $ip, string $userAgent)
    {
        return DB::transaction(function () use ($actorId, $data, $ip, $userAgent) {
            $promotion = $this->repo->create([
                'supermarket_chain_id'    => $data['supermarket_chain_id'],
                'supermarket_branch_id'   => $data['supermarket_branch_id'] ?? null,
                'name'                    => $data['name'],
                'description'             => $data['description'] ?? null,
                'discount_type'           => $data['discount_type'] ?? null,
                'discount_value'          => $data['discount_value'] ?? null,
                'valid_from'              => $data['valid_from'] ?? null,
                'valid_to'                => $data['valid_to'] ?? null,
                'day_of_week'             => $data['day_of_week'] ?? null,
                'requires_payment_method' => $data['requires_payment_method'] ?? false,
                'status'                  => 'active',
            ]);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'promotion.created',
                'entity_name' => 'promotions',
                'entity_id'   => (string) $promotion->id,
                'old_values'  => null,
                'new_values'  => $this->auditPayload($promotion),
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $promotion;
        });
    }

    public function update(int $actorId, int $id, array $data, string $ip, string $userAgent)
    {
        $promotion = $this->repo->findOrFail($id);

        return DB::transaction(function () use ($actorId, $promotion, $data, $ip, $userAgent) {
            $old     = $this->auditPayload($promotion);
            $allowed = array_intersect_key($data, array_flip([
                'name', 'description', 'discount_type', 'discount_value',
                'valid_from', 'valid_to', 'day_of_week', 'requires_payment_method',
            ]));
            $updated = $this->repo->update($promotion, $allowed);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'promotion.updated',
                'entity_name' => 'promotions',
                'entity_id'   => (string) $updated->id,
                'old_values'  => $old,
                'new_values'  => $this->auditPayload($updated),
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $updated;
        });
    }

    public function destroy(int $actorId, int $id, string $ip, string $userAgent)
    {
        $promotion = $this->repo->findOrFail($id);

        if ($promotion->status === 'inactive') {
            throw new IngredientException('RESOURCE_ALREADY_DELETED', 'La promocion ya esta inactiva.', 409);
        }

        return DB::transaction(function () use ($actorId, $promotion, $ip, $userAgent) {
            $old     = $this->auditPayload($promotion);
            $deleted = $this->repo->softDelete($promotion);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'promotion.deleted',
                'entity_name' => 'promotions',
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
        $promotion = $this->repo->findWithTrashedOrFail($id);

        if (! $promotion->trashed()) {
            throw new IngredientException('RESOURCE_NOT_DELETED', 'La promocion no esta eliminada.', 409);
        }

        return DB::transaction(function () use ($actorId, $promotion, $ip, $userAgent) {
            $old      = $this->auditPayload($promotion);
            $restored = $this->repo->restore($promotion);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'promotion.restored',
                'entity_name' => 'promotions',
                'entity_id'   => (string) $restored->id,
                'old_values'  => $old,
                'new_values'  => $this->auditPayload($restored),
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $restored;
        });
    }

    public function forBranch(int $branchId, array $filters = [])
    {
        return $this->repo->forBranch($branchId, $filters);
    }

    public function catalog(array $filters)
    {
        return $this->repo->catalogActive($filters);
    }

    private function auditPayload($promotion): array
    {
        return [
            'supermarket_chain_id'  => $promotion->supermarket_chain_id,
            'supermarket_branch_id' => $promotion->supermarket_branch_id,
            'name'                  => $promotion->name,
            'discount_type'         => $promotion->discount_type,
            'discount_value'        => $promotion->discount_value,
            'status'                => $promotion->status,
        ];
    }
}
