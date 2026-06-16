<?php

namespace Tests\Feature\Api\V1\SupermarketPrices;

use App\AuditLog;
use App\City;
use App\Product;
use App\Role;
use App\SupermarketBranch;
use App\SupermarketChain;
use App\SupermarketProduct;
use App\SupermarketProductPrice;
use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupermarketPricesTest extends TestCase
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

    private function product(array $data = [])
    {
        $name = $data['name'] ?? 'Producto ' . uniqid();
        return Product::create(array_merge([
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
        ], $data));
    }

    private function chain()
    {
        $name = 'Chain ' . uniqid();
        return SupermarketChain::create([
            'name'   => $name,
            'code'   => Str::slug($name, '_') . '_' . Str::random(4),
            'status' => 'active',
        ]);
    }

    private function city()
    {
        return City::create([
            'name'     => 'City ' . uniqid(),
            'province' => 'Prov Test',
            'country'  => 'Argentina',
            'status'   => 'active',
        ]);
    }

    private function branch($chain, $city, array $data = [])
    {
        return SupermarketBranch::create(array_merge([
            'supermarket_chain_id' => $chain->id,
            'city_id'              => $city->id,
            'name'                 => 'Sucursal ' . uniqid(),
            'address'              => 'Calle 123',
            'status'               => 'active',
        ], $data));
    }

    private function supermarketProduct($product, $branch, array $data = [])
    {
        return SupermarketProduct::create(array_merge([
            'product_id'            => $product->id,
            'supermarket_chain_id'  => $branch->supermarket_chain_id,
            'supermarket_branch_id' => $branch->id,
            'external_sku'          => 'SKU-' . uniqid(),
            'status'                => 'active',
        ], $data));
    }

    private function addPrice($sp, float $amount, array $data = [])
    {
        return SupermarketProductPrice::create(array_merge([
            'supermarket_product_id' => $sp->id,
            'price'                  => $amount,
            'currency'               => 'ARS',
            'status'                 => 'active',
            'scraped_at'             => now(),
        ], $data));
    }

    public function test_historial_sin_autenticacion_retorna_401()
    {
        $chain  = $this->chain();
        $city   = $this->city();
        $branch = $this->branch($chain, $city);
        $prod   = $this->product();
        $sp     = $this->supermarketProduct($prod, $branch);

        $response = $this->getJson("/api/v1/admin/supermarket-products/{$sp->id}/prices");

        $response->assertStatus(401);
    }

    public function test_historial_sin_permiso_retorna_403()
    {
        $user   = $this->regularUser();
        $chain  = $this->chain();
        $city   = $this->city();
        $branch = $this->branch($chain, $city);
        $prod   = $this->product();
        $sp     = $this->supermarketProduct($prod, $branch);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/admin/supermarket-products/{$sp->id}/prices");

        $response->assertStatus(403);
    }

    public function test_historial_devuelve_todos_ordenados()
    {
        $admin  = $this->admin();
        $chain  = $this->chain();
        $city   = $this->city();
        $branch = $this->branch($chain, $city);
        $prod   = $this->product();
        $sp     = $this->supermarketProduct($prod, $branch);

        $p1 = $this->addPrice($sp, 100.00, ['scraped_at' => Carbon::now()->subDays(3)]);
        $p2 = $this->addPrice($sp, 200.00, ['scraped_at' => Carbon::now()->subDays(1)]);
        $p3 = $this->addPrice($sp, 300.00, ['scraped_at' => Carbon::now()]);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/admin/supermarket-products/{$sp->id}/prices");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(3, $data);
        $this->assertEquals($p3->id, $data[0]['id']);
        $this->assertEquals($p2->id, $data[1]['id']);
        $this->assertEquals($p1->id, $data[2]['id']);
        $this->assertArrayHasKey('price', $data[0]);
        $this->assertArrayHasKey('currency', $data[0]);
        $this->assertArrayHasKey('source', $data[0]);
        $this->assertArrayHasKey('status', $data[0]);
    }

    public function test_carga_manual_retorna_201()
    {
        $admin  = $this->admin();
        $chain  = $this->chain();
        $city   = $this->city();
        $branch = $this->branch($chain, $city);
        $prod   = $this->product();
        $sp     = $this->supermarketProduct($prod, $branch);

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/admin/supermarket-products/{$sp->id}/prices", [
                'price'    => 2450.50,
                'currency' => 'ARS',
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertEquals('2450.50', $data['price']);
        $this->assertEquals('ARS', $data['currency']);
        $this->assertEquals('manual', $data['source']);
        $this->assertTrue($data['is_current']);
    }

    public function test_precio_invalido_retorna_422()
    {
        $admin  = $this->admin();
        $chain  = $this->chain();
        $city   = $this->city();
        $branch = $this->branch($chain, $city);
        $prod   = $this->product();
        $sp     = $this->supermarketProduct($prod, $branch);

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/admin/supermarket-products/{$sp->id}/prices", [
                'price'    => 0,
                'currency' => 'ARS',
            ]);

        $response->assertStatus(422);
    }

    public function test_currency_invalida_retorna_422()
    {
        $admin  = $this->admin();
        $chain  = $this->chain();
        $city   = $this->city();
        $branch = $this->branch($chain, $city);
        $prod   = $this->product();
        $sp     = $this->supermarketProduct($prod, $branch);

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/admin/supermarket-products/{$sp->id}/prices", [
                'price'    => 100.00,
                'currency' => 'INVALID',
            ]);

        $response->assertStatus(422);
    }

    public function test_mapeo_inactivo_retorna_404()
    {
        $admin  = $this->admin();
        $chain  = $this->chain();
        $city   = $this->city();
        $branch = $this->branch($chain, $city);
        $prod   = $this->product();
        $sp     = $this->supermarketProduct($prod, $branch, ['status' => 'inactive']);

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/admin/supermarket-products/{$sp->id}/prices", [
                'price'    => 100.00,
                'currency' => 'ARS',
            ]);

        $response->assertStatus(404);
    }

    public function test_origen_asignado_por_servidor()
    {
        $admin  = $this->admin();
        $chain  = $this->chain();
        $city   = $this->city();
        $branch = $this->branch($chain, $city);
        $prod   = $this->product();
        $sp     = $this->supermarketProduct($prod, $branch);

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/admin/supermarket-products/{$sp->id}/prices", [
                'price'    => 500.00,
                'currency' => 'ARS',
                'source'   => 'scraper',
            ]);

        $response->assertStatus(201);
        $this->assertEquals('manual', $response->json('data.source'));
    }

    public function test_historial_previo_cerrado_y_nuevo_activo()
    {
        $admin  = $this->admin();
        $chain  = $this->chain();
        $city   = $this->city();
        $branch = $this->branch($chain, $city);
        $prod   = $this->product();
        $sp     = $this->supermarketProduct($prod, $branch);

        $oldPrice = $this->addPrice($sp, 100.00);
        $this->assertNull($oldPrice->valid_to);

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/admin/supermarket-products/{$sp->id}/prices", [
                'price'    => 200.00,
                'currency' => 'ARS',
            ]);

        $response->assertStatus(201);

        $oldPrice->refresh();
        $this->assertNotNull($oldPrice->valid_to);

        $this->assertDatabaseCount('supermarket_product_prices', 2);

        $newId = $response->json('data.id');
        $newPrice = SupermarketProductPrice::find($newId);
        $this->assertNotNull($newPrice);
        $this->assertEquals('manual', $newPrice->source);
        $this->assertEquals('active', $newPrice->status);
        $this->assertNull($newPrice->valid_to);
    }

    public function test_auditoria_registrada()
    {
        $admin  = $this->admin();
        $chain  = $this->chain();
        $city   = $this->city();
        $branch = $this->branch($chain, $city);
        $prod   = $this->product();
        $sp     = $this->supermarketProduct($prod, $branch);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/supermarket-products/{$sp->id}/prices", [
                'price'    => 999.99,
                'currency' => 'ARS',
            ]);

        $log = AuditLog::where('action', 'price.added')
            ->where('entity_name', 'supermarket_product_prices')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($admin->id, $log->user_id);
        $newValues = is_array($log->new_values) ? $log->new_values : json_decode($log->new_values, true);
        $this->assertEquals($sp->id, $newValues['supermarket_product_id']);
    }
}
