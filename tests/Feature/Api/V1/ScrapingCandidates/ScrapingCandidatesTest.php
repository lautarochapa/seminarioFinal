<?php

namespace Tests\Feature\Api\V1\ScrapingCandidates;

use App\AuditLog;
use App\City;
use App\Ingredient;
use App\Product;
use App\Role;
use App\ScrapedProductCandidate;
use App\ScrapingJob;
use App\ScrapingSource;
use App\SupermarketBranch;
use App\SupermarketChain;
use App\SupermarketProduct;
use App\SupermarketProductPrice;
use App\User;
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

    private function ingredient(): Ingredient
    {
        return Ingredient::create([
            'name'            => 'Ingrediente ' . uniqid(),
            'normalized_name' => 'ingr_' . Str::random(6),
            'status'          => 'active',
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
}
