<?php

namespace Tests\Feature\Api\V1\Scraping;

use App\City;
use App\Jobs\RunScrapingJob;
use App\Role;
use App\ScrapingJob;
use App\ScrapingSource;
use App\Scraping\Parsers\CarrefourParser;
use App\SupermarketBranch;
use App\SupermarketChain;
use App\SupermarketProduct;
use App\SupermarketProductPrice;
use App\Product;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScrapingTest extends TestCase
{
    use RefreshDatabase;

    private function admin()
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

    private function regularUser()
    {
        return factory(User::class)->create();
    }

    private function source(array $data = []): ScrapingSource
    {
        return ScrapingSource::create(array_merge([
            'code'      => 'carrefour_' . Str::random(4),
            'name'      => 'Carrefour Bariloche',
            'type'      => 'web_scraper',
            'base_url'  => 'https://www.carrefour.com.ar',
            'is_active' => true,
            'status'    => 'active',
        ], $data));
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
            'name'     => 'Bariloche ' . uniqid(),
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

    private function product(): Product
    {
        $name = 'Prod ' . uniqid();
        return Product::create([
            'nombre'          => $name,
            'brand_id'        => 0,
            'codigo'          => 'BC' . uniqid(),
            'img'             => 'test.png',
            'habilitado'      => 1,
            'supply_id'       => 0,
            'name'            => $name,
            'normalized_name' => 'prod_' . uniqid(),
            'is_active'       => true,
            'status'          => 'active',
        ]);
    }

    private function supermarketProduct(SupermarketBranch $branch, array $data = []): SupermarketProduct
    {
        $prod = $this->product();
        return SupermarketProduct::create(array_merge([
            'product_id'            => $prod->id,
            'supermarket_chain_id'  => $branch->supermarket_chain_id,
            'supermarket_branch_id' => $branch->id,
            'external_sku'          => 'SKU-' . uniqid(),
            'status'                => 'active',
        ], $data));
    }

    private function fixtureJson(): array
    {
        return json_decode(
            file_get_contents(__DIR__ . '/fixtures/carrefour_products.json'),
            true
        );
    }

    public function test_acceso_sin_permiso_retorna_403()
    {
        $user = $this->regularUser();

        $this->actingAs($user)->getJson('/api/v1/admin/scraping/sources')
            ->assertStatus(403);
        $this->actingAs($user)->postJson('/api/v1/admin/scraping/sources', [])
            ->assertStatus(403);
        $this->actingAs($user)->getJson('/api/v1/admin/scraping/jobs')
            ->assertStatus(403);
        $this->actingAs($user)->postJson('/api/v1/admin/scraping/jobs', [])
            ->assertStatus(403);
    }

    public function test_alta_fuente_retorna_201()
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/scraping/sources', [
            'code'     => 'carrefour',
            'name'     => 'Carrefour Bariloche',
            'type'     => 'web_scraper',
            'base_url' => 'https://www.carrefour.com.ar',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'carrefour')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonStructure(['data' => ['id', 'code', 'name', 'base_url', 'status'], 'trace_id']);

        $this->assertDatabaseHas('scraping_sources', ['code' => 'carrefour']);
    }

    public function test_codigo_duplicado_retorna_409()
    {
        $admin = $this->admin();
        $this->source(['code' => 'carrefour_dup']);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/scraping/sources', [
            'code'     => 'carrefour_dup',
            'name'     => 'Otro Carrefour',
            'base_url' => 'https://www.carrefour.com.ar',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'SCRAPING_SOURCE_DUPLICATE');
    }

    public function test_listado_fuentes_paginado()
    {
        $admin = $this->admin();
        $this->source(['code' => 'src_a']);
        $this->source(['code' => 'src_b']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/scraping/sources');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'code', 'name', 'status']],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'trace_id',
            ]);

        $this->assertGreaterThanOrEqual(2, $response->json('meta.total'));
    }

    public function test_fuente_inactiva_bloquea_creacion_de_job()
    {
        $admin  = $this->admin();
        $source = $this->source(['is_active' => false, 'status' => 'inactive']);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/scraping/jobs', [
            'source_id' => $source->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'SCRAPING_SOURCE_INACTIVE');
    }

    public function test_creacion_job_despacha_queue_y_retorna_202()
    {
        Queue::fake();
        $admin  = $this->admin();
        $source = $this->source(['code' => 'carrefour']);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/scraping/jobs', [
            'source_id' => $source->id,
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure(['data' => ['id', 'source_id', 'status'], 'trace_id']);

        Queue::assertPushed(RunScrapingJob::class);
        $this->assertDatabaseHas('scraping_jobs', [
            'source_id' => $source->id,
            'status'    => 'pending',
        ]);
    }

    public function test_bloqueo_job_simultaneo_retorna_409()
    {
        Queue::fake();
        $admin  = $this->admin();
        $source = $this->source(['code' => 'carrefour_blq']);

        ScrapingJob::create([
            'source_id'   => $source->id,
            'job_type'    => 'product_prices',
            'requested_by' => $admin->id,
            'status'      => 'running',
        ]);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/scraping/jobs', [
            'source_id' => $source->id,
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'SCRAPING_JOB_ALREADY_RUNNING');
    }

    public function test_listado_y_detalle_job()
    {
        $admin  = $this->admin();
        $source = $this->source();

        $job = ScrapingJob::create([
            'source_id'   => $source->id,
            'job_type'    => 'product_prices',
            'requested_by' => $admin->id,
            'status'      => 'completed',
            'total_found' => 10,
        ]);

        $this->actingAs($admin)->getJson('/api/v1/admin/scraping/jobs')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta', 'trace_id']);

        $this->actingAs($admin)->getJson("/api/v1/admin/scraping/jobs/{$job->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $job->id)
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_retry_job_fallido_crea_nuevo_job()
    {
        Queue::fake();
        $admin  = $this->admin();
        $source = $this->source();

        $failedJob = ScrapingJob::create([
            'source_id'     => $source->id,
            'job_type'      => 'product_prices',
            'requested_by'  => $admin->id,
            'status'        => 'failed',
            'error_message' => 'timeout',
        ]);

        $response = $this->actingAs($admin)->postJson("/api/v1/admin/scraping/jobs/{$failedJob->id}/retry");

        $response->assertStatus(202)
            ->assertJsonPath('data.status', 'pending');

        Queue::assertPushed(RunScrapingJob::class);
        $this->assertDatabaseHas('scraping_jobs', [
            'source_id' => $source->id,
            'status'    => 'pending',
        ]);
    }

    public function test_retry_en_job_completado_retorna_409()
    {
        $admin  = $this->admin();
        $source = $this->source();

        $completedJob = ScrapingJob::create([
            'source_id'    => $source->id,
            'job_type'     => 'product_prices',
            'requested_by' => $admin->id,
            'status'       => 'completed',
        ]);

        $this->actingAs($admin)->postJson("/api/v1/admin/scraping/jobs/{$completedJob->id}/retry")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SCRAPING_JOB_CANNOT_RETRY');
    }

    public function test_cancel_job_pendiente_lo_cancela()
    {
        $admin  = $this->admin();
        $source = $this->source();

        $job = ScrapingJob::create([
            'source_id'    => $source->id,
            'job_type'     => 'product_prices',
            'requested_by' => $admin->id,
            'status'       => 'pending',
        ]);

        $response = $this->actingAs($admin)->postJson("/api/v1/admin/scraping/jobs/{$job->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('scraping_jobs', [
            'id'     => $job->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cancel_en_job_completado_retorna_409()
    {
        $admin  = $this->admin();
        $source = $this->source();

        $job = ScrapingJob::create([
            'source_id'    => $source->id,
            'job_type'     => 'product_prices',
            'requested_by' => $admin->id,
            'status'       => 'completed',
        ]);

        $this->actingAs($admin)->postJson("/api/v1/admin/scraping/jobs/{$job->id}/cancel")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SCRAPING_JOB_CANNOT_CANCEL');
    }

    public function test_logs_paginados_y_sin_datos_sensibles()
    {
        $admin  = $this->admin();
        $source = $this->source();

        $job = ScrapingJob::create([
            'source_id'    => $source->id,
            'job_type'     => 'product_prices',
            'requested_by' => $admin->id,
            'status'       => 'completed',
        ]);

        \App\ScrapingJobLog::create([
            'scraping_job_id' => $job->id,
            'level'           => 'info',
            'message'         => 'Prueba de log',
            'context_json'    => ['found' => 5, 'token' => 'SECRETO'],
        ]);

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/scraping/jobs/{$job->id}/logs");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'level', 'message', 'context', 'created_at']], 'meta']);

        $context = $response->json('data.0.context');
        $this->assertArrayNotHasKey('token', $context ?? []);
        $this->assertArrayHasKey('found', $context ?? []);
    }

    public function test_parser_carrefour_extrae_productos_del_fixture()
    {
        $parser   = new CarrefourParser();
        $fixture  = $this->fixtureJson();
        $products = $parser->parse($fixture);

        $this->assertCount(2, $products);

        $first = $products[0];
        $this->assertEquals('Leche Entera La Serenisima 1L', $first->rawName);
        $this->assertEquals('La Serenisima', $first->rawBrand);
        $this->assertEquals(450.00, $first->rawPrice);
        $this->assertEquals('ARS', $first->currency);
        $this->assertEquals('sku001', $first->externalSku);
    }

    public function test_job_completo_crea_candidato_en_base_de_datos()
    {
        $fixture = json_encode($this->fixtureJson());
        Http::fake(['*' => Http::response($fixture, 200)]);

        $admin  = $this->admin();
        $source = $this->source(['code' => 'carrefour', 'base_url' => 'https://www.carrefour.com.ar']);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/scraping/jobs', [
            'source_id' => $source->id,
        ]);

        $response->assertStatus(202);

        $this->assertDatabaseHas('scraped_product_candidates', [
            'source_id'  => $source->id,
            'raw_name'   => 'Leche Entera La Serenisima 1L',
            'raw_price'  => 450.00,
        ]);

        $jobId = $response->json('data.id');
        $this->assertDatabaseHas('scraping_jobs', [
            'id'     => $jobId,
            'status' => 'completed',
        ]);
    }

    public function test_precio_historico_se_crea_cuando_existe_supermarket_product()
    {
        $fixture = json_encode($this->fixtureJson());
        Http::fake(['*' => Http::response($fixture, 200)]);

        $admin  = $this->admin();
        $chain  = $this->chain();
        $city   = $this->city();
        $branch = $this->branch($chain, $city);

        $sp = $this->supermarketProduct($branch, [
            'supermarket_chain_id'  => $chain->id,
            'external_sku'          => 'sku001',
            'external_product_id'   => 'p001',
        ]);

        $source = $this->source(['code' => 'carrefour', 'base_url' => 'https://www.carrefour.com.ar']);

        $this->actingAs($admin)->postJson('/api/v1/admin/scraping/jobs', [
            'source_id'            => $source->id,
            'supermarket_chain_id' => $chain->id,
            'supermarket_branch_id' => $branch->id,
        ])->assertStatus(202);

        $this->assertDatabaseHas('supermarket_product_prices', [
            'supermarket_product_id' => $sp->id,
            'source'                 => 'scraper',
            'currency'               => 'ARS',
        ]);

        $price = SupermarketProductPrice::where('supermarket_product_id', $sp->id)->first();
        $this->assertNotNull($price);
        $this->assertEquals('450.00', $price->price);
    }

    public function test_fuente_no_disponible_marca_job_como_fallido()
    {
        $admin  = $this->admin();
        $source = $this->source(['code' => 'changomas']);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/scraping/jobs', [
            'source_id' => $source->id,
        ]);

        $response->assertStatus(202);

        $jobId = $response->json('data.id');
        $this->assertDatabaseHas('scraping_jobs', [
            'id'     => $jobId,
            'status' => 'failed',
        ]);

        $this->assertDatabaseHas('scraping_alerts', [
            'scraping_job_id' => $jobId,
            'alert_type'      => 'source_unavailable',
            'severity'        => 'high',
        ]);
    }

    public function test_fuente_sin_adaptador_falla_job()
    {
        $admin  = $this->admin();
        $source = $this->source(['code' => 'fuente_sin_adaptador']);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/scraping/jobs', [
            'source_id' => $source->id,
        ]);

        $response->assertStatus(202);

        $jobId = $response->json('data.id');
        $this->assertDatabaseHas('scraping_jobs', [
            'id'     => $jobId,
            'status' => 'failed',
        ]);
    }

    public function test_respuesta_http_error_falla_job()
    {
        Http::fake(['*' => Http::response('Internal Server Error', 500)]);

        $admin  = $this->admin();
        $source = $this->source(['code' => 'carrefour']);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/scraping/jobs', [
            'source_id' => $source->id,
        ]);

        $response->assertStatus(202);

        $jobId = $response->json('data.id');
        $job   = \App\ScrapingJob::find($jobId);

        $this->assertEquals('completed', $job->status);
        $this->assertEquals(0, $job->total_found);
        $this->assertEquals(0, $job->total_pending_review);
    }

    public function test_respuesta_vacia_completa_job_sin_candidatos()
    {
        $fixture = json_encode(json_decode(
            file_get_contents(__DIR__ . '/fixtures/carrefour_empty.json'),
            true
        ));
        Http::fake(['*' => Http::response($fixture, 200)]);

        $admin  = $this->admin();
        $source = $this->source(['code' => 'carrefour']);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/scraping/jobs', [
            'source_id' => $source->id,
        ]);

        $response->assertStatus(202);

        $jobId = $response->json('data.id');
        $this->assertDatabaseHas('scraping_jobs', [
            'id'          => $jobId,
            'status'      => 'completed',
            'total_found' => 0,
        ]);

        $candidates = \App\ScrapedProductCandidate::where('scraping_job_id', $jobId)->count();
        $this->assertEquals(0, $candidates);
    }

    public function test_producto_incompleto_se_descarta_del_parser()
    {
        $parser   = new \App\Scraping\Parsers\CarrefourParser();
        $fixture  = json_decode(
            file_get_contents(__DIR__ . '/fixtures/carrefour_incomplete_product.json'),
            true
        );
        $products = $parser->parse($fixture);

        $this->assertCount(0, $products);
    }

    public function test_precio_identico_no_crea_nuevo_registro()
    {
        $fixture = json_encode($this->fixtureJson());
        Http::fake(['*' => Http::response($fixture, 200)]);

        $admin  = $this->admin();
        $chain  = $this->chain();
        $city   = $this->city();
        $branch = $this->branch($chain, $city);

        $sp = $this->supermarketProduct($branch, [
            'supermarket_chain_id'  => $chain->id,
            'external_sku'          => 'sku001',
            'external_product_id'   => 'p001',
        ]);

        SupermarketProductPrice::create([
            'supermarket_product_id' => $sp->id,
            'price'                  => 450.00,
            'currency'               => 'ARS',
            'source'                 => 'scraper',
            'scraped_at'             => now(),
            'valid_from'             => now(),
            'status'                 => 'active',
        ]);

        $source = $this->source(['code' => 'carrefour']);

        $this->actingAs($admin)->postJson('/api/v1/admin/scraping/jobs', [
            'source_id'             => $source->id,
            'supermarket_chain_id'  => $chain->id,
            'supermarket_branch_id' => $branch->id,
        ])->assertStatus(202);

        $priceCount = SupermarketProductPrice::where('supermarket_product_id', $sp->id)->count();
        $this->assertEquals(1, $priceCount, 'No debe crearse un segundo registro con precio identico');
    }

    public function test_cancel_job_running_marca_cancel_requested()
    {
        $admin  = $this->admin();
        $source = $this->source();

        $job = ScrapingJob::create([
            'source_id'    => $source->id,
            'job_type'     => 'product_prices',
            'requested_by' => $admin->id,
            'status'       => 'running',
            'started_at'   => now(),
        ]);

        $response = $this->actingAs($admin)->postJson("/api/v1/admin/scraping/jobs/{$job->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancel_requested');

        $this->assertDatabaseHas('scraping_jobs', [
            'id'     => $job->id,
            'status' => 'cancel_requested',
        ]);
    }

    public function test_job_inexistente_retorna_404()
    {
        $admin = $this->admin();

        $this->actingAs($admin)->getJson('/api/v1/admin/scraping/jobs/999999')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'SCRAPING_JOB_NOT_FOUND');
    }

    public function test_fuente_inexistente_retorna_404()
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/api/v1/admin/scraping/jobs', [
            'source_id' => 999999,
        ])->assertStatus(422);
    }
}
