<?php

namespace Tests\Feature\Api\V1\RecipeScraping;

use App\ImportedRecipeCandidate;
use App\Jobs\RunRecipeScrapingJob;
use App\Permission;
use App\Role;
use App\Scraping\Adapters\CookpadRecipeScraper;
use App\Scraping\DTOs\RecipeScrapingResult;
use App\Scraping\DTOs\ScrapedRecipeDTO;
use App\ScrapingJob;
use App\ScrapingSource;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecipeScrapingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'scraping.recipe_request_delay_ms' => 0,
            'scraping.retry_backoff_seconds' => [0, 0],
            'scraping.limits.recipe_max_pages' => 10,
            'scraping.limits.recipe_max_items' => 200,
        ]);
    }

    private function adminUser(): User
    {
        $user = factory(User::class)->create();
        $role = Role::firstOrCreate(['code' => 'recipe_admin'], ['name' => 'Recipe Admin', 'status' => 'active']);
        $perm = Permission::firstOrCreate(['code' => 'recipes.manage'], [
            'name' => 'Manage Recipes', 'module' => 'recipes', 'action' => 'manage', 'status' => 'active',
        ]);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
        return $user;
    }

    private function cookpadSource(): ScrapingSource
    {
        return ScrapingSource::firstOrCreate(
            ['code' => 'cookpad'],
            ['name' => 'Cookpad Argentina', 'type' => 'web_scraper', 'base_url' => 'https://cookpad.com/ar', 'is_active' => true, 'status' => 'active']
        );
    }

    private function recipeHtml(string $title = 'Milanesa napolitana'): string
    {
        $ld = json_encode([
            '@context' => 'https://schema.org',
            '@type'    => 'Recipe',
            'name'     => $title,
            'description'        => 'Rica receta.',
            'recipeYield'        => '4',
            'recipeIngredient'   => ['500g milanesa', '100g mozzarella'],
            'recipeInstructions' => [['@type' => 'HowToStep', 'text' => 'Cocinar la milanesa.']],
            'image'              => 'https://cookpad.com/img.jpg',
        ]);
        return '<html><head><script type="application/ld+json">' . $ld . '</script></head><body><h1>' . $title . '</h1></body></html>';
    }

    private function cookpadListingHtml(string $baseUrl = 'https://cookpad.com/ar'): string
    {
        return '<html><body><a href="' . $baseUrl . '/recetas/123456">Ver receta</a></body></html>';
    }

    public function test_sin_autenticacion_retorna_401()
    {
        $this->postJson('/api/v1/admin/recipes/scraping/jobs')->assertStatus(401);
    }

    public function test_sin_permiso_retorna_403()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user)->postJson('/api/v1/admin/recipes/scraping/jobs')->assertStatus(403);
    }

    public function test_crear_job_lo_encola_y_retorna_202()
    {
        Queue::fake();
        $user = $this->adminUser();

        $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/scraping/jobs', ['max_pages' => 2])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.job_type', 'recipe_scraping');

        Queue::assertPushed(RunRecipeScrapingJob::class);
    }

    public function test_listar_jobs_retorna_solo_recipe_scraping()
    {
        Queue::fake();
        $user   = $this->adminUser();
        $source = $this->cookpadSource();

        // Create a product scraping job (should NOT appear in recipe list)
        ScrapingJob::create([
            'source_id' => $source->id, 'job_type' => 'product_prices',
            'status' => 'completed', 'requested_by' => $user->id,
        ]);

        $this->actingAs($user)->postJson('/api/v1/admin/recipes/scraping/jobs', ['max_pages' => 1]);

        $response = $this->actingAs($user)->getJson('/api/v1/admin/recipes/scraping/jobs');
        $response->assertStatus(200);

        $types = array_column($response->json('data'), 'job_type');
        foreach ($types as $type) {
            $this->assertEquals('recipe_scraping', $type);
        }
    }

    public function test_mostrar_job_existente()
    {
        Queue::fake();
        $user = $this->adminUser();

        $createResponse = $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/scraping/jobs', ['max_pages' => 1]);

        $jobId = $createResponse->json('data.id');

        $this->actingAs($user)
            ->getJson('/api/v1/admin/recipes/scraping/jobs/' . $jobId)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $jobId);
    }

    public function test_retry_de_job_fallido_crea_nuevo_job()
    {
        Queue::fake();
        $user   = $this->adminUser();
        $source = $this->cookpadSource();

        $failedJob = ScrapingJob::create([
            'source_id'       => $source->id,
            'job_type'        => 'recipe_scraping',
            'status'          => 'failed',
            'requested_by'    => $user->id,
            'parameters_json' => ['max_pages' => 1],
            'error_message'   => 'Timeout',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/scraping/jobs/' . $failedJob->id . '/retry');

        $response->assertStatus(202);
        $this->assertNotEquals($failedJob->id, $response->json('data.id'));
        Queue::assertPushed(RunRecipeScrapingJob::class);
    }

    public function test_retry_de_job_no_fallido_retorna_422()
    {
        Queue::fake();
        $user   = $this->adminUser();
        $source = $this->cookpadSource();

        $pendingJob = ScrapingJob::create([
            'source_id'    => $source->id,
            'job_type'     => 'recipe_scraping',
            'status'       => 'pending',
            'requested_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/scraping/jobs/' . $pendingJob->id . '/retry')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'RECIPE_SCRAPING_JOB_NOT_RETRYABLE');
    }

    public function test_job_handler_crea_candidatas_con_fixture()
    {
        $user   = $this->adminUser();
        $source = $this->cookpadSource();

        $job = ScrapingJob::create([
            'source_id'       => $source->id,
            'job_type'        => 'recipe_scraping',
            'status'          => 'pending',
            'requested_by'    => $user->id,
            'parameters_json' => ['max_pages' => 1],
        ]);

        $listingUrl = 'https://cookpad.com/ar/buscar/comida?page=1';
        $recipeUrl  = 'https://cookpad.com/ar/recetas/123456';

        Http::fake([
            '*buscar*' => Http::response($this->cookpadListingHtml(), 200),
            '*recetas/123456*'  => Http::response($this->recipeHtml('Milanesa napolitana'), 200),
        ]);

        $scrapingRepo = app(\App\Repositories\Scraping\ScrapingRepository::class);
        $scraper      = app(CookpadRecipeScraper::class);
        $handler      = new RunRecipeScrapingJob($job->id);
        $handler->handle(
            $scrapingRepo,
            $scraper,
            app(\App\Services\Scraping\ScrapingExecutionGuard::class),
            app(\App\Services\Scraping\ScrapingCircuitBreaker::class),
            app(\App\Services\Scraping\UrlSecurityValidator::class)
        );

        $job->refresh();
        $this->assertEquals('completed', $job->status, (string) $job->error_message);
        $this->assertGreaterThanOrEqual(1, $job->total_found);
        $this->assertDatabaseHas('imported_recipe_candidates', [
            'source_url'  => $recipeUrl,
            'source_site' => 'cookpad',
            'status'      => 'parsed',
        ]);
    }

    public function test_no_duplica_candidata_con_misma_url()
    {
        $user   = $this->adminUser();
        $source = $this->cookpadSource();

        ImportedRecipeCandidate::create([
            'source_url'   => 'https://cookpad.com/ar/recetas/123456',
            'source_site'  => 'cookpad',
            'raw_title'    => 'Receta existente',
            'status'       => 'parsed',
        ]);

        $job = ScrapingJob::create([
            'source_id'       => $source->id,
            'job_type'        => 'recipe_scraping',
            'status'          => 'pending',
            'requested_by'    => $user->id,
            'parameters_json' => ['max_pages' => 1],
        ]);

        Http::fake([
            '*buscar*' => Http::response($this->cookpadListingHtml(), 200),
            '*recetas/123456*' => Http::response($this->recipeHtml(), 200),
        ]);

        $scrapingRepo = app(\App\Repositories\Scraping\ScrapingRepository::class);
        $scraper      = app(CookpadRecipeScraper::class);
        $handler      = new RunRecipeScrapingJob($job->id);
        $handler->handle(
            $scrapingRepo,
            $scraper,
            app(\App\Services\Scraping\ScrapingExecutionGuard::class),
            app(\App\Services\Scraping\ScrapingCircuitBreaker::class),
            app(\App\Services\Scraping\UrlSecurityValidator::class)
        );

        $this->assertEquals(1, ImportedRecipeCandidate::where('source_url', 'https://cookpad.com/ar/recetas/123456')->count());
    }

    public function test_urls_duplicadas_de_recetas_no_se_descargan_dos_veces()
    {
        $user   = $this->adminUser();
        $source = $this->cookpadSource();

        $job = ScrapingJob::create([
            'source_id'       => $source->id,
            'job_type'        => 'recipe_scraping',
            'status'          => 'pending',
            'requested_by'    => $user->id,
            'parameters_json' => ['max_pages' => 1],
        ]);

        $listing = '<html><body>'
            . '<a href="https://cookpad.com/ar/recetas/123456?utm_source=test#frag">A</a>'
            . '<a href="/ar/recetas/123456">B</a>'
            . '</body></html>';

        Http::fake([
            '*buscar*' => Http::response($listing, 200),
            '*recetas/123456*' => Http::response($this->recipeHtml(), 200),
        ]);

        (new RunRecipeScrapingJob($job->id))->handle(
            app(\App\Repositories\Scraping\ScrapingRepository::class),
            app(CookpadRecipeScraper::class),
            app(\App\Services\Scraping\ScrapingExecutionGuard::class),
            app(\App\Services\Scraping\ScrapingCircuitBreaker::class),
            app(\App\Services\Scraping\UrlSecurityValidator::class)
        );

        Http::assertSentCount(2);
        $this->assertEquals(1, ImportedRecipeCandidate::where('source_url', 'https://cookpad.com/ar/recetas/123456')->count());
    }

    public function test_parser_receta_extrae_titulo_ingredientes_pasos_tiempos_imagen()
    {
        $scraper = app(CookpadRecipeScraper::class);

        $ld = json_encode([
            '@context'           => 'https://schema.org',
            '@type'              => 'Recipe',
            'name'               => 'Ñoquis caseros',
            'description'        => 'Receta tradicional.',
            'recipeYield'        => '6 porciones',
            'prepTime'           => 'PT20M',
            'cookTime'           => 'PT1H10M',
            'recipeIngredient'   => ['1 kg de papa', '300 g de harina', '2 huevos'],
            'recipeInstructions' => [
                ['@type' => 'HowToStep', 'text' => 'Hervir las papas.'],
                ['@type' => 'HowToStep', 'text' => 'Amasar con harina y huevo.'],
            ],
            'image'              => 'https://cookpad.com/img/noquis.jpg',
        ]);
        $html = '<html><head><script type="application/ld+json">' . $ld . '</script></head><body></body></html>';

        $dto = $scraper->parseRecipePage($html, 'https://cookpad.com/ar/recetas/900001');

        $this->assertNotNull($dto);
        $this->assertSame('Ñoquis caseros', $dto->title);
        $this->assertSame(6, $dto->servings);
        $this->assertSame(20, $dto->prepMinutes);
        $this->assertSame(70, $dto->cookMinutes);
        $this->assertSame(['1 kg de papa', '300 g de harina', '2 huevos'], $dto->ingredients);
        $this->assertCount(2, $dto->steps);
        $this->assertSame('Hervir las papas.', $dto->steps[0]['description']);
        $this->assertSame('https://cookpad.com/img/noquis.jpg', $dto->imageUrl);
    }

    public function test_job_respeta_max_recipes()
    {
        $user   = $this->adminUser();
        $source = $this->cookpadSource();

        $job = ScrapingJob::create([
            'source_id'       => $source->id,
            'job_type'        => 'recipe_scraping',
            'status'          => 'pending',
            'requested_by'    => $user->id,
            'parameters_json' => ['max_pages' => 1, 'max_recipes' => 2],
        ]);

        $links = '';
        foreach ([1, 2, 3, 4, 5] as $n) {
            $links .= '<a href="https://cookpad.com/ar/recetas/90000' . $n . '">R' . $n . '</a>';
        }

        Http::fake([
            '*buscar*'  => Http::response('<html><body>' . $links . '</body></html>', 200),
            '*recetas/90000*'  => Http::response($this->recipeHtml('Receta X'), 200),
            '*' => Http::response('<html><body></body></html>', 200),
        ]);

        (new RunRecipeScrapingJob($job->id))->handle(
            app(\App\Repositories\Scraping\ScrapingRepository::class),
            app(CookpadRecipeScraper::class),
            app(\App\Services\Scraping\ScrapingExecutionGuard::class),
            app(\App\Services\Scraping\ScrapingCircuitBreaker::class),
            app(\App\Services\Scraping\UrlSecurityValidator::class)
        );

        $job->refresh();
        $count = ImportedRecipeCandidate::where('raw_title', 'Receta X')->count();
        $this->assertGreaterThanOrEqual(1, $count);
        $this->assertLessThanOrEqual(2, $count);
    }

    public function test_job_se_detiene_ante_pagina_con_verificacion_anti_bot()
    {
        $user   = $this->adminUser();
        $source = $this->cookpadSource();

        $job = ScrapingJob::create([
            'source_id'       => $source->id,
            'job_type'        => 'recipe_scraping',
            'status'          => 'pending',
            'requested_by'    => $user->id,
            'parameters_json' => ['max_pages' => 1],
        ]);

        Http::fake([
            '*buscar*' => Http::response('<html><body>Please verify you are human before continuing. captcha</body></html>', 200),
            '*' => Http::response('<html><body></body></html>', 200),
        ]);

        (new RunRecipeScrapingJob($job->id))->handle(
            app(\App\Repositories\Scraping\ScrapingRepository::class),
            app(CookpadRecipeScraper::class),
            app(\App\Services\Scraping\ScrapingExecutionGuard::class),
            app(\App\Services\Scraping\ScrapingCircuitBreaker::class),
            app(\App\Services\Scraping\UrlSecurityValidator::class)
        );

        $job->refresh();
        $this->assertEquals('failed', $job->status);
        $this->assertEquals(0, ImportedRecipeCandidate::where('source_site', 'cookpad')->count());
        $this->assertDatabaseHas('scraping_alerts', [
            'scraping_job_id' => $job->id,
            'alert_type'      => 'blocked',
        ]);
    }

    public function test_extract_recipe_links_toma_enlaces_relativos_con_prefijo_de_pais()
    {
        $scraper = app(CookpadRecipeScraper::class);

        $html = '<html><body>'
            . '<a href="/ar/recetas/26566687">Receta A</a>'
            . '<a href="/ar/recetas/26565582?ref=search">Receta B</a>'
            . '<a href="https://cookpad.com/ar/recetas/25377106">Receta C</a>'
            . '<a href="/ar/buscar/comida?page=2">Siguiente</a>'
            . '</body></html>';

        $links = $scraper->extractRecipeLinks($html, 'https://cookpad.com/ar');

        $this->assertContains('https://cookpad.com/ar/recetas/26566687', $links);
        $this->assertContains('https://cookpad.com/ar/recetas/26565582?ref=search', $links);
        $this->assertContains('https://cookpad.com/ar/recetas/25377106', $links);
        $this->assertCount(3, $links);
    }

    public function test_job_usa_la_ruta_de_busqueda_buscar_y_termino_configurable()
    {
        $user   = $this->adminUser();
        $source = $this->cookpadSource();

        $job = ScrapingJob::create([
            'source_id'       => $source->id,
            'job_type'        => 'recipe_scraping',
            'status'          => 'pending',
            'requested_by'    => $user->id,
            'parameters_json' => ['max_pages' => 1, 'max_recipes' => 1, 'search_term' => 'pollo'],
        ]);

        Http::fake([
            '*/ar/buscar/pollo*' => Http::response(
                '<html><body><a href="/ar/recetas/777001">R</a></body></html>', 200
            ),
            '*recetas/777001*' => Http::response($this->recipeHtml('Pollo al horno'), 200),
            '*' => Http::response('<html><body></body></html>', 200),
        ]);

        (new RunRecipeScrapingJob($job->id))->handle(
            app(\App\Repositories\Scraping\ScrapingRepository::class),
            app(CookpadRecipeScraper::class),
            app(\App\Services\Scraping\ScrapingExecutionGuard::class),
            app(\App\Services\Scraping\ScrapingCircuitBreaker::class),
            app(\App\Services\Scraping\UrlSecurityValidator::class)
        );

        Http::assertSent(function ($request) {
            return strpos($request->url(), 'cookpad.com/ar/buscar/pollo') !== false;
        });
        Http::assertNotSent(function ($request) {
            return strpos($request->url(), '/busca/recetas') !== false;
        });

        $job->refresh();
        $this->assertEquals('completed', $job->status, (string) $job->error_message);
        $this->assertDatabaseHas('imported_recipe_candidates', [
            'source_url' => 'https://cookpad.com/ar/recetas/777001',
            'raw_title'  => 'Pollo al horno',
        ]);
    }

    public function test_crear_job_persiste_search_term()
    {
        Queue::fake();
        $user = $this->adminUser();

        $response = $this->actingAs($user)->postJson('/api/v1/admin/recipes/scraping/jobs', [
            'max_pages'   => 1,
            'max_recipes' => 3,
            'search_term' => 'tarta',
        ]);

        $response->assertStatus(202);
        $this->assertEquals('tarta', ScrapingJob::find($response->json('data.id'))->parameters_json['search_term']);
    }

    public function test_auditoria_al_crear_job()
    {
        Queue::fake();
        $user = $this->adminUser();

        $this->actingAs($user)
            ->postJson('/api/v1/admin/recipes/scraping/jobs', ['max_pages' => 1])
            ->assertStatus(202);

        $this->assertDatabaseHas('audit_logs', [
            'user_id'     => $user->id,
            'action'      => 'recipe_scraping_job_created',
            'entity_name' => 'scraping_jobs',
        ]);
    }
}
