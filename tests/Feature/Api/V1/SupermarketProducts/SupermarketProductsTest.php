<?php

namespace Tests\Feature\Api\V1\SupermarketProducts;

use App\AuditLog;
use App\City;
use App\Product;
use App\Role;
use App\SupermarketBranch;
use App\SupermarketChain;
use App\SupermarketProduct;
use App\SupermarketProductPrice;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupermarketProductsTest extends TestCase
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
            'product_id'           => $product->id,
            'supermarket_chain_id' => $branch->supermarket_chain_id,
            'supermarket_branch_id'=> $branch->id,
            'external_sku'         => 'SKU-' . uniqid(),
            'status'               => 'active',
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
            'created_at'             => now(),
        ], $data));
    }

    // GET admin sin autenticacion retorna 401
    public function test_admin_sin_autenticacion_retorna_401()
    {
        $this->getJson('/api/v1/admin/supermarket-products')->assertStatus(401);
    }

    // GET admin sin permiso retorna 403
    public function test_admin_sin_permiso_retorna_403()
    {
        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/admin/supermarket-products')
            ->assertStatus(403);
    }

    // alta exitosa retorna 201 con datos del mapeo
    public function test_alta_exitosa_retorna_201()
    {
        $admin   = $this->admin();
        $product = $this->product();
        $chain   = $this->chain();
        $city    = $this->city();
        $branch  = $this->branch($chain, $city);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/supermarket-products', [
                'product_id'            => $product->id,
                'supermarket_branch_id' => $branch->id,
                'external_sku'          => 'SKU-TEST-001',
                'source_url'            => 'https://supermercado.com/producto/001',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.external_sku', 'SKU-TEST-001')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonStructure(['data', 'trace_id']);

        $this->assertDatabaseHas('supermarket_products', [
            'external_sku' => 'SKU-TEST-001',
            'status'       => 'active',
        ]);
    }

    // producto o sucursal inexistente retorna 422
    public function test_producto_o_sucursal_invalida_retorna_422()
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/supermarket-products', [
                'product_id'            => 9999,
                'supermarket_branch_id' => 9999,
            ])
            ->assertStatus(422);
    }

    // mapeo duplicado por producto, sucursal y sku retorna 409
    public function test_mapeo_duplicado_retorna_409()
    {
        $admin   = $this->admin();
        $product = $this->product();
        $chain   = $this->chain();
        $city    = $this->city();
        $branch  = $this->branch($chain, $city);

        $this->supermarketProduct($product, $branch, ['external_sku' => 'SKU-DUPE']);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/supermarket-products', [
                'product_id'            => $product->id,
                'supermarket_branch_id' => $branch->id,
                'external_sku'          => 'SKU-DUPE',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SUPERMARKET_PRODUCT_ALREADY_EXISTS');
    }

    // actualizacion parcial retorna 200
    public function test_actualizacion_parcial_retorna_200()
    {
        $admin   = $this->admin();
        $product = $this->product();
        $chain   = $this->chain();
        $city    = $this->city();
        $branch  = $this->branch($chain, $city);
        $sp      = $this->supermarketProduct($product, $branch, ['external_sku' => 'SKU-ORIG']);

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/supermarket-products/' . $sp->id, [
                'source_url' => 'https://supermercado.com/actualizado',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.external_sku', 'SKU-ORIG')
            ->assertJsonPath('data.source_url', 'https://supermercado.com/actualizado');
    }

    // baja logica y restore funcionan
    public function test_baja_logica_y_restore()
    {
        $admin   = $this->admin();
        $product = $this->product();
        $chain   = $this->chain();
        $city    = $this->city();
        $branch  = $this->branch($chain, $city);
        $sp      = $this->supermarketProduct($product, $branch);

        $this->actingAs($admin)
            ->deleteJson('/api/v1/admin/supermarket-products/' . $sp->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('supermarket_products', ['id' => $sp->id, 'status' => 'inactive']);

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/supermarket-products/' . $sp->id . '/restore')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('supermarket_products', ['id' => $sp->id, 'status' => 'active']);
    }

    // listado de productos por sucursal devuelve solo activos con precio
    public function test_listado_productos_por_sucursal()
    {
        $product1 = $this->product(['name' => 'Leche Entera']);
        $product2 = $this->product(['name' => 'Yogur Natural']);
        $chain    = $this->chain();
        $city     = $this->city();
        $branch   = $this->branch($chain, $city);

        $sp1 = $this->supermarketProduct($product1, $branch);
        $sp2 = $this->supermarketProduct($product2, $branch);
        $this->addPrice($sp1, 150.00);

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/supermarket-branches/' . $branch->id . '/products');

        $response->assertStatus(200);

        $names = collect($response->json('data'))->pluck('product.name')->all();
        $this->assertContains('Leche Entera', $names);
        $this->assertContains('Yogur Natural', $names);
    }

    // precios por producto ordenados de menor a mayor
    public function test_precios_del_producto_ordenados()
    {
        $product = $this->product(['name' => 'Arroz Largo']);
        $chain   = $this->chain();
        $city    = $this->city();
        $branch1 = $this->branch($chain, $city);
        $branch2 = $this->branch($chain, $city);

        $sp1 = $this->supermarketProduct($product, $branch1);
        $sp2 = $this->supermarketProduct($product, $branch2);

        $this->addPrice($sp1, 250.00);
        $this->addPrice($sp2, 180.00);

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/products/' . $product->id . '/supermarket-prices');

        $response->assertStatus(200);

        $prices = collect($response->json('data'))->pluck('current_price.price')->map(fn($p) => (float) $p)->all();
        $this->assertCount(2, $prices);
        $this->assertLessThanOrEqual($prices[1], $prices[0]);
    }

    // mejor precio devuelve el mas barato con datos completos
    public function test_mejor_precio_correcto()
    {
        $product = $this->product(['name' => 'Aceite de Girasol']);
        $chain   = $this->chain();
        $city    = $this->city();
        $branch1 = $this->branch($chain, $city, ['name' => 'Sucursal Barata']);
        $branch2 = $this->branch($chain, $city, ['name' => 'Sucursal Cara']);

        $sp1 = $this->supermarketProduct($product, $branch1);
        $sp2 = $this->supermarketProduct($product, $branch2);

        $this->addPrice($sp1, 800.00);
        $this->addPrice($sp2, 1200.00);

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/products/' . $product->id . '/best-price');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['current_price', 'branch']]);

        $price = (float) $response->json('data.current_price.price');
        $this->assertEquals(800.00, $price);
    }

    // registros inactivos no aparecen en precios
    public function test_registros_inactivos_ignorados()
    {
        $product = $this->product(['name' => 'Manteca']);
        $chain   = $this->chain();
        $city    = $this->city();
        $branch1 = $this->branch($chain, $city);
        $branch2 = $this->branch($chain, $city);

        $spActive   = $this->supermarketProduct($product, $branch1, ['status' => 'active']);
        $spInactive = $this->supermarketProduct($product, $branch2, ['status' => 'inactive']);

        $this->addPrice($spActive, 300.00);
        $this->addPrice($spInactive, 100.00);

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/products/' . $product->id . '/supermarket-prices');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($spActive->id, $ids);
        $this->assertNotContains($spInactive->id, $ids);
    }

    // auditoria registrada en alta
    public function test_auditoria_registrada()
    {
        $admin   = $this->admin();
        $product = $this->product();
        $chain   = $this->chain();
        $city    = $this->city();
        $branch  = $this->branch($chain, $city);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/supermarket-products', [
                'product_id'            => $product->id,
                'supermarket_branch_id' => $branch->id,
                'external_sku'          => 'SKU-AUDIT',
            ])
            ->assertStatus(201);

        $this->assertTrue(
            AuditLog::where('entity_name', 'supermarket_products')
                ->where('action', 'supermarket_product.created')
                ->exists()
        );
    }
}
