<?php

namespace Tests\Feature\Api\V1\Products;

use App\AuditLog;
use App\Brand;
use App\FamilyGroup;
use App\FamilyGroupMember;
use App\Ingredient;
use App\Nutrient;
use App\Product;
use App\ProductBarcode;
use App\ProductCategory;
use App\ProductImage;
use App\ProductNutrient;
use App\Role;
use App\StockItem;
use App\SupermarketProduct;
use App\SupermarketProductPrice;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductsTest extends TestCase
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

    private function groupFor(User $user): FamilyGroup
    {
        $group = factory(FamilyGroup::class)->create(['owner_user_id' => $user->id, 'status' => 'active']);
        factory(FamilyGroupMember::class)->create([
            'family_group_id' => $group->id,
            'user_id' => $user->id,
            'role_in_group' => 'owner',
            'status' => 'active',
        ]);

        return $group;
    }

    public function test_admin_autenticacion_y_permiso()
    {
        $this->getJson('/api/v1/admin/products')->assertStatus(401);

        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/admin/products')
            ->assertStatus(403);
    }

    public function test_alta_exitosa()
    {
        $admin = $this->admin();
        $brand = $this->brand(['name' => 'Gallo', 'normalized_name' => 'gallo']);
        $category = $this->category(['name' => 'Almacen']);
        $unit = $this->unit(['code' => 'kg', 'name' => 'Kilo', 'symbol' => 'kg']);
        $ingredient = $this->ingredient(['name' => 'Arroz']);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/products', [
            'name' => '  Arroz Gallo Oro 1 kg  ',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'ingredient_id' => $ingredient->id,
            'barcode' => '7790001000011',
            'net_quantity' => 1,
            'default_unit_id' => $unit->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Arroz Gallo Oro 1 kg')
            ->assertJsonPath('data.normalized_name', 'arroz gallo oro 1 kg')
            ->assertJsonPath('data.barcode', '7790001000011');
    }

    public function test_relacion_invalida_rechazada()
    {
        $inactiveBrand = $this->brand(['status' => 'inactive']);

        $this->actingAs($this->admin())->postJson('/api/v1/admin/products', [
            'name' => 'Producto',
            'brand_id' => $inactiveBrand->id,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'PRODUCT_BRAND_INVALID');
    }

    public function test_barcode_duplicado()
    {
        $product = $this->product(['barcode' => '7790001000012']);
        ProductBarcode::create(['product_id' => $product->id, 'barcode' => '7790001000012', 'type' => null, 'status' => 'active']);

        $this->actingAs($this->admin())->postJson('/api/v1/admin/products', [
            'name' => 'Duplicado',
            'barcode' => '7790001000012',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'PRODUCT_BARCODE_ALREADY_EXISTS');
    }

    public function test_actualizacion_parcial()
    {
        $product = $this->product(['name' => 'Old', 'description' => 'Keep']);

        $this->actingAs($this->admin())->patchJson('/api/v1/admin/products/'.$product->id, [
            'name' => 'New',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'New')
            ->assertJsonPath('data.description', 'Keep');
    }

    public function test_baja_y_restore()
    {
        $admin = $this->admin();
        $product = $this->product(['name' => 'Baja']);

        $this->actingAs($admin)->deleteJson('/api/v1/admin/products/'.$product->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');

        $this->assertSoftDeleted('products', ['id' => $product->id]);

        $this->actingAs($admin)->patchJson('/api/v1/admin/products/'.$product->id.'/restore')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_catalogo_solo_activos_busqueda_y_detalle()
    {
        $brand = $this->brand(['name' => 'Gallo', 'normalized_name' => 'gallo']);
        $active = $this->product(['name' => 'Arroz Gallo Oro', 'brand' => $brand]);
        $this->product(['name' => 'Oculto', 'status' => 'inactive', 'is_active' => false]);

        $list = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/products?search=gallo');

        $list->assertStatus(200)
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonMissing(['name' => 'Oculto']);

        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/products/'.$active->id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $active->id);
    }

    public function test_barcode_conocido_con_stock_del_grupo_incluye_stock()
    {
        $user = factory(User::class)->create();
        $group = $this->groupFor($user);
        $unit = $this->unit(['code' => 'scan_u_'.uniqid(), 'symbol' => 'u']);
        $product = $this->product(['unit' => $unit, 'barcode' => '7791234500001']);
        ProductBarcode::create(['product_id' => $product->id, 'barcode' => '7791234500001', 'type' => 'ean13', 'status' => 'active']);
        StockItem::create([
            'family_group_id' => $group->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_id' => $unit->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/products/barcode/7791234500001?family_group_id='.$group->id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.stock_summary.in_stock', true)
            ->assertJsonPath('data.stock_items.0.quantity', '2.0000');
    }

    public function test_barcode_conocido_sin_stock_del_grupo_sigue_devolviendo_producto()
    {
        $user = factory(User::class)->create();
        $group = $this->groupFor($user);
        $product = $this->product(['barcode' => '7791234500002']);
        ProductBarcode::create(['product_id' => $product->id, 'barcode' => '7791234500002', 'type' => 'ean13', 'status' => 'active']);

        $this->actingAs($user)
            ->getJson('/api/v1/products/barcode/7791234500002?family_group_id='.$group->id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.stock_summary.in_stock', false)
            ->assertJsonPath('data.stock_items', []);
    }

    public function test_barcode_desconocido_retorna_404_para_flujo_manual()
    {
        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/products/barcode/7791234599999')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'PRODUCT_NOT_FOUND');
    }

    public function test_nutricion()
    {
        $product = $this->product();
        $unit = $this->unit(['code' => 'kcal', 'name' => 'Kilocaloria', 'symbol' => 'kcal']);
        $nutrient = Nutrient::create([
            'code' => 'energy',
            'name' => 'Energia',
            'unit_id' => $unit->id,
            'category' => null,
            'is_macro' => false,
            'status' => 'active',
        ]);
        ProductNutrient::create([
            'product_id' => $product->id,
            'nutrient_id' => $nutrient->id,
            'amount_per_100g' => 350,
            'status' => 'active',
        ]);

        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/products/'.$product->id.'/nutrition')
            ->assertStatus(200)
            ->assertJsonPath('data.0.nutrient.code', 'energy');
    }

    public function test_precios()
    {
        $product = $this->product();
        $chainId = DB::table('supermarket_chains')->insertGetId([
            'name' => 'Market',
            'code' => 'market_'.uniqid(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $supermarketProduct = SupermarketProduct::create([
            'product_id' => $product->id,
            'supermarket_chain_id' => $chainId,
            'source_name' => 'Market',
            'status' => 'active',
        ]);
        SupermarketProductPrice::create([
            'supermarket_product_id' => $supermarketProduct->id,
            'price' => 100,
            'currency' => 'ARS',
            'scraped_at' => now(),
            'status' => 'active',
        ]);

        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/products/'.$product->id.'/prices')
            ->assertStatus(200)
            ->assertJsonPath('data.0.price', '100.00');
    }

    public function test_alternativas()
    {
        $ingredient = $this->ingredient(['name' => 'Arroz']);
        $product = $this->product(['name' => 'Arroz A', 'ingredient' => $ingredient]);
        $alternative = $this->product(['name' => 'Arroz B', 'ingredient' => $ingredient]);

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/products/'.$product->id.'/alternatives')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $alternative->id);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($product->id, $ids);
    }

    public function test_producto_inexistente()
    {
        $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/products/999999')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'PRODUCT_NOT_FOUND');
    }

    public function test_auditoria()
    {
        $admin = $this->admin();
        $product = $this->product();

        $this->actingAs($admin)->patchJson('/api/v1/admin/products/'.$product->id, [
            'description' => 'Audited',
        ])->assertStatus(200);

        $this->assertTrue(AuditLog::where('entity_name', 'products')->where('action', 'product.updated')->exists());
    }

    public function test_rutas_registradas_en_api()
    {
        $this->assertNotNull(Route::getRoutes()->match(request()->create('/api/v1/admin/products', 'GET')));
        $this->assertNotNull(Route::getRoutes()->match(request()->create('/api/v1/products', 'GET')));
        $this->assertNotNull(Route::getRoutes()->match(request()->create('/api/v1/products/1/nutrition', 'GET')));
        $this->assertNotNull(Route::getRoutes()->match(request()->create('/api/v1/products/1/prices', 'GET')));
        $this->assertNotNull(Route::getRoutes()->match(request()->create('/api/v1/products/1/alternatives', 'GET')));
    }

    public function test_imagen_carga_url_valida()
    {
        Storage::fake('public');
        $admin   = $this->admin();
        $product = $this->product();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/products/' . $product->id . '/images', [
                'url'        => 'https://example.com/producto.jpg',
                'is_primary' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_primary', true)
            ->assertJsonPath('data.image_url', 'https://example.com/producto.jpg');

        $this->assertDatabaseHas('product_images', [
            'product_id' => $product->id,
            'image_url'  => 'https://example.com/producto.jpg',
            'is_primary' => true,
            'status'     => 'active',
        ]);
    }

    public function test_imagen_url_invalida_rechazada()
    {
        Storage::fake('public');
        $admin   = $this->admin();
        $product = $this->product();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/products/' . $product->id . '/images', [
                'url' => 'no-es-una-url-valida',
            ])
            ->assertStatus(422);
    }

    public function test_imagen_sin_archivo_ni_url_rechazado()
    {
        Storage::fake('public');
        $admin   = $this->admin();
        $product = $this->product();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/products/' . $product->id . '/images', [])
            ->assertStatus(422);
    }

    public function test_imagen_eliminacion()
    {
        Storage::fake('public');
        $admin   = $this->admin();
        $product = $this->product();

        $image = ProductImage::create([
            'product_id' => $product->id,
            'image_url'  => 'products/images/test.jpg',
            'is_primary' => false,
            'status'     => 'active',
        ]);

        $this->actingAs($admin)
            ->deleteJson('/api/v1/admin/products/' . $product->id . '/images/' . $image->id)
            ->assertStatus(204);

        $this->assertDatabaseHas('product_images', [
            'id'     => $image->id,
            'status' => 'inactive',
        ]);
    }

    public function test_imagenes_presentes_cuando_relacion_cargada()
    {
        $product = $this->product();

        ProductImage::create([
            'product_id' => $product->id,
            'image_url'  => 'https://example.com/img.jpg',
            'is_primary' => true,
            'status'     => 'active',
        ]);

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/products/' . $product->id);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['images']])
            ->assertJsonPath('data.images.0.is_primary', true)
            ->assertJsonPath('data.images.0.image_url', 'https://example.com/img.jpg');
    }

    public function test_producto_sin_imagenes_retorna_array_vacio()
    {
        $product = $this->product();

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/products/' . $product->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.images', []);
    }

    public function test_producto_con_imagen_principal_marcada()
    {
        $product = $this->product();

        ProductImage::create([
            'product_id' => $product->id,
            'image_url'  => 'https://example.com/secondary.jpg',
            'is_primary' => false,
            'status'     => 'active',
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'image_url'  => 'https://example.com/primary.jpg',
            'is_primary' => true,
            'status'     => 'active',
        ]);

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/products/' . $product->id);

        $response->assertStatus(200);
        $images = $response->json('data.images');
        $this->assertCount(2, $images);
        $primary = array_values(array_filter($images, fn($i) => $i['is_primary']));
        $this->assertCount(1, $primary);
        $this->assertEquals('https://example.com/primary.jpg', $primary[0]['image_url']);
    }
}
