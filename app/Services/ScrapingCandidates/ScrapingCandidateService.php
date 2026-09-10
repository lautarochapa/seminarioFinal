<?php

namespace App\Services\ScrapingCandidates;

use App\AuditLog;
use App\Ingredient;
use App\Product;
use App\Repositories\ScrapingCandidates\ScrapingCandidateRepository;
use App\Services\Products\ProductService;
use App\Exceptions\Ingredients\IngredientException;
use Illuminate\Support\Facades\DB;

class ScrapingCandidateService
{
    private const FINALIZED = ['approved', 'rejected'];

    private $repo;
    private $productService;

    public function __construct(
        ScrapingCandidateRepository $repo,
        ProductService $productService
    ) {
        $this->repo           = $repo;
        $this->productService = $productService;
    }

    public function list(array $filters)
    {
        return $this->repo->paginate($filters);
    }

    public function show(int $id)
    {
        return $this->repo->findOrFail($id);
    }

    public function matchProduct(int $actorId, int $id, int $productId, string $ip, string $userAgent)
    {
        $candidate = $this->repo->findOrFail($id);
        $this->assertNotFinalized($candidate);

        $product = Product::where('id', $productId)->where('status', 'active')->whereNull('deleted_at')->first();
        if (!$product) {
            throw new IngredientException('PRODUCT_NOT_FOUND', 'Producto no encontrado o inactivo.', 422);
        }

        return DB::transaction(function () use ($actorId, $candidate, $product, $ip, $userAgent) {
            $old = $this->auditSnapshot($candidate);

            $updated = $this->repo->update($candidate, [
                'suggested_product_id' => $product->id,
                'review_status'        => 'matched',
            ]);

            $this->audit($actorId, 'candidate.matched', $candidate->id, $old, $this->auditSnapshot($updated), $ip, $userAgent);

            return $updated;
        });
    }

    public function createProduct(int $actorId, int $id, array $data, string $ip, string $userAgent)
    {
        $candidate = $this->repo->findOrFail($id);
        $this->assertNotFinalized($candidate);

        $productData = [
            'name'          => $data['name'] ?? $candidate->raw_name,
            'brand_id'      => $data['brand_id'] ?? 0,
            'category_id'   => $data['category_id'] ?? null,
            'ingredient_id' => $data['ingredient_id'] ?? ($candidate->suggested_ingredient_id ?: null),
            'barcode'       => $candidate->ean ?: null,
            'status'        => 'active',
        ];

        // Remove null-only optional fields (but keep brand_id=0)
        $productData = array_filter($productData, function ($v) { return $v !== null; });

        return DB::transaction(function () use ($actorId, $candidate, $productData, $ip, $userAgent) {
            $product = $this->productService->create($actorId, $productData, $ip, $userAgent);

            $old     = $this->auditSnapshot($candidate);
            $updated = $this->repo->update($candidate, [
                'suggested_product_id' => $product->id,
                'review_status'        => 'created',
            ]);

            $this->audit($actorId, 'candidate.product_created', $candidate->id, $old, $this->auditSnapshot($updated), $ip, $userAgent);

            return $updated;
        });
    }

