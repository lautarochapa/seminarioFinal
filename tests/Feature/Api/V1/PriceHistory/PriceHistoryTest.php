<?php

namespace Tests\Feature\Api\V1\PriceHistory;

use App\City;
use App\Product;
use App\SupermarketBranch;
use App\SupermarketChain;
use App\SupermarketProduct;
use App\SupermarketProductPrice;
use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PriceHistoryTest extends TestCase
{
    use RefreshDatabase;

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

    private function chain(array $data = [])
    {
        $name = $data['name'] ?? 'Chain ' . uniqid();
        return SupermarketChain::create(array_merge([
            'name'   => $name,
            'code'   => Str::slug($name, '_') . '_' . Str::random(4),
            'status' => 'active',
        ], $data));
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

    public function test_sin_autenticacion_retorna_401()
    {
        $product = $this->product();

        $this->getJson("/api/v1/products/{$product->id}/price-history")
            ->assertStatus(401);
    }

    public function test_producto_inexistente_retorna_404()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user)
            ->getJson('/api/v1/products/999999/price-history')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'PRODUCT_NOT_FOUND');
    }

    public function test_historial_devuelve_precios_paginados_y_ordenados()
    {
        $user    = factory(User::class)->create();
        $chain   = $this->chain();
        $city    = $this->city();
        $branch  = $this->branch($chain, $city);
        $product = $this->product();
        $sp      = $this->supermarketProduct($product, $branch);

        $p1 = $this->addPrice($sp, 100.00, ['scraped_at' => Carbon::now()->subDays(3)]);
        $p2 = $this->addPrice($sp, 120.00, ['scraped_at' => Carbon::now()->subDays(1)]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/products/{$product->id}/price-history");

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta', 'links', 'trace_id']);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals($p2->id, $data[0]['id']);
        $this->assertEquals($p1->id, $data[1]['id']);
        $this->assertArrayHasKey('chain', $data[0]);
        $this->assertArrayHasKey('branch', $data[0]);
        $this->assertEquals($chain->id, $data[0]['chain']['id']);
    }

    public function test_filtro_por_cadena()
    {
        $user     = factory(User::class)->create();
        $chainA   = $this->chain();
        $chainB   = $this->chain();
        $city     = $this->city();
        $branchA  = $this->branch($chainA, $city);
        $branchB  = $this->branch($chainB, $city);
        $product  = $this->product();
        $spA      = $this->supermarketProduct($product, $branchA);
        $spB      = $this->supermarketProduct($product, $branchB);

        $priceA = $this->addPrice($spA, 100.00);
        $this->addPrice($spB, 150.00);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/products/{$product->id}/price-history?chain_id={$chainA->id}");

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEquals([$priceA->id], $ids);
    }

    public function test_filtro_por_rango_de_fechas()
    {
        $user    = factory(User::class)->create();
        $chain   = $this->chain();
        $city    = $this->city();
        $branch  = $this->branch($chain, $city);
        $product = $this->product();
        $sp      = $this->supermarketProduct($product, $branch);

        $viejo = $this->addPrice($sp, 90.00, ['scraped_at' => Carbon::now()->subDays(30)]);
        $reciente = $this->addPrice($sp, 110.00, ['scraped_at' => Carbon::now()]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/products/' . $product->id . '/price-history?date_from=' . Carbon::now()->subDays(5)->toDateString());

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($reciente->id, $ids);
        $this->assertNotContains($viejo->id, $ids);
    }

    public function test_no_expone_datos_de_otro_producto()
    {
        $user     = factory(User::class)->create();
        $chain    = $this->chain();
        $city     = $this->city();
        $branch   = $this->branch($chain, $city);
        $product1 = $this->product();
        $product2 = $this->product();
        $sp1      = $this->supermarketProduct($product1, $branch);
        $sp2      = $this->supermarketProduct($product2, $branch);

        $this->addPrice($sp1, 100.00);
        $priceOther = $this->addPrice($sp2, 200.00);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/products/{$product1->id}/price-history");

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($priceOther->id, $ids);
    }
}
