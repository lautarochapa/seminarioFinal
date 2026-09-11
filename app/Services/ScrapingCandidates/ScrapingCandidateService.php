<?php

namespace App\Services\ScrapingCandidates;

use App\AuditLog;
use App\Brand;
use App\Ingredient;
use App\Product;
use App\Repositories\ScrapingCandidates\ScrapingCandidateRepository;
use App\Services\Products\ProductImageService;
use App\Services\Products\ProductService;
use App\Services\RecipeImportCandidates\IngredientMatchService;
use App\Exceptions\Ingredients\IngredientException;
use Illuminate\Support\Facades\DB;
use App\UnitMeasure;

class ScrapingCandidateService
{
    private const FINALIZED = ['approved', 'rejected'];

    private $repo;
    private $productService;
    private $productImageService;
    private $ingredientMatcher;
    private $categoryMatcher;

    public function __construct(
        ScrapingCandidateRepository $repo,
        ProductService $productService,
        ProductImageService $productImageService,
        IngredientMatchService $ingredientMatcher,
        CategoryMatchService $categoryMatcher
    ) {
        $this->repo                = $repo;
        $this->productService      = $productService;
        $this->productImageService = $productImageService;
        $this->ingredientMatcher   = $ingredientMatcher;
        $this->categoryMatcher     = $categoryMatcher;
    }

    /**
     * Para el listado se calcula solo la parte liviana del enrichment
     * (ready_for_approval + motivos bloqueantes) y no la completa (marca,
     * ingrediente, categoria) para evitar N+1 pesado sobre paginas de hasta
     * 100 candidatos. El detalle completo se calcula en show().
     */
    public function list(array $filters)
    {
        $paginator = $this->repo->paginate($filters);

        $paginator->getCollection()->each(function (\App\ScrapedProductCandidate $candidate) {
            $readiness = $this->readinessFor($candidate);
            $candidate->setAttribute('enrichment', [
                'ready_for_approval' => $readiness['ready'],
                'review_reasons'     => $readiness['reasons'],
            ]);
        });

        return $paginator;
    }

    public function show(int $id)
    {
        $candidate = $this->repo->findOrFail($id);
        $candidate->setAttribute('enrichment', $this->enrichmentFor($candidate));

        return $candidate;
    }

    /**
     * Version liviana de evaluateReadiness(): solo los factores bloqueantes
     * (nombre, conflicto de EAN, unidad de presentacion), sin las busquedas
     * de marca/ingrediente/categoria que solo alimentan sugerencias.
     */
    public function readinessFor(\App\ScrapedProductCandidate $candidate): array
    {
        $existingProductId = $candidate->ean ? $this->repo->findProductIdByBarcode($candidate->ean) : null;

        return $this->evaluateReadiness($candidate, $existingProductId);
    }

