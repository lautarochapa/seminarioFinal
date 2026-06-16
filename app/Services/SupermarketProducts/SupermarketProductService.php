<?php

namespace App\Services\SupermarketProducts;

use App\AuditLog;
use App\Exceptions\Ingredients\IngredientException;
use App\Repositories\SupermarketProducts\SupermarketProductRepository;
use App\SupermarketBranch;
use App\SupermarketProduct;
use Illuminate\Support\Facades\DB;

class SupermarketProductService
{
    private $repo;

    public function __construct(SupermarketProductRepository $repo)
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
        $branch  = SupermarketBranch::find($data['supermarket_branch_id']);
        $sku     = $data['external_sku'] ?? null;
        $branchId = (int) $data['supermarket_branch_id'];
        $productId = (int) $data['product_id'];

        if ($sku && $this->repo->existsDuplicate($productId, $branchId, $sku)) {
            throw new IngredientException('SUPERMARKET_PRODUCT_ALREADY_EXISTS', 'Ya existe este mapeo de producto, sucursal y SKU.', 409);
        }

        return DB::transaction(function () use ($actorId, $data, $branch, $ip, $userAgent) {
            $sp = $this->repo->create([
                'product_id'            => $data['product_id'],
                'supermarket_chain_id'  => $branch->supermarket_chain_id,
                'supermarket_branch_id' => $data['supermarket_branch_id'],
                'external_sku'          => $data['external_sku'] ?? null,
                'source_url'            => $data['source_url'] ?? null,
                'source_name'           => $data['source_name'] ?? null,
                'last_scraped_at'       => $data['last_scraped_at'] ?? null,
                'status'                => 'active',
            ]);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'supermarket_product.created',
                'entity_name' => 'supermarket_products',
                'entity_id'   => (string) $sp->id,
                'old_values'  => null,
                'new_values'  => $this->auditPayload($sp),
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $sp;
        });
    }

    public function update(int $actorId, int $id, array $data, string $ip, string $userAgent)
    {
        $sp = $this->repo->findOrFail($id);

        $sku = $data['external_sku'] ?? null;
        if ($sku && $sku !== $sp->external_sku) {
            $branchId = isset($data['supermarket_branch_id'])
                ? (int) $data['supermarket_branch_id']
                : (int) $sp->supermarket_branch_id;

            if ($this->repo->existsDuplicate((int) $sp->product_id, $branchId, $sku, $sp->id)) {
                throw new IngredientException('SUPERMARKET_PRODUCT_ALREADY_EXISTS', 'Ya existe este mapeo de producto, sucursal y SKU.', 409);
            }
        }

        return DB::transaction(function () use ($actorId, $sp, $data, $ip, $userAgent) {
            $old      = $this->auditPayload($sp);
            $fillable = array_intersect_key($data, array_flip([
                'external_sku', 'source_url', 'source_name', 'last_scraped_at',
                'supermarket_branch_id',
            ]));
            $updated  = $this->repo->update($sp, $fillable);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'supermarket_product.updated',
                'entity_name' => 'supermarket_products',
                'entity_id'   => (string) $sp->id,
                'old_values'  => $old,
                'new_values'  => $this->auditPayload($updated),
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $updated;
        });
    }

    public function deactivate(int $actorId, int $id, string $ip, string $userAgent)
    {
        $sp = $this->repo->findOrFail($id);

        if ($sp->status === 'inactive') {
            throw new IngredientException('RESOURCE_ALREADY_DELETED', 'El mapeo ya esta inactivo.', 409);
        }

        return DB::transaction(function () use ($actorId, $sp, $ip, $userAgent) {
            $old     = $this->auditPayload($sp);
            $updated = $this->repo->deactivate($sp);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'supermarket_product.deactivated',
                'entity_name' => 'supermarket_products',
                'entity_id'   => (string) $sp->id,
                'old_values'  => $old,
                'new_values'  => $this->auditPayload($updated),
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $updated;
        });
    }

    public function restore(int $actorId, int $id, string $ip, string $userAgent)
    {
        $sp = $this->repo->findOrFail($id);

        if ($sp->status === 'active') {
            throw new IngredientException('RESOURCE_NOT_DELETED', 'El mapeo ya esta activo.', 409);
        }

        return DB::transaction(function () use ($actorId, $sp, $ip, $userAgent) {
            $old     = $this->auditPayload($sp);
            $updated = $this->repo->activate($sp);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'supermarket_product.restored',
                'entity_name' => 'supermarket_products',
                'entity_id'   => (string) $sp->id,
                'old_values'  => $old,
                'new_values'  => $this->auditPayload($updated),
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $updated;
        });
    }

    public function branchProducts(int $branchId, array $filters = [])
    {
        return $this->repo->forBranch($branchId, $filters);
    }

    public function supermarketPrices(int $productId)
    {
        return $this->repo->pricesForProduct($productId);
    }

    public function bestPrice(int $productId)
    {
        $result = $this->repo->bestPriceForProduct($productId);

        if (! $result) {
            throw new IngredientException('BEST_PRICE_NOT_FOUND', 'No hay precios disponibles para este producto.', 404);
        }

        return $result;
    }

    private function auditPayload(SupermarketProduct $sp): array
    {
        return [
            'product_id'            => $sp->product_id,
            'supermarket_branch_id' => $sp->supermarket_branch_id,
            'supermarket_chain_id'  => $sp->supermarket_chain_id,
            'external_sku'          => $sp->external_sku,
            'status'                => $sp->status,
        ];
    }
}
