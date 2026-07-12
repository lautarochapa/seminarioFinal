<?php

namespace Tests\Feature\Api\V1\ProductImages;

use App\AuditLog;
use App\Brand;
use App\Ingredient;
use App\Product;
use App\ProductCategory;
use App\ProductImage;
use App\Role;
use App\UnitMeasure;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

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

    private function product(array $data = [])
    {
        $brand = Brand::create([
            'nombre' => 'Marca ' . uniqid(),
            'name' => 'Marca ' . uniqid(),
            'normalized_name' => 'marca_' . uniqid(),
            'status' => 'active',
            'padre' => 0,
        ]);

        $category = ProductCategory::create([
            'name' => 'Cat ' . uniqid(),
            'status' => 'active',
        ]);

        $unit = UnitMeasure::create([
            'code' => 'u_' . uniqid(),
            'name' => 'Unidad',
            'type' => 'mass',
            'symbol' => 'u',
            'status' => 'active',
        ]);

        $ingredient = Ingredient::create([
            'name' => 'Ing ' . uniqid(),
            'normalized_name' => 'ing_' . uniqid(),
            'base_unit_id' => $unit->id,
            'is_generic' => true,
            'is_preparation' => false,
            'is_supplement' => false,
            'status' => 'active',
        ]);

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
            'is_verified' => false,
            'is_active' => true,
            'status' => 'active',
        ], Arr::except($data, ['name'])));
    }

    private function image(Product $product, array $data = [])
    {
        return ProductImage::create(array_merge([
            'product_id' => $product->id,
            'image_url' => 'products/images/test-' . uniqid() . '.jpg',
            'source' => null,
            'is_primary' => false,
            'status' => 'active',
        ], $data));
    }

    // Test 1: sin autenticación → 401
    public function test_sin_autenticacion_retorna_401()
    {
        $product = $this->product();

        $this->postJson('/api/v1/admin/products/' . $product->id . '/images')
            ->assertStatus(401);
    }

    // Test 2: sin permiso → 403
    public function test_sin_permiso_retorna_403()
    {
        $product = $this->product();

        $this->actingAs(factory(User::class)->create())
            ->postJson('/api/v1/admin/products/' . $product->id . '/images', [
                'url' => 'https://example.com/image.jpg',
            ])
            ->assertStatus(403);
    }

    // Test 3: carga de archivo exitosa → 201
    public function test_carga_exitosa_retorna_201()
    {
        $admin = $this->admin();
        $product = $this->product();

        $file = UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/products/' . $product->id . '/images', [
                'image' => $file,
                'is_primary' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'product_id', 'image_url', 'is_primary'], 'trace_id']);

        $this->assertDatabaseHas('product_images', [
            'product_id' => $product->id,
            'is_primary' => true,
            'status' => 'active',
        ]);
    }

    // Test 4: tipo de archivo inválido → 422
    public function test_tipo_invalido_retorna_422()
    {
        $admin = $this->admin();
        $product = $this->product();

        $file = UploadedFile::fake()->create('script.php', 10, 'application/x-php');

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/products/' . $product->id . '/images', [
                'image' => $file,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    // Test 5: tamaño inválido → 422
    public function test_tamano_invalido_retorna_422()
    {
        $admin = $this->admin();
        $product = $this->product();

        $file = UploadedFile::fake()->create('photo.jpg', 6000, 'image/jpeg');

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/products/' . $product->id . '/images', [
                'image' => $file,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    // Test 6: producto inexistente → 404
    public function test_producto_inexistente_retorna_404()
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/products/999999/images', [
                'url' => 'https://example.com/image.jpg',
            ])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'PRODUCT_NOT_FOUND');
    }

    // Test 7: URL válida → 201
    public function test_url_valida_retorna_201()
    {
        $admin = $this->admin();
        $product = $this->product();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/products/' . $product->id . '/images', [
                'url' => 'https://example.com/product-image.jpg',
                'source' => 'scraped',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.image_url', 'https://example.com/product-image.jpg')
            ->assertJsonPath('data.source', 'scraped');

        $this->assertDatabaseHas('product_images', [
            'product_id' => $product->id,
            'image_url' => 'https://example.com/product-image.jpg',
            'source' => 'scraped',
        ]);
    }

    // Test 8: eliminación exitosa → 204
    public function test_eliminacion_exitosa_retorna_204()
    {
        $admin = $this->admin();
        $product = $this->product();
        $imageRecord = $this->image($product);

        $this->actingAs($admin)
            ->deleteJson('/api/v1/admin/products/' . $product->id . '/images/' . $imageRecord->id)
            ->assertStatus(204);

        $this->assertDatabaseHas('product_images', [
            'id' => $imageRecord->id,
            'status' => 'inactive',
        ]);
    }

    // Test 9: imagen de otro producto rechazada (IDOR) → 404
    public function test_imagen_de_otro_producto_rechazada()
    {
        $admin = $this->admin();
        $productA = $this->product();
        $productB = $this->product();
        $imageRecord = $this->image($productB);

        $this->actingAs($admin)
            ->deleteJson('/api/v1/admin/products/' . $productA->id . '/images/' . $imageRecord->id)
            ->assertStatus(404);
    }

    // Test 10: auditoría registrada
    public function test_auditoria_registrada()
    {
        $admin = $this->admin();
        $product = $this->product();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/products/' . $product->id . '/images', [
                'url' => 'https://example.com/audit-test.jpg',
            ])
            ->assertStatus(201);

        $this->assertTrue(
            AuditLog::where('entity_name', 'product_images')
                ->where('action', 'product_image.created')
                ->exists()
        );
    }
}