    /**
     * Calcula, sin persistir nada, que datos del scraping ya son confiables para
     * precargar la aprobacion y cuales requieren revision humana. No autoaprueba:
     * solo informa a la UI y sirve de default cuando el admin no envia un valor.
     */
    public function enrichmentFor(\App\ScrapedProductCandidate $candidate): array
    {
        $package = $candidate->raw_payload_json ?? [];
        $packageUnit = null;
        if (! empty($package['net_quantity']) && ! empty($package['package_unit_code'])) {
            $packageUnit = $this->findPackageUnit((string) $package['package_unit_code']);
        }

        $brandId = $this->matchBrandId($candidate->raw_brand);

        $ingredientId   = $candidate->suggested_ingredient_id;
        $ingredientName = $candidate->suggested_ingredient_id ? optional($candidate->suggestedIngredient)->name : null;
        $ingredientConfidence = $candidate->suggested_ingredient_id ? 'manual' : null;
        if (! $ingredientId) {
            $match = $this->ingredientMatcher->matchProductName((string) $candidate->raw_name);
            $ingredientId         = $match['suggested_ingredient_id'];
            $ingredientName       = $match['suggested_ingredient_name'];
            $ingredientConfidence = $match['confidence'];
        }

        $sourceCategoryPath = ! empty($package['source_category_path']) ? (string) $package['source_category_path'] : null;
        $sourceCategoryId   = ! empty($package['source_category_id']) ? (string) $package['source_category_id'] : null;
        $categoryMatch = $sourceCategoryPath ? $this->categoryMatcher->match($sourceCategoryPath) : ['category_id' => null, 'category_name' => null, 'confidence' => null];
        $sourceCategorySegments = $sourceCategoryPath ? $this->categoryMatcher->splitPath($sourceCategoryPath) : [];

        $existingProductId = $candidate->ean ? $this->repo->findProductIdByBarcode($candidate->ean) : null;
        $readiness = $this->evaluateReadiness($candidate, $existingProductId, $sourceCategoryPath, $categoryMatch['category_id']);

        return [
            'detected' => [
                'name'                => $candidate->raw_name,
                'brand'               => $candidate->raw_brand,
                'ean'                 => $candidate->ean,
                'net_quantity'        => ! empty($package['net_quantity']) ? (float) $package['net_quantity'] : null,
                'package_unit_id'     => $packageUnit ? $packageUnit->id : null,
                'package_unit_label'  => $packageUnit ? ($packageUnit->symbol ?: $packageUnit->code) : null,
                'price'               => $candidate->raw_price,
                'source_url'          => $candidate->raw_product_url,
                'image_url'           => $candidate->raw_image_url,
                'external_product_id' => $candidate->external_product_id,
                'source_category'     => [
                    'name'        => ! empty($sourceCategorySegments) ? end($sourceCategorySegments) : null,
                    'path'        => $sourceCategoryPath,
                    'external_id' => $sourceCategoryId,
                ],
            ],
            'suggested' => [
                'brand_id'              => $brandId,
                'ingredient_id'         => $ingredientId,
                'ingredient_name'       => $ingredientName,
                'ingredient_confidence' => $ingredientConfidence,
                'existing_product_id'   => $existingProductId,
                'category_id'           => $categoryMatch['category_id'],
                'category_name'         => $categoryMatch['category_name'],
                'category_confidence'   => $categoryMatch['confidence'],
            ],
            'unresolved' => array_values(array_filter([
                $brandId ? null : 'brand',
                $categoryMatch['category_id'] ? null : 'category',
                $ingredientId ? null : 'ingredient',
            ])),
            'ready_for_approval' => $readiness['ready'],
            'review_reasons'     => $readiness['reasons'],
            'review_notes'       => $readiness['notes'],
        ];
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

        return DB::transaction(function () use ($actorId, $candidate, $data, $ip, $userAgent) {
            return $this->doCreateProduct($actorId, $candidate, $data, $ip, $userAgent);
        });
    }

    /**
     * Accion principal de la pantalla de revision: crea (si todavia no existe un
     * producto interno asociado) y aprueba el candidato en un solo paso, usando
     * los mismos datos enriquecidos que "Crear producto" + "Aprobar" por separado.
     * Si el candidato ya fue mapeado a un producto (match-product o create-product
     * previos), no vuelve a crear uno: solo aprueba.
     */
    public function createAndApprove(int $actorId, int $id, array $data, string $ip, string $userAgent)
    {
        $candidate = $this->repo->findOrFail($id);
        $this->assertNotFinalized($candidate);

        return DB::transaction(function () use ($actorId, $candidate, $data, $ip, $userAgent) {
            if (! $candidate->suggested_product_id) {
                $candidate = $this->doCreateProduct($actorId, $candidate, $data, $ip, $userAgent);
            }

            return $this->doApprove($actorId, $candidate, $ip, $userAgent);
        });
    }

