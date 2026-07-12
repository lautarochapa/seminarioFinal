<?php

namespace App\Services\Nutrients;

use App\AuditLog;
use App\Exceptions\Nutrients\NutrientException;
use App\Nutrient;
use App\ProductNutrient;
use App\Repositories\Nutrients\ProductNutrientRepository;
use Illuminate\Support\Facades\DB;

class ProductNutrientService
{
    private $repo;

    public function __construct(ProductNutrientRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list($productId)
    {
        $this->repo->findProductOrFail($productId);

        return $this->repo->listForProduct($productId);
    }

    public function attach($actorId, $productId, array $data, $ip, $userAgent)
    {
        $this->repo->findProductOrFail($productId);

        $nutrient = Nutrient::where('id', $data['nutrient_id'])->where('status', 'active')->first();
        if (! $nutrient) {
            throw new NutrientException('NUTRIENT_NOT_FOUND', 'El nutriente indicado no existe o no está activo.', 422);
        }

        if ($this->repo->activeRelationExists($productId, $data['nutrient_id'])) {
            throw new NutrientException('NUTRIENT_RELATION_DUPLICATE', 'Ya existe una relación activa entre este producto y nutriente.', 409);
        }

        $payload = [
            'product_id'         => (int) $productId,
            'nutrient_id'        => (int) $data['nutrient_id'],
            'amount_per_100g'    => $data['amount_per_100g'] ?? null,
            'amount_per_serving' => $data['amount_per_serving'] ?? null,
            'serving_size'       => $data['serving_size'] ?? null,
            'source'             => $data['source'] ?? null,
            'status'             => $data['status'] ?? 'active',
        ];

        return DB::transaction(function () use ($actorId, $payload, $ip, $userAgent) {
            $relation = $this->repo->create($payload);
            $this->audit($actorId, 'product_nutrient.created', $relation->id, null, $this->auditPayload($relation), $ip, $userAgent);

            return $relation;
        });
    }

    private function auditPayload(ProductNutrient $relation)
    {
        return [
            'product_id'         => $relation->product_id,
            'nutrient_id'        => $relation->nutrient_id,
            'amount_per_100g'    => $relation->amount_per_100g,
            'amount_per_serving' => $relation->amount_per_serving,
            'serving_size'       => $relation->serving_size,
            'source'             => $relation->source,
            'status'             => $relation->status,
        ];
    }

    private function audit($actorId, $action, $entityId, $old, $new, $ip, $userAgent)
    {
        AuditLog::create([
            'user_id'     => $actorId,
            'action'      => $action,
            'entity_name' => 'product_nutrients',
            'entity_id'   => (string) $entityId,
            'old_values'  => $old,
            'new_values'  => $new,
            'ip_address'  => $ip,
            'user_agent'  => $userAgent,
        ]);
    }
}
