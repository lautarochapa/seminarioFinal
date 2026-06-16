<?php

namespace Tests\Feature\Api\V1\PriceRefreshRequests;

use App\AuditLog;
use App\Brand;
use App\Ingredient;
use App\PriceRefreshRequest;
use App\Product;
use App\ProductCategory;
use App\Role;
use App\ScrapingJob;
use App\ScrapingSource;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PriceRefreshRequestsTest extends TestCase
{
    use RefreshDatabase;

    private function admin()
    {
        $user = factory(User::class)->create();
        $role = Role::where('code', 'super_admin')->first();

        DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
        ]);

        return $user;
    }

    private function unit(array $data = [])
    {
        return UnitMeasure::create(array_merge([
            'code' => 'u_'.uniqid(),
            'name' => 'Unidad',
            'type' => 'mass',
            'symbol' => 'u',
            'status' => 'active',
        ], $data));
    }

    private function brand(array $data = [])
    {
        $name = $data['name'] ?? 'Marca '.uniqid();

        return Brand::create(array_merge([
            'nombre' => $name,
            'name' => $name,
            'normalized_name' => strtolower($name),
            'status' => 'active',
            'padre' => 0,
        ], $data));
    }

    private function category(array $data = [])
    {
        return ProductCategory::create(array_merge([
            'name' => 'Categoria '.uniqid(),
            'description' => null,
            'parent_id' => null,
            'status' => 'active',
        ], $data));
    }

    private function ingredient(array $data = [])
    {
        $unit = $data['unit'] ?? $this->unit(['code' => 'g_'.uniqid(), 'name' => 'Gramo', 'symbol' => 'g']);
        $name = $data['name'] ?? 'Ingrediente '.uniqid();

        return Ingredient::create(array_merge([
            'name' => $name,
            'normalized_name' => strtolower($name),
            'category_id' => null,
            'base_unit_id' => $unit->id,
            'description' => null,
            'is_generic' => true,
            'is_preparation' => false,
            'is_supplement' => false,
            'status' => 'active',
        ], Arr::except($data, ['unit'])));
    }

    private function product(array $data = [])
    {
        $brand = $data['brand'] ?? $this->brand();
        $category = $data['category'] ?? $this->category();
        $ingredient = $data['ingredient'] ?? $this->ingredient();
        $unit = $data['unit'] ?? $this->unit();
        $name = $data['name'] ?? 'Producto '.uniqid();

        return Product::create(array_merge([
            'nombre' => $name,
            'brand_id' => $brand->id,
            'codigo' => $data['barcode'] ?? 'BC'.uniqid(),
            'img' => 'product.png',
            'habilitado' => 1,
            'supply_id' => 0,
            'name' => $name,
            'normalized_name' => strtolower($name),
            'category_id' => $category->id,
            'ingredient_id' => $ingredient->id,
            'default_unit_id' => $unit->id,
            'net_quantity' => '1.0000',
            'description' => null,
            'is_verified' => false,
            'is_active' => true,
            'status' => 'active',
        ], Arr::except($data, ['brand', 'category', 'ingredient', 'unit', 'barcode'])));
    }

    private function source(array $data = [])
    {
        return ScrapingSource::create(array_merge([
            'code' => 'carrefour_'.uniqid(),
            'name' => 'Carrefour',
            'type' => 'web_scraper',
            'base_url' => 'https://www.carrefour.com.ar',
            'is_active' => true,
            'status' => 'active',
        ], $data));
    }

    public function test_auth_required()
    {
        $this->postJson('/api/v1/products/1/request-price-refresh')->assertStatus(401);
        $this->getJson('/api/v1/users/me/price-refresh-requests')->assertStatus(401);
        $this->getJson('/api/v1/admin/price-refresh-requests')->assertStatus(401);
        $this->postJson('/api/v1/admin/price-refresh-requests/1/process')->assertStatus(401);
    }

    public function test_user_creates_pending_request()
    {
        $user = factory(User::class)->create();
        $product = $this->product();

        $response = $this->actingAs($user)->postJson('/api/v1/products/'.$product->id.'/request-price-refresh', [
            'reason' => 'Precio desactualizado',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('price_refresh_requests', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 'pending',
        ]);
        $this->assertTrue(AuditLog::where('entity_name', 'price_refresh_requests')->where('action', 'price-refresh-request.created')->exists());
    }

    public function test_duplicate_pending_request_rejected()
    {
        $user = factory(User::class)->create();
        $product = $this->product();
        PriceRefreshRequest::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $this->actingAs($user)->postJson('/api/v1/products/'.$product->id.'/request-price-refresh')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PRICE_REFRESH_REQUEST_ALREADY_PENDING');
    }

    public function test_invalid_product_rejected()
    {
        $user = factory(User::class)->create();
        $product = $this->product(['status' => 'inactive', 'is_active' => false, 'habilitado' => 0]);

        $this->actingAs($user)->postJson('/api/v1/products/'.$product->id.'/request-price-refresh')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'PRODUCT_NOT_FOUND');
    }

    public function test_user_lists_only_own_requests()
    {
        $user = factory(User::class)->create();
        $other = factory(User::class)->create();
        $own = PriceRefreshRequest::create([
            'user_id' => $user->id,
            'product_id' => $this->product(['name' => 'Propio'])->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);
        PriceRefreshRequest::create([
            'user_id' => $other->id,
            'product_id' => $this->product(['name' => 'Ajeno'])->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/users/me/price-refresh-requests');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_admin_requires_permission()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user)->getJson('/api/v1/admin/price-refresh-requests')
            ->assertStatus(403);
    }

    public function test_admin_lists_with_filters()
    {
        $admin = $this->admin();
        $product = $this->product();
        $wanted = PriceRefreshRequest::create([
            'user_id' => factory(User::class)->create()->id,
            'product_id' => $product->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);
        PriceRefreshRequest::create([
            'user_id' => factory(User::class)->create()->id,
            'product_id' => $this->product()->id,
            'status' => 'queued',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/price-refresh-requests?status=pending&product_id='.$product->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $wanted->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_admin_processes_pending_request_and_creates_scraping_job()
    {
        Queue::fake();
        $admin = $this->admin();
        $source = $this->source(['code' => 'carrefour_refresh']);
        $request = PriceRefreshRequest::create([
            'user_id' => factory(User::class)->create()->id,
            'product_id' => $this->product()->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/price-refresh-requests/'.$request->id.'/process');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'queued');

        $this->assertDatabaseHas('scraping_jobs', [
            'source_id' => $source->id,
            'job_type' => 'price_refresh',
            'requested_by' => $admin->id,
            'status' => 'pending',
        ]);
        $this->assertTrue(ScrapingJob::where('job_type', 'price_refresh')->whereJsonContains('parameters_json->product_id', $request->product_id)->exists());
        $this->assertTrue(AuditLog::where('entity_name', 'price_refresh_requests')->where('action', 'price-refresh-request.processed')->exists());
    }

    public function test_double_processing_rejected()
    {
        $request = PriceRefreshRequest::create([
            'user_id' => factory(User::class)->create()->id,
            'product_id' => $this->product()->id,
            'status' => 'queued',
            'requested_at' => now(),
            'processed_at' => now(),
        ]);

        $this->actingAs($this->admin())->postJson('/api/v1/admin/price-refresh-requests/'.$request->id.'/process')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PRICE_REFRESH_REQUEST_ALREADY_PROCESSED');
    }

    public function test_routes_registered_in_api()
    {
        $this->assertNotNull(Route::getRoutes()->match(request()->create('/api/v1/products/1/request-price-refresh', 'POST')));
        $this->assertNotNull(Route::getRoutes()->match(request()->create('/api/v1/users/me/price-refresh-requests', 'GET')));
        $this->assertNotNull(Route::getRoutes()->match(request()->create('/api/v1/admin/price-refresh-requests', 'GET')));
        $this->assertNotNull(Route::getRoutes()->match(request()->create('/api/v1/admin/price-refresh-requests/1/process', 'POST')));
    }
}