    /**
     * Aprobacion masiva: aplica createAndApprove() a cada candidato de la
     * lista, cada uno en su propia transaccion (la que ya abre
     * createAndApprove), para que una falla en uno no afecte a los demas.
     * Solo procesa candidatos pendientes con ready_for_approval=true; los
     * demas se reportan como "skipped" sin tocarlos. Nunca aborta el lote
     * completo por una falla individual.
     *
     * @param int[] $candidateIds
     */
    public function bulkCreateAndApprove(int $actorId, array $candidateIds, string $ip, string $userAgent): array
    {
        $candidates = $this->repo->findManyByIds($candidateIds)->keyBy('id');

        $approved = 0;
        $skipped  = 0;
        $failed   = 0;
        $results  = [];

        foreach ($candidateIds as $id) {
            $candidate = $candidates->get($id);

            if (! $candidate) {
                $failed++;
                $results[] = ['candidate_id' => $id, 'status' => 'failed', 'reason' => 'not_found'];
                continue;
            }

            if (in_array($candidate->review_status, self::FINALIZED)) {
                $skipped++;
                $results[] = [
                    'candidate_id' => $id,
                    'status'       => 'skipped',
                    'reason'       => $candidate->review_status === 'approved' ? 'already_approved' : 'already_rejected',
                ];
                continue;
            }

            $readiness = $this->readinessFor($candidate);
            if (! $readiness['ready']) {
                $skipped++;
                $results[] = [
                    'candidate_id'    => $id,
                    'status'          => 'skipped',
                    'reason'          => 'not_ready',
                    'review_reasons'  => $readiness['reasons'],
                ];
                continue;
            }

            try {
                $updated = $this->createAndApprove($actorId, $id, [], $ip, $userAgent);
                $approved++;
                $results[] = [
                    'candidate_id' => $id,
                    'status'       => 'approved',
                    'product_id'   => $updated->suggested_product_id,
                ];
            } catch (\Throwable $e) {
                $failed++;
                $results[] = [
                    'candidate_id' => $id,
                    'status'       => 'failed',
                    'reason'       => $e instanceof IngredientException ? $e->getErrorCode() : 'unexpected_error',
                ];
            }
        }

        return [
            'requested' => count($candidateIds),
            'approved'  => $approved,
            'skipped'   => $skipped,
            'failed'    => $failed,
            'results'   => $results,
        ];
    }

    private function doCreateProduct(int $actorId, \App\ScrapedProductCandidate $candidate, array $data, string $ip, string $userAgent): \App\ScrapedProductCandidate
    {
        $productData = $this->buildProductData($candidate, $data);

        $product = $this->productService->create($actorId, $productData, $ip, $userAgent);
        $this->attachScrapedImage($actorId, $product, $candidate, $ip, $userAgent);

        $old     = $this->auditSnapshot($candidate);
        $updated = $this->repo->update($candidate, [
            'suggested_product_id' => $product->id,
            'review_status'        => 'created',
        ]);

        $this->audit($actorId, 'candidate.product_created', $candidate->id, $old, $this->auditSnapshot($updated), $ip, $userAgent);

        return $updated;
    }

    /**
     * Combina los datos confiables del scraping (marca por coincidencia exacta,
     * ingrediente sugerido de forma conservadora, presentacion ya calculada por
     * PackagePresentationParser) con las correcciones explicitas del admin, que
     * siempre tienen prioridad.
     */
    private function buildProductData(\App\ScrapedProductCandidate $candidate, array $overrides): array
    {
        $productData = [
            'name'          => $overrides['name'] ?? $candidate->raw_name,
            'brand_id'      => $overrides['brand_id'] ?? ($this->matchBrandId($candidate->raw_brand) ?: 0),
            'category_id'   => $overrides['category_id'] ?? $this->defaultCategoryId($candidate),
            'ingredient_id' => $overrides['ingredient_id'] ?? $this->defaultIngredientId($candidate),
            'barcode'       => $candidate->ean ?: null,
            'status'        => 'active',
        ];

        $package = $candidate->raw_payload_json ?? [];
        if (! empty($package['net_quantity']) && ! empty($package['package_unit_code'])) {
            $packageUnit = $this->findPackageUnit((string) $package['package_unit_code']);
            if ($packageUnit) {
                $productData['net_quantity'] = (float) $package['net_quantity'];
                $productData['package_unit_id'] = $packageUnit->id;
                $productData['default_unit_id'] = $packageUnit->id;
            }
        }

        // Remove null-only optional fields (but keep brand_id=0)
        return array_filter($productData, function ($v) { return $v !== null; });
    }

    private function defaultIngredientId(\App\ScrapedProductCandidate $candidate): ?int
    {
        if ($candidate->suggested_ingredient_id) {
            return $candidate->suggested_ingredient_id;
        }

        $match = $this->ingredientMatcher->matchProductName((string) $candidate->raw_name);

        return $match['suggested_ingredient_id'];
    }

