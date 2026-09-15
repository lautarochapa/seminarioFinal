<?php

namespace Tests\Feature\Api\V1\ScrapingCandidates;

use App\AuditLog;
use App\Brand;
use App\City;
use App\Ingredient;
use App\Product;
use App\ProductBarcode;
use App\ProductCategory;
use App\Role;
use App\ScrapedProductCandidate;
use App\ScrapingJob;
use App\ScrapingSource;
use App\SupermarketBranch;
use App\SupermarketChain;
use App\SupermarketProduct;
use App\SupermarketProductPrice;
use App\User;
use App\UnitMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScrapingCandidatesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = factory(User::class)->create();
        $role = Role::where('code', 'super_admin')->first();
        DB::table('user_roles')->insert([
            'user_id'    => $user->id,
            'role_id'    => $role->id,
            'created_at' => now(),
        ]);
        return $user;
    }

    private function regularUser(): User
    {
        return factory(User::class)->create();
    }

    private function chain(): SupermarketChain
    {
        $name = 'Chain ' . uniqid();
        return SupermarketChain::create([
            'name'   => $name,
            'code'   => Str::slug($name, '_') . '_' . Str::random(4),
            'status' => 'active',
        ]);
    }

    private function city(): City
    {
        return City::create([
            'name'     => 'City ' . uniqid(),
            'province' => 'Rio Negro',
            'country'  => 'Argentina',
            'status'   => 'active',
        ]);
    }

    private function branch(SupermarketChain $chain, City $city): SupermarketBranch
    {
        return SupermarketBranch::create([
            'supermarket_chain_id' => $chain->id,
            'city_id'              => $city->id,
            'name'                 => 'Sucursal ' . uniqid(),
            'address'              => 'Calle 123',
            'status'               => 'active',
        ]);
    }

    private function source(): ScrapingSource
    {
        return ScrapingSource::create([
            'code'      => 'src_' . Str::random(6),
            'name'      => 'Fuente Test',
            'type'      => 'web_scraper',
            'base_url'  => 'https://example.com',
            'is_active' => true,
            'status'    => 'active',
        ]);
    }

    private function job(ScrapingSource $source, array $params = []): ScrapingJob
    {
        return ScrapingJob::create([
            'source_id'       => $source->id,
            'job_type'        => 'product_prices',
            'status'          => 'completed',
            'parameters_json' => $params,
        ]);
    }

    private function candidate(ScrapingJob $job, array $data = []): ScrapedProductCandidate
    {
        return ScrapedProductCandidate::create(array_merge([
            'scraping_job_id'     => $job->id,
            'source_id'           => $job->source_id,
            'raw_name'            => 'Leche Entera 1L',
            'raw_brand'           => 'La Serenisima',
            'raw_price'           => 450.00,
            'external_product_id' => 'EXT-' . uniqid(),
            'review_status'       => 'pending',
        ], $data));
    }

    private function product(): Product
    {
        $name = 'Producto ' . uniqid();
        return Product::create([
            'nombre'          => $name,
            'name'            => $name,
            'normalized_name' => Str::slug($name, '_'),
            'brand_id'        => 0,
            'codigo'          => 'COD-' . uniqid(),
            'img'             => '',
            'habilitado'      => 1,
            'supply_id'       => 0,
            'is_active'       => true,
            'status'          => 'active',
        ]);
    }

    private function ingredient(array $data = []): Ingredient
    {
        return Ingredient::create(array_merge([
            'name'            => 'Ingrediente ' . uniqid(),
            'normalized_name' => 'ingr_' . Str::random(6),
            'status'          => 'active',
        ], $data));
    }

    private function brand(string $name): Brand
    {
        return Brand::create([
            'name'            => $name,
            'nombre'          => $name,
            'normalized_name' => mb_strtolower($name, 'UTF-8'),
            'status'          => 'active',
        ]);
    }

    private function category(string $name): ProductCategory
    {
        return ProductCategory::create([
            'name'   => $name,
            'status' => 'active',
        ]);
    }

    private function unit(string $code, string $symbol): UnitMeasure
    {
        return UnitMeasure::create([
            'code'   => $code,
            'name'   => $symbol,
            'type'   => 'mass',
            'symbol' => $symbol,
            'status' => 'active',
        ]);
    }

    public function test_acceso_sin_permiso_retorna_403()
    {
        $user    = $this->regularUser();
        $source  = $this->source();
        $job     = $this->job($source);
        $cand    = $this->candidate($job);

        $this->actingAs($user)->getJson('/api/v1/admin/scraping/product-candidates')
            ->assertStatus(403);
        $this->actingAs($user)->getJson("/api/v1/admin/scraping/product-candidates/{$cand->id}")
            ->assertStatus(403);
        $this->actingAs($user)->postJson("/api/v1/admin/scraping/product-candidates/{$cand->id}/approve")
            ->assertStatus(403);
        $this->actingAs($user)->postJson("/api/v1/admin/scraping/product-candidates/{$cand->id}/reject")
            ->assertStatus(403);
    }

    public function test_listado_candidatos_paginado()
    {
        $admin  = $this->admin();
        $source = $this->source();
        $job    = $this->job($source);
        $this->candidate($job);
        $this->candidate($job);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/scraping/product-candidates');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'raw_name', 'raw_price', 'review_status', 'source_id']],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'trace_id',
            ]);
        $this->assertGreaterThanOrEqual(2, $response->json('meta.total'));
    }

    public function test_detalle_candidato()
    {
        $admin  = $this->admin();
        $source = $this->source();
        $job    = $this->job($source);
        $cand   = $this->candidate($job);

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/scraping/product-candidates/{$cand->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $cand->id)
            ->assertJsonPath('data.raw_name', 'Leche Entera 1L')
            ->assertJsonStructure(['data' => ['id', 'raw_name', 'raw_price', 'review_status'], 'trace_id']);
    }

    public function test_filtro_por_review_status()
    {
        $admin  = $this->admin();
        $source = $this->source();
        $job    = $this->job($source);
        $this->candidate($job, ['review_status' => 'pending']);
        $this->candidate($job, ['review_status' => 'rejected']);

        $response = $this->actingAs($admin)->getJson(
            '/api/v1/admin/scraping/product-candidates?review_status=rejected'
        );

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertEquals('rejected', $item['review_status']);
        }
    }

    public function test_match_product_asocia_producto_existente()
    {
        $admin  = $this->admin();
        $source = $this->source();
        $job    = $this->job($source);
        $cand   = $this->candidate($job);
        $prod   = $this->product();

        $response = $this->actingAs($admin)->postJson(
            "/api/v1/admin/scraping/product-candidates/{$cand->id}/match-product",
            ['product_id' => $prod->id]
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.review_status', 'matched')
            ->assertJsonPath('data.suggested_product_id', $prod->id);

        $this->assertDatabaseHas('scraped_product_candidates', [
            'id'                  => $cand->id,
            'review_status'       => 'matched',
            'suggested_product_id' => $prod->id,
        ]);
    }

    public function test_match_product_invalido_retorna_422()
    {
        $admin  = $this->admin();
        $source = $this->source();
        $job    = $this->job($source);
        $cand   = $this->candidate($job);

        $this->actingAs($admin)->postJson(
            "/api/v1/admin/scraping/product-candidates/{$cand->id}/match-product",
            ['product_id' => 99999]
        )->assertStatus(422);
    }

    public function test_create_product_desde_candidato()
    {
        $admin  = $this->admin();
        $source = $this->source();
        $job    = $this->job($source);
        $cand   = $this->candidate($job, ['raw_name' => 'Producto Nuevo Test']);

        $response = $this->actingAs($admin)->postJson(
            "/api/v1/admin/scraping/product-candidates/{$cand->id}/create-product",
            []
        );

        $response->assertStatus(201)
            ->assertJsonPath('data.review_status', 'created');

        $this->assertDatabaseHas('scraped_product_candidates', [
            'id'            => $cand->id,
            'review_status' => 'created',
        ]);

        $updatedCand = ScrapedProductCandidate::find($cand->id);
        $this->assertNotNull($updatedCand->suggested_product_id);
        $this->assertDatabaseHas('products', ['name' => 'Producto Nuevo Test']);
    }

    public function test_create_product_persiste_presentacion_normalizada_del_scraper()
    {
        $admin = $this->admin();
        $source = $this->source();
        $job = $this->job($source);
        $grams = UnitMeasure::create([
            'code' => 'g',
            'name' => 'Gramo',
            'type' => 'mass',
            'symbol' => 'g',
            'status' => 'active',
        ]);
        $candidate = $this->candidate($job, [
            'raw_name' => 'Pure de tomate Arcor brik 520 g.',
            'raw_payload_json' => [
                'ean' => '7790580146115',
                'net_quantity' => 520,
                'package_unit_code' => 'g',
            ],
        ]);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/scraping/product-candidates/'.$candidate->id.'/create-product', [])
            ->assertStatus(201);

        $productId = ScrapedProductCandidate::find($candidate->id)->suggested_product_id;
        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'net_quantity' => 520,
            'package_unit_id' => $grams->id,
            'default_unit_id' => $grams->id,
        ]);
    }

    public function test_assign_ingredient_al_candidato()
    {
        $admin  = $this->admin();
        $source = $this->source();
        $job    = $this->job($source);
        $cand   = $this->candidate($job);
        $ingr   = $this->ingredient();

        $response = $this->actingAs($admin)->postJson(
            "/api/v1/admin/scraping/product-candidates/{$cand->id}/assign-ingredient",
            ['ingredient_id' => $ingr->id]
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.suggested_ingredient_id', $ingr->id);

        $this->assertDatabaseHas('scraped_product_candidates', [
            'id'                    => $cand->id,
            'suggested_ingredient_id' => $ingr->id,
        ]);
    }

    public function test_approve_candidato_con_producto_asociado()
    {
        $admin  = $this->admin();
        $chain  = $this->chain();
        $city   = $this->city();
        $branch = $this->branch($chain, $city);
        $source = $this->source();
        $job    = $this->job($source, [
            'supermarket_chain_id'  => $chain->id,
            'supermarket_branch_id' => $branch->id,
        ]);
        $prod = $this->product();
        $cand = $this->candidate($job, [
            'suggested_product_id' => $prod->id,
            'review_status'        => 'matched',
            'raw_price'            => 500.00,
        ]);

        $response = $this->actingAs($admin)->postJson(
            "/api/v1/admin/scraping/product-candidates/{$cand->id}/approve"
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.review_status', 'approved');

        $this->assertDatabaseHas('scraped_product_candidates', [
            'id'            => $cand->id,
            'review_status' => 'approved',
        ]);

        $this->assertDatabaseHas('supermarket_product_prices', [
            'source'   => 'scraper',
            'currency' => 'ARS',
        ]);
    }

    public function test_approve_sin_producto_asociado_retorna_422()
    {
        $admin  = $this->admin();
        $chain  = $this->chain();
        $city   = $this->city();
        $branch = $this->branch($chain, $city);
        $source = $this->source();
        $job    = $this->job($source, [
            'supermarket_chain_id'  => $chain->id,
            'supermarket_branch_id' => $branch->id,
        ]);
        $cand = $this->candidate($job, ['suggested_product_id' => null]);

        $this->actingAs($admin)->postJson(
            "/api/v1/admin/scraping/product-candidates/{$cand->id}/approve"
        )->assertStatus(422)
         ->assertJsonPath('error.code', 'CANDIDATE_NO_PRODUCT');
    }

    public function test_reject_candidato_con_motivo()
    {
        $admin  = $this->admin();
        $source = $this->source();
        $job    = $this->job($source);
        $cand   = $this->candidate($job);

        $response = $this->actingAs($admin)->postJson(
            "/api/v1/admin/scraping/product-candidates/{$cand->id}/reject",
            ['reason' => 'Producto duplicado']
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.review_status', 'rejected');

        $this->assertDatabaseHas('scraped_product_candidates', [
            'id'            => $cand->id,
            'review_status' => 'rejected',
        ]);
    }

    public function test_candidato_finalizado_no_acepta_nuevas_acciones()
    {
        $admin  = $this->admin();
        $source = $this->source();
        $job    = $this->job($source);
        $prod   = $this->product();
        $cand   = $this->candidate($job, ['review_status' => 'approved']);

        $this->actingAs($admin)->postJson(
            "/api/v1/admin/scraping/product-candidates/{$cand->id}/approve"
        )->assertStatus(409);

        $this->actingAs($admin)->postJson(
            "/api/v1/admin/scraping/product-candidates/{$cand->id}/reject",
            ['reason' => 'intento']
        )->assertStatus(409);

        $this->actingAs($admin)->postJson(
            "/api/v1/admin/scraping/product-candidates/{$cand->id}/match-product",
            ['product_id' => $prod->id]
        )->assertStatus(409);
    }

    public function test_approve_no_duplica_precio_identico()
    {
        $admin  = $this->admin();
        $chain  = $this->chain();
        $city   = $this->city();
        $branch = $this->branch($chain, $city);
        $source = $this->source();
        $job    = $this->job($source, [
            'supermarket_chain_id'  => $chain->id,
            'supermarket_branch_id' => $branch->id,
        ]);
        $prod = $this->product();
        $cand = $this->candidate($job, [
            'suggested_product_id' => $prod->id,
            'review_status'        => 'matched',
            'raw_price'            => 500.00,
        ]);

        // Pre-existing supermarket product + same price
        $sp = SupermarketProduct::create([
            'product_id'           => $prod->id,
            'supermarket_chain_id' => $chain->id,
            'supermarket_branch_id' => $branch->id,
            'status'               => 'active',
        ]);
        SupermarketProductPrice::create([
            'supermarket_product_id' => $sp->id,
            'price'                  => 500.00,
            'currency'               => 'ARS',
            'source'                 => 'manual',
            'scraped_at'             => now(),
            'status'                 => 'active',
        ]);

        $this->actingAs($admin)->postJson(
            "/api/v1/admin/scraping/product-candidates/{$cand->id}/approve"
        )->assertStatus(200);

        $this->assertEquals(1, SupermarketProductPrice::where('supermarket_product_id', $sp->id)->count());
    }

    public function test_approve_con_job_solo_chain_sin_branch_null()
    {
        $admin  = $this->admin();
        $chain  = $this->chain();
        $source = $this->source();
        // Job SIN supermarket_branch_id -> approve() pasa branchId = null.
        $job    = $this->job($source, ['supermarket_chain_id' => $chain->id]);
        $prod   = $this->product();
        $cand   = $this->candidate($job, [
            'suggested_product_id' => $prod->id,
            'review_status'        => 'matched',
            'raw_price'            => 700.00,
        ]);

        $this->actingAs($admin)->postJson(
            "/api/v1/admin/scraping/product-candidates/{$cand->id}/approve"
        )->assertStatus(200)->assertJsonPath('data.review_status', 'approved');

        $sp = SupermarketProduct::where('product_id', $prod->id)
            ->where('supermarket_chain_id', $chain->id)
            ->whereNull('supermarket_branch_id')
            ->first();
        $this->assertNotNull($sp, 'Debe crearse SupermarketProduct con branch NULL');

        $this->assertDatabaseHas('supermarket_product_prices', [
            'supermarket_product_id' => $sp->id,
            'price'                  => 700.00,
            'source'                 => 'scraper',
        ]);

        // Re-aprobar otro candidato del mismo par (chain, product, branch NULL) no duplica el mapping.
        $cand2 = $this->candidate($job, [
            'suggested_product_id' => $prod->id,
            'review_status'        => 'matched',
            'raw_price'            => 700.00,
        ]);
        $this->actingAs($admin)->postJson(
            "/api/v1/admin/scraping/product-candidates/{$cand2->id}/approve"
        )->assertStatus(200);

        $this->assertEquals(
            1,
            SupermarketProduct::where('product_id', $prod->id)
                ->where('supermarket_chain_id', $chain->id)
                ->whereNull('supermarket_branch_id')
                ->count()
        );
    }

    public function test_approve_con_ean_crea_product_barcode()
    {
        $admin  = $this->admin();
        $chain  = $this->chain();
        $city   = $this->city();
        $branch = $this->branch($chain, $city);
        $source = $this->source();
        $job    = $this->job($source, [
            'supermarket_chain_id'  => $chain->id,
            'supermarket_branch_id' => $branch->id,
        ]);
        $prod = $this->product();
        $cand = $this->candidate($job, [
            'suggested_product_id' => $prod->id,
            'review_status'        => 'matched',
            'raw_price'            => 500.00,
            'raw_payload_json'     => ['ean' => '7790895000860'],
        ]);

        $this->actingAs($admin)->postJson(
            "/api/v1/admin/scraping/product-candidates/{$cand->id}/approve"
        )->assertStatus(200)->assertJsonPath('data.review_status', 'approved');

        $this->assertDatabaseHas('product_barcodes', [
            'product_id' => $prod->id,
            'barcode'    => '7790895000860',
            'status'     => 'active',
        ]);
    }

    public function test_reaprobar_otro_candidato_con_mismo_ean_no_duplica_barcode()
    {
        $admin  = $this->admin();
        $chain  = $this->chain();
        $city   = $this->city();
        $branch = $this->branch($chain, $city);
        $source = $this->source();
        $job    = $this->job($source, [
            'supermarket_chain_id'  => $chain->id,
            'supermarket_branch_id' => $branch->id,
        ]);
        $prod = $this->product();

        $first = $this->candidate($job, [
            'suggested_product_id' => $prod->id,
            'review_status'        => 'matched',
            'raw_price'            => 500.00,
            'raw_payload_json'     => ['ean' => '7790895000860'],
        ]);
        $second = $this->candidate($job, [
            'suggested_product_id' => $prod->id,
            'review_status'        => 'matched',
            'raw_price'            => 540.00,
            'raw_payload_json'     => ['ean' => '7790895000860'],
        ]);

        $this->actingAs($admin)->postJson("/api/v1/admin/scraping/product-candidates/{$first->id}/approve")
            ->assertStatus(200);
        $this->actingAs($admin)->postJson("/api/v1/admin/scraping/product-candidates/{$second->id}/approve")
            ->assertStatus(200);

        $this->assertEquals(
            1,
            \App\ProductBarcode::where('barcode', '7790895000860')->count()
        );
    }

    public function test_create_product_desde_candidato_con_ean_genera_barcode()
    {
        $admin  = $this->admin();
        $source = $this->source();
        $job    = $this->job($source);
        $cand   = $this->candidate($job, [
            'raw_name'         => 'Producto Con EAN Test',
            'raw_payload_json' => ['ean' => '7791234567895'],
        ]);

        $response = $this->actingAs($admin)->postJson(
            "/api/v1/admin/scraping/product-candidates/{$cand->id}/create-product",
            []
        );
        $response->assertStatus(201)->assertJsonPath('data.review_status', 'created');

        $productId = ScrapedProductCandidate::find($cand->id)->suggested_product_id;
        $this->assertDatabaseHas('product_barcodes', [
            'product_id' => $productId,
            'barcode'    => '7791234567895',
        ]);
    }

    public function test_candidato_expone_ean_en_detalle()
    {
        $admin  = $this->admin();
        $source = $this->source();
        $job    = $this->job($source);
        $cand   = $this->candidate($job, ['raw_payload_json' => ['ean' => '7790895000860']]);

        $this->actingAs($admin)->getJson("/api/v1/admin/scraping/product-candidates/{$cand->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.ean', '7790895000860');
    }

    public function test_auditoria_creada_al_aprobar()
    {
        $admin  = $this->admin();
        $chain  = $this->chain();
        $city   = $this->city();
        $branch = $this->branch($chain, $city);
        $source = $this->source();
        $job    = $this->job($source, [
            'supermarket_chain_id'  => $chain->id,
            'supermarket_branch_id' => $branch->id,
        ]);
        $prod = $this->product();
        $cand = $this->candidate($job, [
            'suggested_product_id' => $prod->id,
            'review_status'        => 'matched',
            'raw_price'            => 600.00,
        ]);

        $this->actingAs($admin)->postJson(
            "/api/v1/admin/scraping/product-candidates/{$cand->id}/approve"
        )->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'action'      => 'candidate.approved',
            'entity_name' => 'scraped_product_candidates',
            'entity_id'   => (string) $cand->id,
            'user_id'     => $admin->id,
        ]);
    }

    public function test_create_product_precarga_cantidad_desde_nombre_arroz_1kg()
    {
        $admin = $this->admin();
        $source = $this->source();
        $job = $this->job($source);
        $kg = $this->unit('kg', 'kg');
        $cand = $this->candidate($job, [
            'raw_name' => 'Arroz largo fino Gallo 1 kg',
            'raw_payload_json' => [
                'net_quantity' => 1,
                'package_unit_code' => 'kg',
            ],
        ]);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/scraping/product-candidates/' . $cand->id . '/create-product', [])
            ->assertStatus(201);

        $productId = ScrapedProductCandidate::find($cand->id)->suggested_product_id;
        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'net_quantity' => 1,
            'package_unit_id' => $kg->id,
            'default_unit_id' => $kg->id,
        ]);
    }

    public function test_create_product_precarga_cantidad_desde_nombre_aceite_900ml()
    {
        $admin = $this->admin();
        $source = $this->source();
        $job = $this->job($source);
        $ml = $this->unit('ml', 'ml');
        $cand = $this->candidate($job, [
            'raw_name' => 'Aceite de girasol 900 ml',
            'raw_payload_json' => [
                'net_quantity' => 900,
                'package_unit_code' => 'ml',
            ],
        ]);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/scraping/product-candidates/' . $cand->id . '/create-product', [])
            ->assertStatus(201);

        $productId = ScrapedProductCandidate::find($cand->id)->suggested_product_id;
        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'net_quantity' => 900,
            'package_unit_id' => $ml->id,
            'default_unit_id' => $ml->id,
        ]);
    }

    public function test_create_product_reutiliza_marca_existente_por_nombre_scrapeado()
    {
        $admin = $this->admin();
        $source = $this->source();
        $job = $this->job($source);
        $brand = $this->brand('Gallo');
        $cand = $this->candidate($job, [
            'raw_name' => 'Arroz largo fino Gallo 1 kg',
            'raw_brand' => 'Gallo',
        ]);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/scraping/product-candidates/' . $cand->id . '/create-product', [])
            ->assertStatus(201);

        $productId = ScrapedProductCandidate::find($cand->id)->suggested_product_id;
        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'brand_id' => $brand->id,
        ]);
    }

    public function test_create_product_sugiere_ingrediente_de_forma_conservadora()
    {
        $admin = $this->admin();
        $source = $this->source();
        $job = $this->job($source);
        $arroz = $this->ingredient(['name' => 'Arroz', 'normalized_name' => 'arroz']);
        $cand = $this->candidate($job, ['raw_name' => 'Arroz largo fino Gallo 1 kg']);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/scraping/product-candidates/' . $cand->id . '/create-product', [])
            ->assertStatus(201);

        $productId = ScrapedProductCandidate::find($cand->id)->suggested_product_id;
        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'ingredient_id' => $arroz->id,
        ]);
    }

    public function test_create_product_no_autoasigna_ingrediente_por_coincidencia_parcial()
    {
        $admin = $this->admin();
        $source = $this->source();
        $job = $this->job($source);
        $this->ingredient(['name' => 'Arroz', 'normalized_name' => 'arroz']);
        $cand = $this->candidate($job, ['raw_name' => 'Galletitas de arroz sabor pizza']);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/scraping/product-candidates/' . $cand->id . '/create-product', [])
            ->assertStatus(201);

        $productId = ScrapedProductCandidate::find($cand->id)->suggested_product_id;
        $this->assertDatabaseHas('products', [
            'id' => $productId,
        ]);
        $this->assertNull(Product::find($productId)->ingredient_id);
    }

    public function test_create_product_no_autoasigna_ingrediente_alfajor_de_arroz()
    {
        $admin = $this->admin();
        $source = $this->source();
        $job = $this->job($source);
        $this->ingredient(['name' => 'Arroz', 'normalized_name' => 'arroz']);
        $cand = $this->candidate($job, ['raw_name' => 'Alfajor de arroz']);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/scraping/product-candidates/' . $cand->id . '/create-product', [])
            ->assertStatus(201);

        $productId = ScrapedProductCandidate::find($cand->id)->suggested_product_id;
        $this->assertNull(Product::find($productId)->ingredient_id);
    }

    public function test_detalle_candidato_expone_enrichment_listo_para_aprobar()
    {
        $admin = $this->admin();
        $source = $this->source();
        $job = $this->job($source);
        $this->unit('g', 'g');
        $cand = $this->candidate($job, [
            'raw_name' => 'Pure de tomate Arcor brik 520 g.',
            'raw_payload_json' => [
                'ean' => '7790580146115',
                'net_quantity' => 520,
                'package_unit_code' => 'g',
            ],
        ]);

        $response = $this->actingAs($admin)->getJson(
            "/api/v1/admin/scraping/product-candidates/{$cand->id}"
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.enrichment.detected.net_quantity', 520)
            ->assertJsonPath('data.enrichment.detected.ean', '7790580146115')
            ->assertJsonPath('data.enrichment.ready_for_approval', true);
    }

    public function test_detalle_candidato_marca_revision_cuando_ean_pertenece_a_otro_producto()
    {
        $admin = $this->admin();
        $source = $this->source();
        $job = $this->job($source);
        $existingProduct = $this->product();
        ProductBarcode::create([
            'product_id' => $existingProduct->id,
            'barcode'    => '7790895000860',
            'type'       => 'EAN',
            'status'     => 'active',
        ]);
        $cand = $this->candidate($job, [
            'raw_name' => 'Producto Con EAN Repetido',
            'raw_payload_json' => ['ean' => '7790895000860'],
        ]);

        $response = $this->actingAs($admin)->getJson(
            "/api/v1/admin/scraping/product-candidates/{$cand->id}"
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.enrichment.suggested.existing_product_id', $existingProduct->id)
            ->assertJsonPath('data.enrichment.ready_for_approval', false);
    }

    public function test_create_product_con_ean_de_otro_producto_no_duplica()
    {
        $admin = $this->admin();
        $source = $this->source();
        $job = $this->job($source);
        $existingProduct = $this->product();
        ProductBarcode::create([
            'product_id' => $existingProduct->id,
            'barcode'    => '7790895000860',
            'type'       => 'EAN',
            'status'     => 'active',
        ]);
        $cand = $this->candidate($job, [
            'raw_name' => 'Producto Con EAN Repetido',
            'raw_payload_json' => ['ean' => '7790895000860'],
        ]);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/scraping/product-candidates/' . $cand->id . '/create-product', [])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PRODUCT_BARCODE_ALREADY_EXISTS');

        $this->assertEquals(1, Product::where('id', $existingProduct->id)->count());
    }

    public function test_create_and_approve_crea_y_aprueba_en_un_solo_paso()
    {
        $admin  = $this->admin();
        $chain  = $this->chain();
        $city   = $this->city();
        $branch = $this->branch($chain, $city);
        $source = $this->source();
        $job    = $this->job($source, [
            'supermarket_chain_id'  => $chain->id,
            'supermarket_branch_id' => $branch->id,
        ]);
        $cand = $this->candidate($job, [
            'raw_name'  => 'Producto Crear Y Aprobar',
            'raw_price' => 350.00,
        ]);

        $response = $this->actingAs($admin)->postJson(
            "/api/v1/admin/scraping/product-candidates/{$cand->id}/create-and-approve",
            []
        );

        $response->assertStatus(200)->assertJsonPath('data.review_status', 'approved');

        $updated = ScrapedProductCandidate::find($cand->id);
        $this->assertNotNull($updated->suggested_product_id);
        $this->assertDatabaseHas('products', ['id' => $updated->suggested_product_id, 'name' => 'Producto Crear Y Aprobar']);
        $this->assertDatabaseHas('supermarket_products', ['product_id' => $updated->suggested_product_id]);
    }

    public function test_create_and_approve_con_producto_ya_asociado_no_duplica_producto()
    {
        $admin  = $this->admin();
        $chain  = $this->chain();
        $city   = $this->city();
        $branch = $this->branch($chain, $city);
        $source = $this->source();
        $job    = $this->job($source, [
            'supermarket_chain_id'  => $chain->id,
            'supermarket_branch_id' => $branch->id,
        ]);
        $prod = $this->product();
        $cand = $this->candidate($job, [
            'suggested_product_id' => $prod->id,
            'review_status'        => 'matched',
            'raw_price'            => 500.00,
        ]);

        $response = $this->actingAs($admin)->postJson(
            "/api/v1/admin/scraping/product-candidates/{$cand->id}/create-and-approve",
            []
        );

        $response->assertStatus(200)->assertJsonPath('data.review_status', 'approved');
        $this->assertEquals(1, Product::where('id', $prod->id)->count());
        $this->assertDatabaseHas('scraped_product_candidates', [
            'id'                   => $cand->id,
            'suggested_product_id' => $prod->id,
        ]);
    }

    public function test_pantalla_admin_de_candidatos_scrapeados_renderiza_accion_principal()
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get('/admin-web/scraped-products');

        $response->assertStatus(200);
        $response->assertSee('Crear y aprobar', false);
        $response->assertSee('data-candidate-create-and-approve', false);
    }

    public function test_create_product_sugiere_categoria_por_ruta_vtex_hoja()
    {
        $admin = $this->admin();
        $source = $this->source();
        $job = $this->job($source);
        $this->category('Almacen');
        $arroz = $this->category('Arroz');
        $cand = $this->candidate($job, [
            'raw_name' => 'Arroz Gallo 1 kg',
            'raw_payload_json' => ['source_category_path' => '/Almacen/Arroz/', 'source_category_id' => '77'],
        ]);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/scraping/product-candidates/' . $cand->id . '/create-product', [])
            ->assertStatus(201);

        $productId = ScrapedProductCandidate::find($cand->id)->suggested_product_id;
        $this->assertDatabaseHas('products', ['id' => $productId, 'category_id' => $arroz->id]);
    }

    public function test_create_product_sugiere_categoria_padre_cuando_hoja_no_tiene_match()
    {
        $admin = $this->admin();
        $source = $this->source();
        $job = $this->job($source);
        $almacen = $this->category('Almacen');
        $cand = $this->candidate($job, [
            'raw_name' => 'Arroz Gallo 1 kg',
            'raw_payload_json' => ['source_category_path' => '/Almacen/Arroz/'],
        ]);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/scraping/product-candidates/' . $cand->id . '/create-product', [])
            ->assertStatus(201);

        $productId = ScrapedProductCandidate::find($cand->id)->suggested_product_id;
        $this->assertDatabaseHas('products', ['id' => $productId, 'category_id' => $almacen->id]);
    }

    public function test_create_product_no_sugiere_categoria_sin_equivalente_local()
    {
        $admin = $this->admin();
        $source = $this->source();
        $job = $this->job($source);
        $cand = $this->candidate($job, [
            'raw_name' => 'Arroz Gallo 1 kg',
            'raw_payload_json' => ['source_category_path' => '/Almacen/Arroz/'],
        ]);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/scraping/product-candidates/' . $cand->id . '/create-product', [])
            ->assertStatus(201);

        $productId = ScrapedProductCandidate::find($cand->id)->suggested_product_id;
        $this->assertNull(Product::find($productId)->category_id);
    }

    public function test_create_product_no_elige_categoria_ambigua()
    {
        $admin = $this->admin();
        $source = $this->source();
        $job = $this->job($source);
        $this->category('Arroz');
        $this->category('Arroz');
        $cand = $this->candidate($job, [
            'raw_name' => 'Arroz Gallo 1 kg',
            'raw_payload_json' => ['source_category_path' => '/Almacen/Arroz/'],
        ]);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/scraping/product-candidates/' . $cand->id . '/create-product', [])
            ->assertStatus(201);

        $productId = ScrapedProductCandidate::find($cand->id)->suggested_product_id;
        $this->assertNull(Product::find($productId)->category_id);
    }

    public function test_detalle_candidato_expone_categoria_de_origen_y_sugerida()
    {
        $admin = $this->admin();
        $source = $this->source();
        $job = $this->job($source);
        $arroz = $this->category('Arroz');
        $cand = $this->candidate($job, [
            'raw_name' => 'Arroz Gallo 1 kg',
            'raw_payload_json' => ['source_category_path' => '/Almacen/Arroz/', 'source_category_id' => '77'],
        ]);

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/scraping/product-candidates/{$cand->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.enrichment.detected.source_category.path', '/Almacen/Arroz/')
            ->assertJsonPath('data.enrichment.detected.source_category.name', 'Arroz')
            ->assertJsonPath('data.enrichment.detected.source_category.external_id', '77')
            ->assertJsonPath('data.enrichment.suggested.category_id', $arroz->id)
            ->assertJsonPath('data.enrichment.suggested.category_name', 'Arroz')
            ->assertJsonPath('data.enrichment.ready_for_approval', true);
    }

    public function test_detalle_candidato_agrega_nota_no_bloqueante_sin_categoria_equivalente()
    {
        $admin = $this->admin();
        $source = $this->source();
        $job = $this->job($source);
        $cand = $this->candidate($job, [
            'raw_name' => 'Arroz Gallo 1 kg',
            'raw_payload_json' => ['source_category_path' => '/Almacen/Arroz/'],
        ]);

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/scraping/product-candidates/{$cand->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.enrichment.suggested.category_id', null)
            ->assertJsonPath('data.enrichment.ready_for_approval', true);
        $this->assertNotEmpty($response->json('data.enrichment.review_notes'));
    }

    public function test_create_product_override_manual_de_categoria_gana_sobre_sugerida()
    {
        $admin = $this->admin();
        $source = $this->source();
        $job = $this->job($source);
        $this->category('Arroz');
        $otraCategoria = $this->category('Cereales');
        $cand = $this->candidate($job, [
            'raw_name' => 'Arroz Gallo 1 kg',
            'raw_payload_json' => ['source_category_path' => '/Almacen/Arroz/'],
        ]);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/scraping/product-candidates/' . $cand->id . '/create-product', [
                'category_id' => $otraCategoria->id,
            ])
            ->assertStatus(201);

        $productId = ScrapedProductCandidate::find($cand->id)->suggested_product_id;
        $this->assertDatabaseHas('products', ['id' => $productId, 'category_id' => $otraCategoria->id]);
    }

    public function test_create_product_sin_categoria_de_origen_sigue_funcionando()
    {
        $admin = $this->admin();
        $source = $this->source();
        $job = $this->job($source);
        $cand = $this->candidate($job, ['raw_name' => 'Producto Sin Categoria De Origen']);

        $response = $this->actingAs($admin)->postJson(
            '/api/v1/admin/scraping/product-candidates/' . $cand->id . '/create-product',
            []
        );

        $response->assertStatus(201);
        $productId = ScrapedProductCandidate::find($cand->id)->suggested_product_id;
        $this->assertNull(Product::find($productId)->category_id);
    }

    private function jobConChain(): array
    {
        $chain = $this->chain();
        $city = $this->city();
        $branch = $this->branch($chain, $city);
        $source = $this->source();
        $job = $this->job($source, [
            'supermarket_chain_id'  => $chain->id,
            'supermarket_branch_id' => $branch->id,
        ]);

        return ['job' => $job, 'chain' => $chain, 'source' => $source];
    }

    public function test_bulk_aprueba_tres_candidatos_listos()
    {
        $admin = $this->admin();
        $ctx = $this->jobConChain();
        $c1 = $this->candidate($ctx['job'], ['raw_name' => 'Bulk Producto Uno', 'raw_price' => 100]);
        $c2 = $this->candidate($ctx['job'], ['raw_name' => 'Bulk Producto Dos', 'raw_price' => 200]);
        $c3 = $this->candidate($ctx['job'], ['raw_name' => 'Bulk Producto Tres', 'raw_price' => 300]);

        $response = $this->actingAs($admin)->postJson(
            '/api/v1/admin/scraping/product-candidates/bulk-create-and-approve',
            ['candidate_ids' => [$c1->id, $c2->id, $c3->id]]
        );

        $response->assertStatus(200)
            ->assertJsonPath('requested', 3)
            ->assertJsonPath('approved', 3)
            ->assertJsonPath('skipped', 0)
            ->assertJsonPath('failed', 0);

        foreach ([$c1, $c2, $c3] as $cand) {
            $this->assertDatabaseHas('scraped_product_candidates', [
                'id'            => $cand->id,
                'review_status' => 'approved',
            ]);
        }
    }

    public function test_bulk_omite_candidato_no_listo_sin_aprobarlo()
    {
        $admin = $this->admin();
        $ctx = $this->jobConChain();
        $listo = $this->candidate($ctx['job'], ['raw_name' => 'Bulk Listo']);
        $noListo = $this->candidate($ctx['job'], ['raw_name' => '']);

        $response = $this->actingAs($admin)->postJson(
            '/api/v1/admin/scraping/product-candidates/bulk-create-and-approve',
            ['candidate_ids' => [$listo->id, $noListo->id]]
        );

        $response->assertStatus(200)
            ->assertJsonPath('approved', 1)
            ->assertJsonPath('skipped', 1);

        $results = collect($response->json('results'))->keyBy('candidate_id');
        $this->assertEquals('skipped', $results[$noListo->id]['status']);
        $this->assertEquals('not_ready', $results[$noListo->id]['reason']);

        $this->assertDatabaseHas('scraped_product_candidates', ['id' => $listo->id, 'review_status' => 'approved']);
        $this->assertDatabaseHas('scraped_product_candidates', ['id' => $noListo->id, 'review_status' => 'pending']);
        $this->assertNull(ScrapedProductCandidate::find($noListo->id)->suggested_product_id);
    }

    public function test_bulk_omite_candidato_ya_aprobado()
    {
        $admin = $this->admin();
        $ctx = $this->jobConChain();
        $prod = $this->product();
        $yaAprobado = $this->candidate($ctx['job'], [
            'suggested_product_id' => $prod->id,
            'review_status'        => 'approved',
        ]);
        $pendiente = $this->candidate($ctx['job'], ['raw_name' => 'Bulk Pendiente']);

        $response = $this->actingAs($admin)->postJson(
            '/api/v1/admin/scraping/product-candidates/bulk-create-and-approve',
            ['candidate_ids' => [$yaAprobado->id, $pendiente->id]]
        );

        $response->assertStatus(200)
            ->assertJsonPath('approved', 1)
            ->assertJsonPath('skipped', 1);

        $results = collect($response->json('results'))->keyBy('candidate_id');
        $this->assertEquals('already_approved', $results[$yaAprobado->id]['reason']);
        $this->assertEquals(1, Product::where('id', $prod->id)->count());
    }

    public function test_bulk_con_ean_de_otro_producto_no_duplica_y_se_omite()
    {
        $admin = $this->admin();
        $ctx = $this->jobConChain();
        $existingProduct = $this->product();
        ProductBarcode::create([
            'product_id' => $existingProduct->id,
            'barcode'    => '7790895000860',
            'type'       => 'EAN',
            'status'     => 'active',
        ]);
        $conflicto = $this->candidate($ctx['job'], [
            'raw_name'         => 'Bulk Con EAN Repetido',
            'raw_payload_json' => ['ean' => '7790895000860'],
        ]);

        $response = $this->actingAs($admin)->postJson(
            '/api/v1/admin/scraping/product-candidates/bulk-create-and-approve',
            ['candidate_ids' => [$conflicto->id]]
        );

        $response->assertStatus(200)
            ->assertJsonPath('approved', 0)
            ->assertJsonPath('skipped', 1);

        $this->assertEquals(1, Product::where('id', $existingProduct->id)->count());
        $this->assertNull(ScrapedProductCandidate::find($conflicto->id)->suggested_product_id);
    }

    public function test_bulk_error_en_un_candidato_no_revierte_los_demas()
    {
        $admin = $this->admin();
        $ctx = $this->jobConChain();
        // Candidato con nombre ya usado por un producto activo: ProductService
        // rechaza la creacion con PRODUCT_NAME_ALREADY_EXISTS (409), simulando
        // una falla aislada durante la creacion del producto.
        $existingName = 'Bulk Nombre Duplicado';
        Product::create([
            'nombre'          => $existingName,
            'name'            => $existingName,
            'normalized_name' => mb_strtolower($existingName, 'UTF-8'),
            'brand_id'        => 0,
            'codigo'          => 'COD-' . uniqid(),
            'img'             => '',
            'habilitado'      => 1,
            'supply_id'       => 0,
            'is_active'       => true,
            'status'          => 'active',
        ]);
        $falla = $this->candidate($ctx['job'], ['raw_name' => $existingName]);
        $ok1 = $this->candidate($ctx['job'], ['raw_name' => 'Bulk Ok Uno']);
        $ok2 = $this->candidate($ctx['job'], ['raw_name' => 'Bulk Ok Dos']);

        $response = $this->actingAs($admin)->postJson(
            '/api/v1/admin/scraping/product-candidates/bulk-create-and-approve',
            ['candidate_ids' => [$ok1->id, $falla->id, $ok2->id]]
        );

        $response->assertStatus(200)
            ->assertJsonPath('approved', 2)
            ->assertJsonPath('failed', 1);

        $results = collect($response->json('results'))->keyBy('candidate_id');
        $this->assertEquals('failed', $results[$falla->id]['status']);
        $this->assertEquals('PRODUCT_NAME_ALREADY_EXISTS', $results[$falla->id]['reason']);
        $this->assertEquals('pending', ScrapedProductCandidate::find($falla->id)->review_status);
        $this->assertEquals('approved', ScrapedProductCandidate::find($ok1->id)->review_status);
        $this->assertEquals('approved', ScrapedProductCandidate::find($ok2->id)->review_status);
    }

    public function test_bulk_rechaza_mas_de_100_candidatos()
    {
        $admin = $this->admin();
        $ids = range(1, 101);

        $this->actingAs($admin)->postJson(
            '/api/v1/admin/scraping/product-candidates/bulk-create-and-approve',
            ['candidate_ids' => $ids]
        )->assertStatus(422);
    }

    public function test_bulk_requiere_permiso_admin()
    {
        $user = $this->regularUser();
        $ctx = $this->jobConChain();
        $cand = $this->candidate($ctx['job']);

        $this->actingAs($user)->postJson(
            '/api/v1/admin/scraping/product-candidates/bulk-create-and-approve',
            ['candidate_ids' => [$cand->id]]
        )->assertStatus(403);
    }

    public function test_bulk_filtra_candidatos_por_job_en_el_listado()
    {
        $admin = $this->admin();
        $ctx1 = $this->jobConChain();
        $ctx2 = $this->jobConChain();
        $enJob1 = $this->candidate($ctx1['job'], ['raw_name' => 'Del job 1']);
        $enJob2 = $this->candidate($ctx2['job'], ['raw_name' => 'Del job 2']);

        $response = $this->actingAs($admin)->getJson(
            '/api/v1/admin/scraping/product-candidates?scraping_job_id=' . $ctx1['job']->id
        );

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($enJob1->id));
        $this->assertFalse($ids->contains($enJob2->id));
    }

    public function test_bulk_rerun_no_duplica_al_reintentar_candidatos_ya_aprobados()
    {
        $admin = $this->admin();
        $ctx = $this->jobConChain();
        $cand = $this->candidate($ctx['job'], ['raw_name' => 'Bulk Rerun']);

        $this->actingAs($admin)->postJson(
            '/api/v1/admin/scraping/product-candidates/bulk-create-and-approve',
            ['candidate_ids' => [$cand->id]]
        )->assertStatus(200)->assertJsonPath('approved', 1);

        $productId = ScrapedProductCandidate::find($cand->id)->suggested_product_id;

        $second = $this->actingAs($admin)->postJson(
            '/api/v1/admin/scraping/product-candidates/bulk-create-and-approve',
            ['candidate_ids' => [$cand->id]]
        );

        $second->assertStatus(200)
            ->assertJsonPath('approved', 0)
            ->assertJsonPath('skipped', 1);

        $this->assertEquals(1, Product::where('id', $productId)->count());
    }

    public function test_bulk_crea_product_barcode_supermarket_product_y_price_igual_que_individual()
    {
        $admin = $this->admin();
        $ctx = $this->jobConChain();
        $cand = $this->candidate($ctx['job'], [
            'raw_name'         => 'Bulk Con Todo',
            'raw_price'        => 750.00,
            'raw_image_url'    => 'https://img.example.com/bulk.jpg',
            'raw_payload_json' => ['ean' => '7791111111111'],
        ]);

        $this->actingAs($admin)->postJson(
            '/api/v1/admin/scraping/product-candidates/bulk-create-and-approve',
            ['candidate_ids' => [$cand->id]]
        )->assertStatus(200)->assertJsonPath('approved', 1);

        $productId = ScrapedProductCandidate::find($cand->id)->suggested_product_id;
        $this->assertDatabaseHas('product_barcodes', ['product_id' => $productId, 'barcode' => '7791111111111']);
        $this->assertDatabaseHas('supermarket_products', ['product_id' => $productId]);
        $this->assertDatabaseHas('supermarket_product_prices', ['price' => 750.00, 'source' => 'scraper']);
        $this->assertDatabaseHas('product_images', ['product_id' => $productId, 'image_url' => 'https://img.example.com/bulk.jpg']);
    }

    public function test_listado_expone_ready_for_approval_liviano_por_fila()
    {
        $admin = $this->admin();
        $source = $this->source();
        $job = $this->job($source);
        $this->candidate($job, ['raw_name' => 'Listado Listo']);
        $this->candidate($job, ['raw_name' => '']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/scraping/product-candidates');

        $response->assertStatus(200);
        $rows = collect($response->json('data'));
        $this->assertTrue($rows->every(function ($row) {
            return isset($row['enrichment']['ready_for_approval']);
        }));
    }

    public function test_pantalla_admin_de_candidatos_scrapeados_renderiza_seleccion_masiva()
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get('/admin-web/scraped-products');

        $response->assertStatus(200);
        $response->assertSee('data-candidates-bulk-approve', false);
        $response->assertSee('data-candidates-select-all', false);
        $response->assertSee('data-candidates-job', false);
        $response->assertSee('data-candidates-readiness', false);
    }
}