    public function assignIngredient(int $actorId, int $id, int $ingredientId, string $ip, string $userAgent)
    {
        $candidate = $this->repo->findOrFail($id);
        $this->assertNotFinalized($candidate);

        $ingredient = Ingredient::where('id', $ingredientId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();

        if (!$ingredient) {
            throw new IngredientException('INGREDIENT_NOT_FOUND', 'Ingrediente no encontrado o inactivo.', 422);
        }

        return DB::transaction(function () use ($actorId, $candidate, $ingredient, $ip, $userAgent) {
            $old     = $this->auditSnapshot($candidate);
            $updated = $this->repo->update($candidate, [
                'suggested_ingredient_id' => $ingredient->id,
            ]);

            $this->audit($actorId, 'candidate.ingredient_assigned', $candidate->id, $old, $this->auditSnapshot($updated), $ip, $userAgent);

            return $updated;
        });
    }

    public function approve(int $actorId, int $id, string $ip, string $userAgent)
    {
        $candidate = $this->repo->findOrFail($id);
        $this->assertNotFinalized($candidate);

        if (!$candidate->suggested_product_id) {
            throw new IngredientException(
                'CANDIDATE_NO_PRODUCT',
                'El candidato no tiene un producto interno asociado. Asocia uno antes de aprobar.',
                422
            );
        }

        $params   = $candidate->job ? ($candidate->job->parameters_json ?? []) : [];
        $chainId  = isset($params['supermarket_chain_id']) ? (int) $params['supermarket_chain_id'] : null;
        $branchId = isset($params['supermarket_branch_id']) ? (int) $params['supermarket_branch_id'] : null;

        if (!$chainId) {
            throw new IngredientException(
                'CANDIDATE_NO_CHAIN',
                'El job de scraping no tiene cadena de supermercado configurada.',
                422
            );
        }

        return DB::transaction(function () use ($actorId, $candidate, $chainId, $branchId, $ip, $userAgent) {
            $productId = $candidate->suggested_product_id;

            // Vincular EAN al producto global (idempotente) para que el escaner mobile lo encuentre
            $ean = $candidate->ean;
            if ($ean) {
                $this->repo->linkBarcode((int) $productId, $ean);
            }

            // Buscar o crear SupermarketProduct
            $sp = $this->repo->findSupermarketProductMapping($chainId, $branchId, $productId);

            if (!$sp) {
                $sp = $this->repo->createSupermarketProduct([
                    'product_id'            => $productId,
                    'supermarket_chain_id'  => $chainId,
                    'supermarket_branch_id' => $branchId,
                    'external_product_id'   => $candidate->external_product_id,
                    'external_sku'          => $candidate->external_product_id,
                    'source_url'            => $candidate->raw_product_url,
                    'source_image_url'      => $candidate->raw_image_url,
                    'last_scraped_at'       => now(),
                    'last_seen_at'          => now(),
                    'status'                => 'active',
                ]);
            }

            // Crear precio solo si no existe uno identico activo
            if ($candidate->raw_price !== null) {
                $currentPrice = $this->repo->currentActivePrice($sp->id);
                $rawPrice     = (float) $candidate->raw_price;

                if (!$currentPrice || abs((float) $currentPrice->price - $rawPrice) > 0.001) {
                    if ($currentPrice) {
                        $currentPrice->valid_to = now();
                        $currentPrice->save();
                    }
                    $this->repo->createPrice($sp->id, [
                        'price'      => $rawPrice,
                        'currency'   => 'ARS',
                        'source'     => 'scraper',
                        'scraped_at' => now(),
                        'valid_from' => now(),
                        'status'     => 'active',
                    ]);
                }
            }

            $old     = $this->auditSnapshot($candidate);
            $updated = $this->repo->update($candidate, [
                'review_status' => 'approved',
                'reviewed_by'   => $actorId,
                'reviewed_at'   => now(),
            ]);

            $this->audit($actorId, 'candidate.approved', $candidate->id, $old, $this->auditSnapshot($updated), $ip, $userAgent);

            return $updated;
        });
    }

    public function reject(int $actorId, int $id, ?string $reason, string $ip, string $userAgent)
    {
        $candidate = $this->repo->findOrFail($id);
        $this->assertNotFinalized($candidate);

        return DB::transaction(function () use ($actorId, $candidate, $reason, $ip, $userAgent) {
            $old     = $this->auditSnapshot($candidate);
            $updated = $this->repo->update($candidate, [
                'review_status' => 'rejected',
                'reviewed_by'   => $actorId,
                'reviewed_at'   => now(),
            ]);

            $this->audit($actorId, 'candidate.rejected', $candidate->id, $old, array_merge($this->auditSnapshot($updated), ['reason' => $reason]), $ip, $userAgent);

            return $updated;
        });
    }

    private function assertNotFinalized(\App\ScrapedProductCandidate $candidate): void
    {
        if (in_array($candidate->review_status, self::FINALIZED)) {
            throw new IngredientException(
                'CANDIDATE_ALREADY_FINALIZED',
                'El candidato ya fue procesado y no puede modificarse.',
                409
            );
        }
    }

    private function auditSnapshot(\App\ScrapedProductCandidate $candidate): array
    {
        return [
            'review_status'          => $candidate->review_status,
            'suggested_product_id'   => $candidate->suggested_product_id,
            'suggested_ingredient_id' => $candidate->suggested_ingredient_id,
            'reviewed_by'            => $candidate->reviewed_by,
        ];
    }

    private function audit(int $actorId, string $action, int $entityId, ?array $old, ?array $new, string $ip, string $userAgent): void
    {
        AuditLog::create([
            'user_id'     => $actorId,
            'action'      => $action,
            'entity_name' => 'scraped_product_candidates',
            'entity_id'   => (string) $entityId,
            'old_values'  => $old,
            'new_values'  => $new,
            'ip_address'  => $ip,
            'user_agent'  => $userAgent,
        ]);
    }
}