    private function defaultCategoryId(\App\ScrapedProductCandidate $candidate): ?int
    {
        $package = $candidate->raw_payload_json ?? [];
        if (empty($package['source_category_path'])) {
            return null;
        }

        $match = $this->categoryMatcher->match((string) $package['source_category_path']);

        return $match['category_id'];
    }

    private function matchBrandId(?string $rawBrand): ?int
    {
        if ($rawBrand === null || trim($rawBrand) === '') {
            return null;
        }

        $normalized = mb_strtolower(preg_replace('/\s+/', ' ', trim($rawBrand)), 'UTF-8');

        $brand = Brand::where('status', 'active')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($normalized) {
                $q->where('normalized_name', $normalized)
                  ->orWhereRaw('lower(name) = ?', [$normalized]);
            })
            ->first();

        return $brand ? $brand->id : null;
    }

    /**
     * Usa la imagen ya scrapeada (VTEX/Carrefour) como imagen del producto,
     * reutilizando el mecanismo existente de ProductImage. No se crea storage
     * nuevo: solo se registra la URL externa, igual que hace el endpoint manual
     * de carga de imagenes.
     */
    private function attachScrapedImage(int $actorId, Product $product, \App\ScrapedProductCandidate $candidate, string $ip, string $userAgent): void
    {
        $url = $candidate->raw_image_url;
        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('/^https?:\/\//i', $url)) {
            return;
        }

        $this->productImageService->addImage($actorId, $product->id, [
            'image_url'  => $url,
            'source'     => 'scraper',
            'is_primary' => true,
        ], $ip, $userAgent);
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

        return DB::transaction(function () use ($actorId, $candidate, $ip, $userAgent) {
            return $this->doApprove($actorId, $candidate, $ip, $userAgent);
        });
    }

    private function doApprove(int $actorId, \App\ScrapedProductCandidate $candidate, string $ip, string $userAgent): \App\ScrapedProductCandidate
    {
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

    private function findPackageUnit(string $code): ?UnitMeasure
    {
        $aliases = [
            'g' => ['g', 'gr'],
            'kg' => ['kg', 'kilo'],
            'ml' => ['ml'],
            'l' => ['l', 'lt'],
        ];
        $values = $aliases[$code] ?? [$code];

        return UnitMeasure::where('status', 'active')
            ->where(function ($query) use ($values) {
                foreach ($values as $value) {
                    $query->orWhereRaw('lower(code) = ?', [$value])
                        ->orWhereRaw('lower(symbol) = ?', [$value]);
                }
            })
            ->first();
    }

    /**
     * Evalua si el candidato puede aprobarse/crearse sin revision adicional.
     * Conservador: no exige ingrediente ni categoria (son opcionales para
     * Product), pero si detecta un EAN que ya pertenece a otro producto o una
     * unidad de presentacion no reconocida, marca revision manual (bloqueante).
     * La falta de categoria local equivalente es solo informativa (no
     * bloqueante): se reporta en "notes", no en "reasons".
     */
    private function evaluateReadiness(\App\ScrapedProductCandidate $candidate, ?int $existingProductId, ?string $sourceCategoryPath = null, ?int $matchedCategoryId = null): array
    {
        $reasons = [];
        $notes = [];

        if (trim((string) $candidate->raw_name) === '') {
            $reasons[] = 'Sin nombre de producto detectado.';
        }

        if ($existingProductId && $existingProductId !== $candidate->suggested_product_id) {
            $reasons[] = 'El EAN ya pertenece a otro producto (#' . $existingProductId . '); asociarlo en vez de crear uno nuevo.';
        }

        $package = $candidate->raw_payload_json ?? [];
        if (! empty($package['net_quantity']) && ! empty($package['package_unit_code']) && ! $this->findPackageUnit((string) $package['package_unit_code'])) {
            $reasons[] = 'La unidad de presentacion detectada no existe en el catalogo de unidades.';
        }

        if ($sourceCategoryPath && ! $matchedCategoryId) {
            $notes[] = 'Categoria Carrefour detectada pero sin categoria local equivalente.';
        }

        return ['ready' => empty($reasons), 'reasons' => $reasons, 'notes' => $notes];
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
