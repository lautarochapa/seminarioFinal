<?php

namespace Tests\Feature\Api\V1\AdminReports;

use App\ImportedRecipeCandidate;
use App\LoginLog;
use App\Permission;
use App\Recipe;
use App\RecipeCookLog;
use App\Role;
use App\ScrapedProductCandidate;
use App\SupermarketChain;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermission(string $permCode): User
    {
        $user = factory(User::class)->create();
        $perm = Permission::firstOrCreate(['code' => $permCode], ['module' => 'admin', 'action' => 'read', 'status' => 'active']);
        $role = Role::create(['code' => 'role_' . uniqid(), 'name' => 'Role', 'status' => 'active']);
        DB::table('role_permissions')->insert(['role_id' => $role->id, 'permission_id' => $perm->id]);
        DB::table('user_roles')->insert(['user_id' => $user->id, 'role_id' => $role->id, 'created_at' => now()]);
        return $user;
    }

    private function product(): \App\Product
    {
        return \App\Product::create([
            'name'       => 'Prod ' . uniqid(),
            'nombre'     => 'Prod',
            'brand_id'   => 0,
            'codigo'     => 'C' . uniqid(),
            'img'        => '',
            'habilitado' => 1,
            'supply_id'  => 0,
            'status'     => 'active',
        ]);
    }

    private function chain(): SupermarketChain
    {
        return SupermarketChain::create([
            'name'   => 'Chain ' . uniqid(),
            'code'   => 'CH_' . uniqid(),
            'status' => 'active',
        ]);
    }

    private function scrapingSource(): \App\ScrapingSource
    {
        return \App\ScrapingSource::create([
            'code'  => 'SRC_' . uniqid(),
            'name'  => 'Source test',
            'type'  => 'supermarket',
        ]);
    }

    private function scrapingJob(\App\ScrapingSource $source): \App\ScrapingJob
    {
        return \App\ScrapingJob::create([
            'source_id' => $source->id,
            'job_type'  => 'prices',
            'status'    => 'completed',
        ]);
    }

    private function recipe(): Recipe
    {
        return Recipe::create([
            'nombre'      => 'Receta ' . uniqid(),
            'descripcion' => 'desc',
            'tiempo'      => '20 min',
            'img'         => '',
            'video'       => '',
            'porcion'     => '2',
            'calorias'    => 200,
        ]);
    }

    public function test_unauthenticated_request_is_rejected()
    {
        $this->getJson('/api/v1/admin/reports/users-active')->assertStatus(401);
        $this->getJson('/api/v1/admin/reports/products-pending-review')->assertStatus(401);
        $this->getJson('/api/v1/admin/reports/most-used-recipes')->assertStatus(401);
    }

    public function test_user_without_permission_is_forbidden()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user)->getJson('/api/v1/admin/reports/users-active')->assertStatus(403);
        $this->actingAs($user)->getJson('/api/v1/admin/reports/products-pending-review')->assertStatus(403);
        $this->actingAs($user)->getJson('/api/v1/admin/reports/most-used-recipes')->assertStatus(403);
    }

    public function test_users_active_returns_login_totals()
    {
        $admin = $this->userWithPermission('audit.read');
        $user  = factory(User::class)->create();

        LoginLog::create(['user_id' => $user->id, 'email' => $user->email, 'success' => true, 'ip_address' => '127.0.0.1', 'user_agent' => 'test']);
        LoginLog::create(['user_id' => $user->id, 'email' => $user->email, 'success' => true, 'ip_address' => '127.0.0.1', 'user_agent' => 'test']);
        LoginLog::create(['user_id' => $user->id, 'email' => $user->email, 'success' => false, 'ip_address' => '127.0.0.1', 'user_agent' => 'test']);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/reports/users-active')
            ->assertStatus(200);

        $this->assertEquals(2, $response->json('data.total_logins'));
        $this->assertEquals(1, $response->json('data.unique_users'));
        $this->assertArrayHasKey('by_day', $response->json('data'));
        $this->assertArrayHasKey('trace_id', $response->json());
    }

    public function test_users_active_filters_by_date_range()
    {
        $admin = $this->userWithPermission('audit.read');
        $user  = factory(User::class)->create();

        DB::table('login_logs')->insert([
            'user_id'    => $user->id,
            'email'      => $user->email,
            'success'    => true,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'created_at' => '2025-01-01 10:00:00',
        ]);
        LoginLog::create(['user_id' => $user->id, 'email' => $user->email, 'success' => true, 'ip_address' => '127.0.0.1', 'user_agent' => 'test']);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/reports/users-active?date_from=2026-01-01')
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('data.total_logins'));
    }

    public function test_products_pending_review_returns_totals_and_breakdown()
    {
        $admin = $this->userWithPermission('scraped_products.review');

        $source = $this->scrapingSource();
        $job    = $this->scrapingJob($source);

        ScrapedProductCandidate::create([
            'scraping_job_id' => $job->id,
            'source_id'       => $source->id,
            'raw_name'        => 'Leche entera',
            'review_status'   => 'pending',
        ]);
        ScrapedProductCandidate::create([
            'scraping_job_id' => $job->id,
            'source_id'       => $source->id,
            'raw_name'        => 'Aceite',
            'review_status'   => 'approved',
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/reports/products-pending-review')
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('data.total_pending'));
        $this->assertArrayHasKey('by_source', $response->json('data'));
        $this->assertArrayHasKey('oldest_pending', $response->json('data'));
    }

    public function test_recipes_pending_review_returns_count_and_breakdown()
    {
        $admin = $this->userWithPermission('catalog.manage');

        ImportedRecipeCandidate::create(['raw_title' => 'Tarta de manzana', 'source_site' => 'recetas.com', 'status' => 'pending', 'source_url' => 'http://a.com/1']);
        ImportedRecipeCandidate::create(['raw_title' => 'Pasta', 'source_site' => 'cocinemos.com', 'status' => 'reviewed', 'source_url' => 'http://b.com/2']);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/reports/recipes-pending-review')
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('data.total_pending'));
        $this->assertArrayHasKey('by_site', $response->json('data'));
    }

    public function test_most_used_recipes_returns_ranked_list()
    {
        $admin  = $this->userWithPermission('catalog.manage');
        $user   = factory(User::class)->create();
        $recipe = $this->recipe();

        RecipeCookLog::create(['user_id' => $user->id, 'recipe_id' => $recipe->id, 'servings' => 2, 'cooked_at' => now()]);
        RecipeCookLog::create(['user_id' => $user->id, 'recipe_id' => $recipe->id, 'servings' => 4, 'cooked_at' => now()]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/reports/most-used-recipes')
            ->assertStatus(200);

        $this->assertEquals(2, $response->json('data.total_cook_events'));
        $this->assertEquals(6, $response->json('data.total_servings'));
        $this->assertCount(1, $response->json('data.top_recipes'));
        $this->assertEquals(2, $response->json('data.top_recipes.0.times_cooked'));
    }

    public function test_price_variations_returns_empty_when_insufficient_history()
    {
        $admin = $this->userWithPermission('catalog.manage');

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/reports/price-variations')
            ->assertStatus(200);

        $this->assertEquals(0, $response->json('data.total'));
        $this->assertArrayHasKey('variations', $response->json('data'));
    }

    public function test_supermarket_price_status_returns_chain_summary()
    {
        $admin   = $this->userWithPermission('catalog.manage');
        $chain   = $this->chain();
        $product = $this->product();

        DB::table('supermarket_products')->insert([
            'product_id'           => $product->id,
            'supermarket_chain_id' => $chain->id,
            'status'               => 'active',
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/reports/supermarket-price-status')
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('data.total_chains'));
        $chainData = $response->json('data.chains.0');
        $this->assertEquals($chain->name, $chainData['chain_name']);
        $this->assertEquals(1, $chainData['total_products']);
        $this->assertEquals(1, $chainData['without_price']);
    }

    public function test_price_variations_detects_actual_change()
    {
        $admin   = $this->userWithPermission('catalog.manage');
        $chain   = $this->chain();
        $product = $this->product();

        $spId = DB::table('supermarket_products')->insertGetId([
            'product_id'           => $product->id,
            'supermarket_chain_id' => $chain->id,
            'status'               => 'active',
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        DB::table('supermarket_product_prices')->insert([
            'supermarket_product_id' => $spId,
            'price'                  => 100.00,
            'currency'               => 'ARS',
            'scraped_at'             => now()->subDays(2),
            'created_at'             => now()->subDays(2),
        ]);
        DB::table('supermarket_product_prices')->insert([
            'supermarket_product_id' => $spId,
            'price'                  => 120.00,
            'currency'               => 'ARS',
            'scraped_at'             => now(),
            'created_at'             => now(),
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/reports/price-variations')
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('data.total'));
        $variation = $response->json('data.variations.0');
        $this->assertEquals(120.0, (float) $variation['current_price']);
        $this->assertEquals(100.0, (float) $variation['previous_price']);
        $this->assertEquals(20.0,  (float) $variation['variation']);
    }
}
