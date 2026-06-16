<?php

namespace Tests\Feature\Api\V1\Barcodes;

use App\AuditLog;
use App\Brand;
use App\Category;
use App\Ingredient;
use App\Product;
use App\ProductBarcode;
use App\ProductCategory;
use App\Role;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BarcodesTest extends TestCase
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
        $name = $data['name'] ?? 'Marca ' . uniqid();

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
            'name' => 'Categoria ' . uniqid(),
            'description' => null,
            'parent_id' => null,
            'status' => 'active',
        ], $data));
    }

    private function unit(array $data = [])
    {
        return UnitMeasure::create(array_merge([
            'code' => 'u_' . uniqid(),
            'name' => 'Unidad',
            'type' => 'mass',
            'symbol' => 'u',
            'status' => 'active',
        ], $data));
    }

    private function ingredient(array $data = [])
    {
        $unit = $data['unit'] ?? $this->unit(['code' => 'g_' . uniqid(), 'name' => 'Gramo', 'symbol' => 'g']);
        $name = $data['name'] ?? 'Ingrediente ' . uniqid();

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
        $name = $data['name'] ?? 'Producto ' . uniqid();

        return Product::create(array_merge([
            'nombre' => $name,
            'brand_id' => $brand->id,
            'codigo' => 'BC' . uniqid(),
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
        ], Arr::except($data, ['brand', 'category', 'ingredient', 'unit'])));
    }

    private function barcode(Product $product, string $barcode = null, string $status = 'active')
    {
        return ProductBarcode::create([
            'product_id' => $product->id,
            'barcode' => $barcode ?? ('7790' . uniqid()),
            'type' => null,
            'status' => $status,
        ]);
    }

    // Test 1: búsqueda exitosa por barcode
    public function test_busqueda_exitosa_por_barcode()
    {
        $product = $this->product(['name' => 'Arroz Gallo']);
        $this->barcode($product, '7791234567890');

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/products/barcode/7791234567890');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.name', 'Arroz Gallo')
            ->assertJsonPath('data.barcode', '7791234567890')
            ->assertJsonStructure(['data', 'trace_id']);
    }

    // Test 2: barcode inexistente devuelve 404
    public function test_barcode_inexistente_devuelve_404()
    {
        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/products/barcode/9999999999999');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'PRODUCT_NOT_FOUND');
    }

    // Test 3: producto inactivo no se devuelve
    public function test_producto_inactivo_no_se_devuelve()
    {
        $product = $this->product(['status' => 'inactive', 'is_active' => false, 'habilitado' => 0]);
        $this->barcode($product, '7790000000001');

        $response = $this->actingAs(factory(User::class)->create())
            ->getJson('/api/v1/products/barcode/7790000000001');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'PRODUCT_NOT_FOUND');
    }

    // Test 4: admin sin permiso obtiene 403
    public function test_admin_sin_permiso_obtiene_403()
    {
        $product = $this->product();
        $user = factory(User::class)->create();

        $this->actingAs($user)
            ->postJson('/api/v1/admin/products/' . $product->id . '/barcodes', [
                'barcode' => '7790000000002',
            ])
            ->assertStatus(403);
    }

    // Test 5: alta exitosa de barcode
    public function test_alta_exitosa_de_barcode()
    {
        $admin = $this->admin();
        $product = $this->product();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/products/' . $product->id . '/barcodes', [
                'barcode' => '7790000000003',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data', 'trace_id']);

        $this->assertDatabaseHas('product_barcodes', [
            'product_id' => $product->id,
            'barcode' => '7790000000003',
            'status' => 'active',
        ]);
    }

    // Test 6: barcode duplicado en catálogo devuelve 409
    public function test_barcode_duplicado_devuelve_409()
    {
        $admin = $this->admin();
        $other = $this->product();
        $this->barcode($other, '7790000000004');

        $product = $this->product();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/products/' . $product->id . '/barcodes', [
                'barcode' => '7790000000004',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PRODUCT_BARCODE_ALREADY_EXISTS');
    }

    // Test 7: preservación de ceros iniciales
    public function test_preservacion_de_ceros_iniciales()
    {
        $admin = $this->admin();
        $product = $this->product();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/products/' . $product->id . '/barcodes', [
                'barcode' => '0001234567890',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('product_barcodes', [
            'product_id' => $product->id,
            'barcode' => '0001234567890',
        ]);
    }

    // Test 8: eliminación exitosa
    public function test_eliminacion_exitosa()
    {
        $admin = $this->admin();
        $product = $this->product();
        $barcodeRecord = $this->barcode($product, '7790000000005');

        $this->actingAs($admin)
            ->deleteJson('/api/v1/admin/products/' . $product->id . '/barcodes/' . $barcodeRecord->id)
            ->assertStatus(204);

        $this->assertDatabaseHas('product_barcodes', [
            'id' => $barcodeRecord->id,
            'status' => 'inactive',
        ]);
    }

    // Test 9: barcode de otro producto rechazado (IDOR)
    public function test_barcode_de_otro_producto_rechazado()
    {
        $admin = $this->admin();
        $productA = $this->product();
        $productB = $this->product();
        $barcodeRecord = $this->barcode($productB, '7790000000006');

        $this->actingAs($admin)
            ->deleteJson('/api/v1/admin/products/' . $productA->id . '/barcodes/' . $barcodeRecord->id)
            ->assertStatus(404);
    }

    // Test 10: auditoría registrada
    public function test_auditoria_registrada()
    {
        $admin = $this->admin();
        $product = $this->product();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/products/' . $product->id . '/barcodes', [
                'barcode' => '7790000000007',
            ])
            ->assertStatus(201);

        $this->assertTrue(
            AuditLog::where('entity_name', 'product_barcodes')
                ->where('action', 'product_barcode.created')
                ->exists()
        );
    }
}
